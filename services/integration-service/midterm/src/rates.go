package main

import (
	"context"
	"errors"
	"math"
	"math/rand"
	"net/http"
	"sort"
	"strings"
	"time"
)

type exchangeRate struct {
	Currency  string    `json:"currency_code"`
	RateToVND float64   `json:"rate_to_vnd"`
	UpdatedAt time.Time `json:"updated_at"`
}

// GET /api/integrations/rates?currency=USD
func (s *Server) handleGetRates(w http.ResponseWriter, r *http.Request) {
	query := "SELECT currency_code, rate_to_vnd, updated_at FROM exchange_rates"
	var args []any
	if c := strings.ToUpper(strings.TrimSpace(r.URL.Query().Get("currency"))); c != "" {
		query += " WHERE currency_code = ?"
		args = append(args, c)
	}
	query += " ORDER BY currency_code"

	rows, err := s.db.QueryContext(r.Context(), query, args...)
	if err != nil {
		s.internalError(w, err)
		return
	}
	defer rows.Close()

	list := []exchangeRate{}
	for rows.Next() {
		var e exchangeRate
		if err := rows.Scan(&e.Currency, &e.RateToVND, &e.UpdatedAt); err != nil {
			s.internalError(w, err)
			return
		}
		list = append(list, e)
	}
	if err := rows.Err(); err != nil {
		s.internalError(w, err)
		return
	}
	if len(args) > 0 && len(list) == 0 {
		fail(w, http.StatusNotFound, "NOT_FOUND", "không có tỷ giá cho loại tiền này")
		return
	}
	ok(w, http.StatusOK, "lấy tỷ giá thành công", list)
}

// tỷ giá gốc để giả lập, mỗi ngân hàng sẽ lệch một chút quanh mức này
var baseRates = map[string]float64{
	"USD": 25410, "EUR": 27620, "GBP": 32280, "JPY": 168.45, "SGD": 18920, "CNY": 3518,
}

var rateProviders = []string{"VCB", "TCB", "BIDV"}

type providerQuote struct {
	Provider  string             `json:"provider"`
	LatencyMs int64              `json:"latency_ms"`
	Error     string             `json:"error,omitempty"`
	rates     map[string]float64 `json:"-"`
}

// giả lập gọi api tỷ giá của 1 ngân hàng: có độ trễ ngẫu nhiên, đôi khi chậm quá timeout
func fetchQuote(ctx context.Context, provider string) providerQuote {
	start := time.Now()
	delay := time.Duration(100+rand.Intn(1300)) * time.Millisecond

	select {
	case <-time.After(delay):
	case <-ctx.Done():
		return providerQuote{Provider: provider, LatencyMs: time.Since(start).Milliseconds(), Error: "quá thời gian chờ"}
	}

	rates := make(map[string]float64, len(baseRates))
	for code, base := range baseRates {
		jitter := 1 + (rand.Float64()-0.5)/100 // lệch tối đa ±0.5%
		rates[code] = math.Round(base*jitter*10000) / 10000
	}
	return providerQuote{Provider: provider, LatencyMs: time.Since(start).Milliseconds(), rates: rates}
}

// POST /api/integrations/sync-rates
// gọi song song nhiều ngân hàng bằng goroutine, ai trả về kịp trong 1.2s thì lấy, rồi tính trung bình
func (s *Server) handleSyncRates(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 1200*time.Millisecond)
	defer cancel()

	results := make(chan providerQuote, len(rateProviders))
	for _, p := range rateProviders {
		go func(p string) {
			results <- fetchQuote(ctx, p)
		}(p)
	}

	quotes := make([]providerQuote, 0, len(rateProviders))
	sum := map[string]float64{}
	okCount := 0
	for range rateProviders {
		q := <-results
		quotes = append(quotes, q)
		if q.Error != "" {
			continue
		}
		okCount++
		for code, v := range q.rates {
			sum[code] += v
		}
	}
	sort.Slice(quotes, func(i, j int) bool { return quotes[i].Provider < quotes[j].Provider })

	if okCount == 0 {
		writeJSON(w, http.StatusServiceUnavailable, map[string]any{
			"success": false, "error": "PROVIDERS_UNAVAILABLE",
			"message": "không ngân hàng nào phản hồi kịp, giữ nguyên tỷ giá cũ", "providers": quotes,
		})
		return
	}

	updated, err := s.saveRates(r.Context(), sum, okCount)
	if err != nil {
		s.internalError(w, err)
		return
	}
	ok(w, http.StatusOK, "đồng bộ tỷ giá thành công", map[string]any{
		"providers_ok": okCount,
		"providers":    quotes,
		"rates":        updated,
	})
}

func (s *Server) saveRates(ctx context.Context, sum map[string]float64, n int) ([]exchangeRate, error) {
	if n == 0 {
		return nil, errors.New("không có dữ liệu")
	}
	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		return nil, err
	}
	defer tx.Rollback()

	codes := make([]string, 0, len(sum))
	for code := range sum {
		codes = append(codes, code)
	}
	sort.Strings(codes)

	now := time.Now()
	out := make([]exchangeRate, 0, len(codes))
	for _, code := range codes {
		avg := math.Round(sum[code]/float64(n)*10000) / 10000
		if _, err := tx.ExecContext(ctx, `INSERT INTO exchange_rates (currency_code, rate_to_vnd) VALUES (?, ?)
			ON DUPLICATE KEY UPDATE rate_to_vnd = VALUES(rate_to_vnd)`, code, avg); err != nil {
			return nil, err
		}
		out = append(out, exchangeRate{Currency: code, RateToVND: avg, UpdatedAt: now})
	}
	return out, tx.Commit()
}
