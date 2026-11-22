package handlers

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"cashflow/integration-service-final/internal/config"
	"cashflow/integration-service-final/internal/models"
	"cashflow/integration-service-final/internal/pkg/httpx"
	"cashflow/integration-service-final/internal/pkg/locker"
	"cashflow/integration-service-final/internal/services"
	"github.com/gin-gonic/gin"
)

func TestKeyedLockerSameKey(t *testing.T) {
	l := locker.New()
	counter := 0
	var wg sync.WaitGroup

	for i := 0; i < 500; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			unlock := l.Lock("VCB:001")
			defer unlock()
			v := counter
			time.Sleep(time.Microsecond)
			counter = v + 1
		}()
	}
	wg.Wait()

	if counter != 500 {
		t.Fatalf("mong đợi 500, nhận được %d", counter)
	}
	if n := l.Size(); n != 0 {
		t.Fatalf("map khóa phải rỗng sau khi xong, còn %d key", n)
	}
}

func TestKeyedLockerDifferentKeysRunInParallel(t *testing.T) {
	l := locker.New()
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

func TestMoneyPrecision(t *testing.T) {
	cases := map[string]models.Money{
		"0.00":        0,
		"15000000.00": 1500000000,
		"12.5":        1250,
		"7":           700,
		"-3.25":       -325,
	}
	for in, want := range cases {
		got, err := models.ParseMoney(in)
		if err != nil || got != want {
			t.Errorf("ParseMoney(%q) = %d, %v; muốn %d", in, got, err, want)
		}
	}
	if s := models.FormatVND(models.Money(150000000)); s != "1.500.000" {
		t.Errorf("FormatVND được %s", s)
	}
}

func TestNormalizeWebhook(t *testing.T) {
	base := WebhookRequest{Provider: "vcb", ExternalRef: "FT001", AccountNo: "0071000123456", Direction: "credit", Amount: 100000}

	in, err := base.Normalize("")
	if err != nil {
		t.Fatal(err)
	}
	if in.Key != "VCB:FT001" || in.BankCode != "VCB" || in.Direction != "CREDIT" || in.Amount != 10000000 {
		t.Fatalf("chuẩn hóa sai: %+v", in)
	}

	withHeader, _ := base.Normalize("my-key-1")
	if withHeader.Key != "my-key-1" {
		t.Fatal("phải ưu tiên Idempotency-Key trên header")
	}

	bad := base
	bad.Amount = -1
	if _, err := bad.Normalize(""); err == nil {
		t.Fatal("amount âm phải bị từ chối")
	}
}

func TestValidSignature(t *testing.T) {
	body := []byte(`{"external_ref":"FT001"}`)
	mac := hmac.New(sha256.New, []byte("secret"))
	mac.Write(body)
	sig := hex.EncodeToString(mac.Sum(nil))

	if !ValidSignature(body, sig, "secret") {
		t.Fatal("chữ ký đúng mà bị từ chối")
	}
	if ValidSignature(body, sig, "secret-khac") {
		t.Fatal("sai secret mà vẫn qua")
	}
}

func TestGinUserIDAndRole(t *testing.T) {
	gin.SetMode(gin.TestMode)

	w := httptest.NewRecorder()
	c, _ := gin.CreateTestContext(w)
	c.Request = httptest.NewRequest(http.MethodGet, "/api/integrations/accounts?user_id=2", nil)
	c.Request.Header.Set("X-User-Id", "1")
	c.Request.Header.Set("X-User-Role", "USER")

	if got := httpx.UserIDFrom(c); got != 1 {
		t.Fatalf("user thường sửa ?user_id phải bị bỏ qua, được %d", got)
	}
	c.Request.Header.Set("X-User-Role", "ADMIN")
	if got := httpx.UserIDFrom(c); got != 2 {
		t.Fatalf("admin truyền ?user_id=2 phải xem user 2, được %d", got)
	}
}

func TestGinRouterHealthAndErrors(t *testing.T) {
	gin.SetMode(gin.TestMode)
	cfg := config.Load()
	syncer := services.NewSyncer(nil, cfg, &http.Client{}, services.NewNotifier(cfg.NotificationURL, &http.Client{}))
	srv := NewServer(cfg, nil, syncer, nil)
	r := srv.SetupRouter()

	w1 := httptest.NewRecorder()
	r.ServeHTTP(w1, httptest.NewRequest(http.MethodGet, "/health", nil))
	if w1.Code != http.StatusServiceUnavailable {
		t.Fatalf("expected 503 when db nil, got %d", w1.Code)
	}

	w2 := httptest.NewRecorder()
	r.ServeHTTP(w2, httptest.NewRequest(http.MethodDelete, "/api/integrations/rates", nil))
	if w2.Code != http.StatusMethodNotAllowed {
		t.Fatalf("expected 405, got %d", w2.Code)
	}

	w3 := httptest.NewRecorder()
	r.ServeHTTP(w3, httptest.NewRequest(http.MethodGet, "/khong-ton-tai", nil))
	if w3.Code != http.StatusNotFound {
		t.Fatalf("expected 404, got %d", w3.Code)
	}
}

func TestGinValidationAndWebhookSignature(t *testing.T) {
	gin.SetMode(gin.TestMode)
	cfg := config.Load()
	cfg.WebhookSecret = "my-webhook-secret"
	syncer := services.NewSyncer(nil, cfg, &http.Client{}, services.NewNotifier(cfg.NotificationURL, &http.Client{}))
	srv := NewServer(cfg, nil, syncer, nil)
	r := srv.SetupRouter()

	// 1. CreateAccount validation (invalid bank_code)
	req1 := httptest.NewRequest(http.MethodPost, "/api/integrations/accounts",
		strings.NewReader(`{"user_id":1,"bank_code":"!","account_no":"0071000123456","holder_name":"TEST"}`))
	req1.Header.Set("Content-Type", "application/json")
	w1 := httptest.NewRecorder()
	r.ServeHTTP(w1, req1)
	if w1.Code != http.StatusUnprocessableEntity {
		t.Fatalf("CreateAccount invalid bank_code expected 422, got %d", w1.Code)
	}

	// 2. Webhook invalid HMAC signature -> 401
	req2 := httptest.NewRequest(http.MethodPost, "/api/integrations/webhooks/banking",
		strings.NewReader(`{"provider":"VCB","external_ref":"FT1","account_no":"0071000123456","direction":"CREDIT","amount":10000}`))
	req2.Header.Set("Content-Type", "application/json")
	req2.Header.Set("X-Signature", "bad-sig")
	w2 := httptest.NewRecorder()
	r.ServeHTTP(w2, req2)
	if w2.Code != http.StatusUnauthorized {
		t.Fatalf("Webhook bad signature expected 401, got %d", w2.Code)
	}

	// 3. BatchDeleteBankTx missing filter -> 400
	req3 := httptest.NewRequest(http.MethodDelete, "/api/integrations/bank-transactions", nil)
	w3 := httptest.NewRecorder()
	r.ServeHTTP(w3, req3)
	if w3.Code != http.StatusBadRequest {
		t.Fatalf("BatchDelete missing filter expected 400, got %d", w3.Code)
	}

	// 4. Simulate validation (events > 2000) -> 422
	req4 := httptest.NewRequest(http.MethodPost, "/api/integrations/simulate",
		strings.NewReader(`{"account_id":1,"events":9999,"workers":10,"mode":"safe"}`))
	req4.Header.Set("Content-Type", "application/json")
	w4 := httptest.NewRecorder()
	r.ServeHTTP(w4, req4)
	if w4.Code != http.StatusUnprocessableEntity {
		t.Fatalf("Simulate events > 2000 expected 422, got %d", w4.Code)
	}
}
