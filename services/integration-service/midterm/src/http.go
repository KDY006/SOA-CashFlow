package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"runtime/debug"
	"strconv"
	"time"
	"unicode/utf8"
)

const maxBodySize = 1 << 20

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	enc := json.NewEncoder(w)
	enc.SetEscapeHTML(false)
	_ = enc.Encode(v)
}

func ok(w http.ResponseWriter, status int, msg string, data any) {
	writeJSON(w, status, map[string]any{"success": true, "message": msg, "data": data})
}

func fail(w http.ResponseWriter, status int, code, msg string) {
	writeJSON(w, status, map[string]any{"success": false, "error": code, "message": msg})
}

var errBodyTooLarge = errors.New("body vượt quá 1MB")

func readBody(r *http.Request) ([]byte, error) {
	raw, err := io.ReadAll(io.LimitReader(r.Body, maxBodySize+1))
	if err != nil {
		return nil, err
	}
	if len(raw) > maxBodySize {
		return nil, errBodyTooLarge
	}
	return raw, nil
}

func decodeJSON(r *http.Request, dst any) error {
	raw, err := readBody(r)
	if err != nil {
		return err
	}
	if len(raw) == 0 {
		return errors.New("body rỗng")
	}
	if err := json.Unmarshal(raw, dst); err != nil {
		return fmt.Errorf("json không hợp lệ: %v", err)
	}
	return nil
}

func pathID(r *http.Request) (int64, bool) {
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	return id, err == nil && id > 0
}

// user đã đăng nhập, do gateway giải mã jwt rồi gắn vào header X-User-Id.
// gọi thẳng vào service (không qua gateway) thì không có header này -> trả 0
func authUserID(r *http.Request) int64 {
	if v, err := strconv.ParseInt(r.Header.Get("X-User-Id"), 10, 64); err == nil && v > 0 {
		return v
	}
	return 0
}

// ưu tiên header của gateway, để user không xem được dữ liệu người khác chỉ bằng cách sửa ?user_id.
// không có header (gọi thẳng service khi test) thì mới lấy theo query
func userIDFrom(r *http.Request) int64 {
	if v := authUserID(r); v > 0 {
		return v
	}
	if v, err := strconv.ParseInt(r.URL.Query().Get("user_id"), 10, 64); err == nil && v > 0 {
		return v
	}
	return 0
}

// có user đăng nhập mà không phải chủ dữ liệu thì coi như không tồn tại (trả 404 thay vì 403
// để không lộ ra là id đó có tồn tại)
func forbiddenFor(r *http.Request, ownerID int64) bool {
	uid := authUserID(r)
	return uid > 0 && uid != ownerID
}

// cắt chuỗi theo số ký tự, không cắt ngang giữa ký tự tiếng việt
func truncate(s string, max int) string {
	if utf8.RuneCountInString(s) <= max {
		return s
	}
	r := []rune(s)
	return string(r[:max])
}

type statusRecorder struct {
	http.ResponseWriter
	status int
}

func (r *statusRecorder) WriteHeader(code int) {
	r.status = code
	r.ResponseWriter.WriteHeader(code)
}

func withMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		rec := &statusRecorder{ResponseWriter: w, status: http.StatusOK}

		defer func() {
			if v := recover(); v != nil {
				log.Printf("panic: %v\n%s", v, debug.Stack())
				fail(rec, http.StatusInternalServerError, "INTERNAL_ERROR", "lỗi hệ thống")
			}
			log.Printf("%s %s %d %s", r.Method, r.URL.Path, rec.status, time.Since(start).Round(time.Millisecond))
		}()

		h := w.Header()
		h.Set("Access-Control-Allow-Origin", "*")
		h.Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		h.Set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-User-Id, Idempotency-Key, X-Signature")
		if r.Method == http.MethodOptions {
			rec.WriteHeader(http.StatusNoContent)
			return
		}

		next.ServeHTTP(rec, r)
	})
}
