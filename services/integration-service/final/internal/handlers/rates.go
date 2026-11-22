package handlers

import (
	"context"
	"errors"
	"math"
	"math/rand"
	"net/http"
	"sort"
	"strings"
	"time"

	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"github.com/gin-gonic/gin"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

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

func (s *Server) HandleGetRates(c *gin.Context) {
	q := s.db.WithContext(c.Request.Context()).Model(&models.ExchangeRate{})
	currency := strings.ToUpper(strings.TrimSpace(c.Query("currency")))
	if currency != "" {
		q = q.Where("currency_code = ?", currency)
	}

	list := make([]models.ExchangeRate, 0)
	if err := q.Order("currency_code ASC").Find(&list).Error; err != nil {
		s.internalError(c, err)
		return
	}
	if currency != "" && len(list) == 0 {
		httpx.Fail(c, http.StatusNotFound, "NOT_FOUND", "không có tỷ giá cho loại tiền này")
		return
	}
	httpx.OK(c, http.StatusOK, "lấy tỷ giá thành công", list)
}

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
		jitter := 1 + (rand.Float64()-0.5)/100
		rates[code] = math.Round(base*jitter*10000) / 10000
	}
	return providerQuote{Provider: provider, LatencyMs: time.Since(start).Milliseconds(), rates: rates}
}

func (s *Server) HandleSyncRates(c *gin.Context) {
	ctx, cancel := context.WithTimeout(c.Request.Context(), 1200*time.Millisecond)
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
		c.JSON(http.StatusServiceUnavailable, gin.H{
			"success":   false,
			"error":     "PROVIDERS_UNAVAILABLE",
			"message":   "không ngân hàng nào phản hồi kịp, giữ nguyên tỷ giá cũ",
			"providers": quotes,
		})
		return
	}

	updated, err := s.saveRates(c.Request.Context(), sum, okCount)
	if err != nil {
		s.internalError(c, err)
		return
	}
	httpx.OK(c, http.StatusOK, "đồng bộ tỷ giá thành công", gin.H{
		"providers_ok": okCount,
		"providers":    quotes,
		"rates":        updated,
	})
}

func (s *Server) saveRates(ctx context.Context, sum map[string]float64, n int) ([]models.ExchangeRate, error) {
	if n == 0 {
		return nil, errors.New("không có dữ liệu")
	}

	codes := make([]string, 0, len(sum))
	for code := range sum {
		codes = append(codes, code)
	}
	sort.Strings(codes)

	now := time.Now()
	out := make([]models.ExchangeRate, 0, len(codes))
	for _, code := range codes {
		avg := math.Round(sum[code]/float64(n)*10000) / 10000
		out = append(out, models.ExchangeRate{
			Currency:  code,
			RateToVND: avg,
			UpdatedAt: now,
		})
	}

	err := s.db.WithContext(ctx).Transaction(func(tx *gorm.DB) error {
		return tx.Clauses(clause.OnConflict{
			Columns:   []clause.Column{{Name: "currency_code"}},
			DoUpdates: clause.AssignmentColumns([]string{"rate_to_vnd", "updated_at"}),
		}).Create(&out).Error
	})
	return out, err
}
