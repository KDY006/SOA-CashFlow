FROM golang:1.21-alpine AS builder

WORKDIR /app
COPY go.mod ./
# COPY go.sum ./
# RUN go mod download || true

COPY src/ ./src/
# RUN go build -o main ./src || true

FROM alpine:3.18
WORKDIR /app
# COPY --from=builder /app/main .
EXPOSE 8085
CMD ["echo", "Integration Service (Go) started on port 8085"]
