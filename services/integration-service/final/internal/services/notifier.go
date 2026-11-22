package services

import (
	"bytes"
	"context"
	"encoding/json"
	"log"
	"net/http"
	"time"
)

type Notifier struct {
	url    string
	client *http.Client
}

func NewNotifier(url string, client *http.Client) *Notifier {
	return &Notifier{url: url, client: client}
}

func (n *Notifier) Send(userID int64, title, message, typ string, meta any) {
	go func() {
		body, _ := json.Marshal(map[string]any{
			"userId":   userID,
			"title":    title,
			"message":  message,
			"type":     typ,
			"metadata": meta,
		})

		ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
		defer cancel()
		req, err := http.NewRequestWithContext(ctx, http.MethodPost, n.url+"/api/notifications/send", bytes.NewReader(body))
		if err != nil {
			return
		}
		req.Header.Set("Content-Type", "application/json")

		resp, err := n.client.Do(req)
		if err != nil {
			log.Printf("không gửi được thông báo cho user %d: %v", userID, err)
			return
		}
		resp.Body.Close()
		if resp.StatusCode/100 != 2 {
			log.Printf("notification-service trả về %d", resp.StatusCode)
		}
	}()
}
