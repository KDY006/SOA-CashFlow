package httpx

import (
	"strconv"
	"strings"
	"unicode/utf8"

	"github.com/gin-gonic/gin"
)

const MaxBodySize = 1 << 20

func OK(c *gin.Context, status int, msg string, data any) {
	c.JSON(status, gin.H{
		"success": true,
		"message": msg,
		"data":    data,
	})
}

func Fail(c *gin.Context, status int, code, msg string) {
	c.JSON(status, gin.H{
		"success": false,
		"error":   code,
		"message": msg,
	})
}

func ParsePathID(c *gin.Context) (int64, bool) {
	id, err := strconv.ParseInt(c.Param("id"), 10, 64)
	return id, err == nil && id > 0
}

func AuthUserID(c *gin.Context) int64 {
	if v, err := strconv.ParseInt(c.GetHeader("X-User-Id"), 10, 64); err == nil && v > 0 {
		return v
	}
	return 0
}

func IsAdmin(c *gin.Context) bool {
	return strings.EqualFold(c.GetHeader("X-User-Role"), "ADMIN")
}

func UserIDFrom(c *gin.Context) int64 {
	q, qErr := strconv.ParseInt(c.Query("user_id"), 10, 64)
	hasQuery := qErr == nil && q > 0
	if v := AuthUserID(c); v > 0 {
		if IsAdmin(c) && hasQuery {
			return q
		}
		return v
	}
	if hasQuery {
		return q
	}
	return 0
}

func ForbiddenFor(c *gin.Context, ownerID int64) bool {
	uid := AuthUserID(c)
	return uid > 0 && uid != ownerID && !IsAdmin(c)
}

func Truncate(s string, max int) string {
	if utf8.RuneCountInString(s) <= max {
		return s
	}
	r := []rune(s)
	return string(r[:max])
}
