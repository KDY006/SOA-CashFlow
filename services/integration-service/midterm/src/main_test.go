package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"
	"time"
)

func TestKeyedLockerSameKey(t *testing.T) {
	l := newKeyedLocker()
	counter := 0
	var wg sync.WaitGroup

	for i := 0; i < 500; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			unlock := l.Lock("VCB:001")
			defer unlock()
			// đọc - nghỉ - ghi, không có khóa là chắc chắn mất cập nhật
			v := counter
			time.Sleep(time.Microsecond)
			counter = v + 1
		}()
	}
	wg.Wait()

	if counter != 500 {
		t.Fatalf("mong đợi 500, nhận được %d", counter)
	}
	if n := l.size(); n != 0 {
		t.Fatalf("map khóa phải rỗng sau khi xong, còn %d key", n)
	}
}

func TestKeyedLockerDifferentKeysRunInParallel(t *testing.T) {
	l := newKeyedLocker()
	unlockA := l.Lock("A")
	defer unlockA()

	done := make(chan struct{})
	go func() {
		unlock := l.Lock("B")
		unlock()
		close(done)
	}()

	select {
	case <-done:
	case <-time.After(time.Second):
		t.Fatal("key B bị chặn bởi key A")
	}
}

func TestParseMoney(t *testing.T) {
	cases := map[string]money{
		"0.00":        0,
		"15000000.00": 1500000000,
		"12.5":        1250,
		"7":           700,
		"-3.25":       -325,
		"1.999":       199,
	}
	for in, want := range cases {
		got, err := parseMoney(in)
		if err != nil || got != want {
			t.Errorf("parseMoney(%q) = %d, %v; muốn %d", in, got, err, want)
		}
	}
	if _, err := parseMoney("abc"); err == nil {
		t.Error("parseMoney(abc) phải báo lỗi")
	}
}

func TestMoneyString(t *testing.T) {
	if s := money(1500000050).String(); s != "15000000.50" {
		t.Errorf("được %s", s)
	}
	if s := money(-5).String(); s != "-0.05" {
		t.Errorf("được %s", s)
	}
	if s := formatVND(money(150000000)); s != "1.500.000" {
		t.Errorf("formatVND được %s", s)
	}
}

func TestNormalizeWebhook(t *testing.T) {
	base := webhookRequest{Provider: "vcb", ExternalRef: "FT001", AccountNo: "0071000123456", Direction: "credit", Amount: 100000}

	in, err := base.normalize("")
	if err != nil {
		t.Fatal(err)
	}
	if in.Key != "VCB:FT001" || in.BankCode != "VCB" || in.Direction != "CREDIT" || in.Amount != 10000000 {
		t.Fatalf("chuẩn hóa sai: %+v", in)
	}

	// cùng nội dung thì hash phải giống nhau, khác số tiền thì hash khác
	again, _ := base.normalize("")
	other := base
	other.Amount = 200000
	changed, _ := other.normalize("")
	if in.Hash != again.Hash || in.Hash == changed.Hash {
		t.Fatal("hash nội dung không đúng")
	}

	withHeader, _ := base.normalize("my-key-1")
	if withHeader.Key != "my-key-1" {
		t.Fatal("phải ưu tiên Idempotency-Key trên header")
	}

	bad := base
	bad.Amount = -1
	if _, err := bad.normalize(""); err == nil {
		t.Fatal("amount âm phải bị từ chối")
	}
	bad = base
	bad.Direction = "IN"
	if _, err := bad.normalize(""); err == nil {
		t.Fatal("direction sai phải bị từ chối")
	}
}

func TestValidSignature(t *testing.T) {
	body := []byte(`{"external_ref":"FT001"}`)
	mac := hmac.New(sha256.New, []byte("secret"))
	mac.Write(body)
	sig := hex.EncodeToString(mac.Sum(nil))

	if !validSignature(body, sig, "secret") {
		t.Fatal("chữ ký đúng mà bị từ chối")
	}
	if validSignature(body, sig, "secret-khac") {
		t.Fatal("sai secret mà vẫn qua")
	}
	if validSignature([]byte(`{"external_ref":"FT002"}`), sig, "secret") {
		t.Fatal("body bị sửa mà vẫn qua")
	}
}

func TestUserIDFromPrefersGatewayHeader(t *testing.T) {
	r := httptest.NewRequest(http.MethodGet, "/api/integrations/accounts?user_id=1", nil)
	if got := userIDFrom(r); got != 1 {
		t.Fatalf("không có header thì lấy theo query, được %d", got)
	}
	r.Header.Set("X-User-Id", "2")
	if got := userIDFrom(r); got != 2 {
		t.Fatalf("có header của gateway thì phải bỏ qua query, được %d", got)
	}
	if forbiddenFor(r, 2) {
		t.Fatal("chủ tài khoản mà bị chặn")
	}
	if !forbiddenFor(r, 1) {
		t.Fatal("user 2 xem dữ liệu user 1 mà không bị chặn")
	}
	noHeader := httptest.NewRequest(http.MethodGet, "/", nil)
	if forbiddenFor(noHeader, 1) {
		t.Fatal("gọi thẳng service (không có header) thì không chặn")
	}
}

func TestRouterMethodNotAllowed(t *testing.T) {
	s := &Server{}
	h := s.routes()

	cases := []struct {
		method, path string
		want         int
		allow        string
	}{
		{http.MethodDelete, "/api/integrations/rates", http.StatusMethodNotAllowed, "GET"},
		{http.MethodGet, "/api/integrations/webhooks/banking", http.StatusMethodNotAllowed, "POST"},
		{http.MethodPut, "/api/integrations/accounts/5", http.StatusMethodNotAllowed, "GET"},
		{http.MethodGet, "/khong-ton-tai", http.StatusNotFound, ""},
	}
	for _, c := range cases {
		w := httptest.NewRecorder()
		h.ServeHTTP(w, httptest.NewRequest(c.method, c.path, nil))
		if w.Code != c.want || w.Header().Get("Allow") != c.allow {
			t.Errorf("%s %s: được %d (Allow=%q), muốn %d (Allow=%q)", c.method, c.path, w.Code, w.Header().Get("Allow"), c.want, c.allow)
		}
	}
}
