package middleware

import (
	"net/http"

	"cashflow/integration-service-final/internal/pkg/httpx"
	"github.com/gin-gonic/gin"
)

func CORS() gin.HandlerFunc {
	return func(c *gin.Context) {
		h := c.Writer.Header()
		h.Set("Access-Control-Allow-Origin", "*")
		h.Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		h.Set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-User-Id, X-User-Role, Idempotency-Key, X-Signature")
		if c.Request.Method == http.MethodOptions {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}
		c.Next()
	}
}

func MaxBodySize(limit int64) gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Request.Body = http.MaxBytesReader(c.Writer, c.Request.Body, limit)
		c.Next()
	}
}

func HandleNoRoute(c *gin.Context) {
	httpx.Fail(c, http.StatusNotFound, "NOT_FOUND",
		"không có đường dẫn "+c.Request.Method+" "+c.Request.URL.Path+" trên integration-service")
}

func HandleNoMethod(c *gin.Context) {
	httpx.Fail(c, http.StatusMethodNotAllowed, "METHOD_NOT_ALLOWED",
		c.Request.URL.Path+" không hỗ trợ method "+c.Request.Method)
}
