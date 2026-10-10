FROM golang:1.22-alpine AS builder

WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download

COPY src/ ./src/
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /app/integration-service ./src

FROM alpine:3.19
RUN apk add --no-cache tzdata ca-certificates
ENV TZ=Asia/Ho_Chi_Minh
WORKDIR /app
COPY --from=builder /app/integration-service .

EXPOSE 8085
CMD ["./integration-service"]
