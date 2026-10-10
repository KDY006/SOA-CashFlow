// loadtest bắn webhook đồng thời qua HTTP để kiểm tra race condition và idempotency.
//
//	go run ./tools/loadtest -url http://localhost:8085 -account 1 -n 200 -dup 3 -c 50
//
// mỗi giao dịch được gửi -dup lần (giả lập ngân hàng retry), tất cả trộn lẫn và chạy song song.
// cuối cùng so số dư thực tế với số dư tính tay, lệch thì thoát với mã 1.
package main

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"os"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
)

type account struct {
	ID        int64       `json:"id"`
	BankCode  string      `json:"bank_code"`
	AccountNo string      `json:"account_no"`
	Balance   json.Number `json:"balance"`
}

type result struct {
	status    int
	duplicate bool
	txStatus  string
	direction string
	amount    int64 // đồng
	err       error
	latency   time.Duration
}

var token string

func main() {
	baseURL := flag.String("url", "http://localhost:8085", "địa chỉ integration-service (hoặc gateway)")
	accountID := flag.Int64("account", 1, "id tài khoản liên kết")
	n := flag.Int("n", 200, "số giao dịch khác nhau")
	dup := flag.Int("dup", 3, "mỗi giao dịch gửi bao nhiêu lần")
	c := flag.Int("c", 50, "số request chạy song song")
	debitRatio := flag.Float64("debit", 0.4, "tỉ lệ giao dịch trừ tiền")
	secret := flag.String("secret", "", "WEBHOOK_SECRET nếu service có bật kiểm tra chữ ký")
	flag.StringVar(&token, "token", "", "access token jwt, cần khi bắn qua gateway")
	flag.Parse()

	client := &http.Client{Timeout: 30 * time.Second}

	before, err := getAccount(client, *baseURL, *accountID)
	if err != nil {
		fmt.Println("không lấy được tài khoản:", err)
		os.Exit(1)
	}
	balanceBefore := toCents(before.Balance)

	runID := time.Now().Unix()
	type job struct {
		body      []byte
		direction string
		amount    int64
	}
	var jobs []job
	for i := 0; i < *n; i++ {
		dir := "CREDIT"
		if rand.Float64() < *debitRatio {
			dir = "DEBIT"
		}
		amount := int64(rand.Intn(290)+10) * 1000
		body, _ := json.Marshal(map[string]any{
			"provider":     before.BankCode,
			"external_ref": fmt.Sprintf("LT%d-%05d", runID, i+1),
			"bank_code":    before.BankCode,
			"account_no":   before.AccountNo,
			"direction":    dir,
			"amount":       amount,
			"description":  "load test",
		})
		for k := 0; k < *dup; k++ {
			jobs = append(jobs, job{body: body, direction: dir, amount: amount})
		}
	}
	rand.Shuffle(len(jobs), func(i, j int) { jobs[i], jobs[j] = jobs[j], jobs[i] })

	fmt.Printf("tài khoản #%d %s-%s, số dư đầu: %s\n", before.ID, before.BankCode, before.AccountNo, fmtCents(balanceBefore))
	fmt.Printf("gửi %d request (%d giao dịch x %d lần), %d luồng song song...\n\n", len(jobs), *n, *dup, *c)

	queue := make(chan job)
	results := make(chan result, len(jobs))
	var wg sync.WaitGroup
	start := time.Now()
	for i := 0; i < *c; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for j := range queue {
				results <- send(client, *baseURL, *secret, j.body, j.direction, j.amount)
			}
		}()
	}
	for _, j := range jobs {
		queue <- j
	}
	close(queue)
	wg.Wait()
	close(results)
	elapsed := time.Since(start)

	statusCount := map[int]int{}
	var created, duplicates, rejected, errCount int
	var applied int64
	var latencies []time.Duration
	for r := range results {
		if r.err != nil {
			errCount++
			continue
		}
		latencies = append(latencies, r.latency)
		statusCount[r.status]++
		switch {
		case r.duplicate:
			duplicates++
		case r.txStatus == "REJECTED":
			rejected++
		case r.status == http.StatusCreated:
			created++
			if r.direction == "CREDIT" {
				applied += r.amount * 100
			} else {
				applied -= r.amount * 100
			}
		}
	}

	after, err := getAccount(client, *baseURL, *accountID)
	if err != nil {
		fmt.Println("không lấy được tài khoản sau khi test:", err)
		os.Exit(1)
	}
	balanceAfter := toCents(after.Balance)
	expected := balanceBefore + applied

	sort.Slice(latencies, func(i, j int) bool { return latencies[i] < latencies[j] })

	fmt.Println("===== kết quả =====")
	fmt.Printf("thời gian chạy     : %s (%.0f req/s)\n", elapsed.Round(time.Millisecond), float64(len(jobs))/elapsed.Seconds())
	if len(latencies) > 0 {
		fmt.Printf("độ trễ p50 / p95   : %s / %s\n", pct(latencies, 50), pct(latencies, 95))
	}
	fmt.Printf("http status        : %v\n", statusCount)
	fmt.Printf("ghi nhận mới       : %d\n", created)
	fmt.Printf("bị từ chối (hết số dư): %d\n", rejected)
	fmt.Printf("trùng lặp bị chặn  : %d\n", duplicates)
	fmt.Printf("lỗi kết nối        : %d\n", errCount)
	fmt.Printf("số giao dịch duy nhất: %d, đã xử lý: %d\n", *n, created+rejected)
	fmt.Printf("số dư thực tế      : %s\n", fmtCents(balanceAfter))
	fmt.Printf("số dư mong đợi     : %s\n", fmtCents(expected))

	if balanceAfter != expected || created+rejected != *n || errCount > 0 {
		fmt.Println("\n=> KHÔNG ĐẠT: dữ liệu bị lệch")
		os.Exit(1)
	}
	fmt.Println("\n=> ĐẠT: số dư khớp, mỗi giao dịch chỉ được ghi nhận đúng 1 lần")
}

func send(client *http.Client, baseURL, secret string, body []byte, direction string, amount int64) result {
	req, _ := http.NewRequest(http.MethodPost, baseURL+"/api/integrations/webhooks/banking", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	if token != "" {
		req.Header.Set("Authorization", "Bearer "+token)
	}
	if secret != "" {
		mac := hmac.New(sha256.New, []byte(secret))
		mac.Write(body)
		req.Header.Set("X-Signature", hex.EncodeToString(mac.Sum(nil)))
	}

	start := time.Now()
	resp, err := client.Do(req)
	if err != nil {
		return result{err: err}
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)

	var out struct {
		Data struct {
			Duplicate   bool `json:"duplicate"`
			Transaction struct {
				Status string `json:"status"`
			} `json:"transaction"`
		} `json:"data"`
	}
	_ = json.Unmarshal(raw, &out)
	return result{
		status:    resp.StatusCode,
		duplicate: out.Data.Duplicate,
		txStatus:  out.Data.Transaction.Status,
		direction: direction,
		amount:    amount,
		latency:   time.Since(start),
	}
}

func getAccount(client *http.Client, baseURL string, id int64) (account, error) {
	req, _ := http.NewRequest(http.MethodGet, fmt.Sprintf("%s/api/integrations/accounts/%d", baseURL, id), nil)
	if token != "" {
		req.Header.Set("Authorization", "Bearer "+token)
	}
	resp, err := client.Do(req)
	if err != nil {
		return account{}, err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return account{}, fmt.Errorf("http %d", resp.StatusCode)
	}
	var out struct {
		Data account `json:"data"`
	}
	dec := json.NewDecoder(resp.Body)
	dec.UseNumber()
	if err := dec.Decode(&out); err != nil {
		return account{}, err
	}
	return out.Data, nil
}

// "15000000.50" -> 1500000050
func toCents(n json.Number) int64 {
	intPart, frac, _ := strings.Cut(n.String(), ".")
	frac = (frac + "00")[:2]
	i, _ := strconv.ParseInt(intPart, 10, 64)
	f, _ := strconv.ParseInt(frac, 10, 64)
	return i*100 + f
}

func fmtCents(v int64) string {
	return fmt.Sprintf("%d.%02d", v/100, v%100)
}

func pct(sorted []time.Duration, p int) time.Duration {
	idx := len(sorted) * p / 100
	if idx >= len(sorted) {
		idx = len(sorted) - 1
	}
	return sorted[idx].Round(time.Millisecond)
}
