package models

import (
	"database/sql/driver"
	"fmt"
	"math"
	"strconv"
	"strings"
	"time"
)

// Money stores amounts in 1/100 VND (matching DECIMAL(18,2)) to avoid float64 drift.
type Money int64

func MoneyFromFloat(v float64) Money {
	return Money(math.Round(v * 100))
}

func (m Money) Float() float64 {
	return float64(m) / 100
}

func (m Money) String() string {
	v := int64(m)
	sign := ""
	if v < 0 {
		sign = "-"
		v = -v
	}
	return fmt.Sprintf("%s%d.%02d", sign, v/100, v%100)
}

func (m Money) MarshalJSON() ([]byte, error) {
	return []byte(m.String()), nil
}

func ParseMoney(s string) (Money, error) {
	s = strings.TrimSpace(s)
	neg := strings.HasPrefix(s, "-")
	s = strings.TrimPrefix(s, "-")

	intPart, frac, _ := strings.Cut(s, ".")
	if len(frac) > 2 {
		frac = frac[:2]
	}
	for len(frac) < 2 {
		frac += "0"
	}
	i, err := strconv.ParseInt(intPart, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("số tiền không hợp lệ %q", s)
	}
	f, err := strconv.ParseInt(frac, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("số tiền không hợp lệ %q", s)
	}
	v := i*100 + f
	if neg {
		v = -v
	}
	return Money(v), nil
}

func (m *Money) Scan(src any) error {
	switch v := src.(type) {
	case []byte:
		p, err := ParseMoney(string(v))
		*m = p
		return err
	case string:
		p, err := ParseMoney(v)
		*m = p
		return err
	case int64:
		*m = Money(v * 100)
		return nil
	case float64:
		*m = MoneyFromFloat(v)
		return nil
	case nil:
		*m = 0
		return nil
	}
	return fmt.Errorf("không đọc được kiểu %T thành Money", src)
}

func (m Money) Value() (driver.Value, error) {
	return m.String(), nil
}

func FormatVND(m Money) string {
	s := fmt.Sprintf("%d", int64(m)/100)
	neg := strings.HasPrefix(s, "-")
	s = strings.TrimPrefix(s, "-")
	var b strings.Builder
	for i, c := range s {
		if i > 0 && (len(s)-i)%3 == 0 {
			b.WriteByte('.')
		}
		b.WriteRune(c)
	}
	if neg {
		return "-" + b.String()
	}
	return b.String()
}

type LinkedAccount struct {
	ID         int64     `gorm:"column:id;primaryKey;autoIncrement" json:"id"`
	UserID     int64     `gorm:"column:user_id;not null" json:"user_id"`
	BankCode   string    `gorm:"column:bank_code;size:20;not null" json:"bank_code"`
	AccountNo  string    `gorm:"column:account_no;size:30;not null" json:"account_no"`
	HolderName string    `gorm:"column:holder_name;size:100;not null" json:"holder_name"`
	Balance    Money     `gorm:"column:balance;type:decimal(18,2);not null;default:0" json:"balance"`
	Version    int64     `gorm:"column:version;not null;default:0" json:"version"`
	CreatedAt  time.Time `gorm:"column:created_at;autoCreateTime" json:"created_at"`
	UpdatedAt  time.Time `gorm:"column:updated_at;autoUpdateTime" json:"updated_at"`
}

func (LinkedAccount) TableName() string { return "linked_accounts" }

type BankTransaction struct {
	ID             int64     `gorm:"column:id;primaryKey;autoIncrement" json:"id"`
	AccountID      int64     `gorm:"column:account_id;not null" json:"account_id"`
	Provider       string    `gorm:"column:provider;size:20;not null" json:"provider"`
	ExternalRef    string    `gorm:"column:external_ref;size:100;not null" json:"external_ref"`
	IdempotencyKey string    `gorm:"column:idempotency_key;size:150;not null" json:"idempotency_key"`
	RequestHash    string    `gorm:"column:request_hash;size:64;not null" json:"-"`
	Direction      string    `gorm:"column:direction;type:enum('CREDIT','DEBIT');not null" json:"direction"`
	Amount         Money     `gorm:"column:amount;type:decimal(18,2);not null" json:"amount"`
	BalanceAfter   Money     `gorm:"column:balance_after;type:decimal(18,2);not null" json:"balance_after"`
	Category       string    `gorm:"column:category;size:100;not null;default:''" json:"category"`
	Description    string    `gorm:"column:description;size:255;not null;default:''" json:"description"`
	OccurredAt     time.Time `gorm:"column:occurred_at;not null" json:"occurred_at"`
	Status         string    `gorm:"column:status;type:enum('RECEIVED','SYNCING','SYNCED','FAILED','REJECTED');not null;default:'RECEIVED'" json:"status"`
	TransactionRef *int64    `gorm:"column:transaction_ref" json:"transaction_ref"`
	SyncAttempts   int       `gorm:"column:sync_attempts;not null;default:0" json:"sync_attempts"`
	LastError      *string   `gorm:"column:last_error;size:255" json:"last_error,omitempty"`
	CreatedAt      time.Time `gorm:"column:created_at;autoCreateTime" json:"created_at"`
	UpdatedAt      time.Time `gorm:"column:updated_at;autoUpdateTime" json:"-"`
}

func (BankTransaction) TableName() string { return "bank_transactions" }

type ExchangeRate struct {
	ID        int       `gorm:"column:id;primaryKey;autoIncrement" json:"-"`
	Currency  string    `gorm:"column:currency_code;size:10;not null" json:"currency_code"`
	RateToVND float64   `gorm:"column:rate_to_vnd;type:decimal(15,4);not null" json:"rate_to_vnd"`
	UpdatedAt time.Time `gorm:"column:updated_at;autoUpdateTime" json:"updated_at"`
}

func (ExchangeRate) TableName() string { return "exchange_rates" }

type ExternalWebhook struct {
	ID           int64     `gorm:"column:id;primaryKey;autoIncrement" json:"id"`
	ProviderName string    `gorm:"column:provider_name;size:50;not null" json:"provider_name"`
	EventType    string    `gorm:"column:event_type;size:50;not null" json:"event_type"`
	Payload      string    `gorm:"column:payload;type:json" json:"payload"`
	ReceivedAt   time.Time `gorm:"column:received_at;autoCreateTime" json:"received_at"`
}

func (ExternalWebhook) TableName() string { return "external_webhooks" }
