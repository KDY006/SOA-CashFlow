# ERD - Integration Service (Go)

Database: `integration_db` (MySQL), chỉ integration-service được đọc ghi.

```mermaid
erDiagram
    LINKED_ACCOUNTS ||--o{ BANK_TRANSACTIONS : "có nhiều biến động"

    LINKED_ACCOUNTS {
        bigint id PK
        bigint user_id "tham chiếu logic sang auth-service"
        varchar bank_code "VCB, TCB, MOMO..."
        varchar account_no
        varchar holder_name
        decimal balance "CHECK >= 0"
        bigint version "tăng mỗi lần đổi số dư"
        timestamp created_at
        timestamp updated_at
    }

    BANK_TRANSACTIONS {
        bigint id PK
        bigint account_id FK
        varchar provider
        varchar external_ref "mã giao dịch phía ngân hàng"
        varchar idempotency_key UK
        char request_hash "sha256 nội dung"
        enum direction "CREDIT | DEBIT"
        decimal amount "CHECK > 0"
        decimal balance_after
        varchar category
        varchar description
        datetime occurred_at
        enum status "RECEIVED | SYNCING | SYNCED | FAILED | REJECTED"
        bigint transaction_ref "id bên transaction-service"
        int sync_attempts
        varchar last_error
        timestamp created_at
        timestamp updated_at
    }

    EXCHANGE_RATES {
        int id PK
        varchar currency_code UK
        decimal rate_to_vnd "CHECK > 0"
        timestamp updated_at
    }

    EXTERNAL_WEBHOOKS {
        bigint id PK
        varchar provider_name
        varchar event_type
        json payload
        timestamp received_at
    }
```

## Ràng buộc

| Bảng | Ràng buộc | Mục đích |
|---|---|---|
| `linked_accounts` | `UNIQUE (bank_code, account_no)` | 1 tài khoản ngân hàng chỉ liên kết 1 lần |
| `linked_accounts` | `CHECK (balance >= 0)` | db tự chặn nếu code có lỗi làm âm số dư |
| `bank_transactions` | `UNIQUE (idempotency_key)` | chống ghi trùng theo key |
| `bank_transactions` | `UNIQUE (provider, external_ref)` | chống ghi trùng theo mã giao dịch ngân hàng, kể cả khi client đổi key |
| `bank_transactions` | `FK account_id -> linked_accounts(id) ON DELETE CASCADE` | |
| `bank_transactions` | `INDEX (status, updated_at)` | sweeper tìm nhanh giao dịch cần thử lại |
| `bank_transactions` | `INDEX (account_id, occurred_at)` | xem lịch sử theo tài khoản |

## Liên kết với service khác

- `linked_accounts.user_id` -> `users.id` bên **auth-service** (không đặt khóa ngoại vì khác database)
- `bank_transactions.transaction_ref` -> `transactions.id` bên **transaction-service**, có giá trị khi đã `SYNCED`
- `external_webhooks` và `exchange_rates` đứng độc lập
