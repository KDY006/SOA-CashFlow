package main

import (
	"database/sql/driver"
	"fmt"
	"math"
	"strconv"
	"strings"
)

// money lưu theo đơn vị 1/100 đồng cho khớp DECIMAL(18,2).
// dùng số nguyên để cộng trừ số dư không bị sai số như float64.
type money int64

func moneyFromFloat(v float64) money {
	return money(math.Round(v * 100))
}

func (m money) Float() float64 {
	return float64(m) / 100
}

func (m money) String() string {
	v := int64(m)
	sign := ""
	if v < 0 {
		sign = "-"
		v = -v
	}
	return fmt.Sprintf("%s%d.%02d", sign, v/100, v%100)
}

func (m money) MarshalJSON() ([]byte, error) {
	return []byte(m.String()), nil
}

func parseMoney(s string) (money, error) {
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
	return money(v), nil
}

// Scan cho phép đọc thẳng cột DECIMAL từ mysql vào kiểu money
func (m *money) Scan(src any) error {
	switch v := src.(type) {
	case []byte:
		p, err := parseMoney(string(v))
		*m = p
		return err
	case string:
		p, err := parseMoney(v)
		*m = p
		return err
	case int64:
		*m = money(v * 100)
		return nil
	case float64:
		*m = moneyFromFloat(v)
		return nil
	case nil:
		*m = 0
		return nil
	}
	return fmt.Errorf("không đọc được kiểu %T thành money", src)
}

func (m money) Value() (driver.Value, error) {
	return m.String(), nil
}
