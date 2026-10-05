CREATE TABLE transactions (
    id                VARCHAR(36) PRIMARY KEY,
    user_id           VARCHAR(100) NOT NULL,
    tuition_record_id VARCHAR(100) NOT NULL,
    amount            NUMERIC(14, 2) NOT NULL CHECK (amount > 0),
    status            VARCHAR(20) NOT NULL
                      CHECK (status IN ('PENDING', 'OTP_PENDING', 'SUCCEEDED', 'FAILED')),
    idempotency_key   VARCHAR(100) NOT NULL UNIQUE,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_transactions_user_id ON transactions(user_id);
CREATE INDEX idx_transactions_tuition_record_id ON transactions(tuition_record_id);
CREATE INDEX idx_transactions_created_at ON transactions(created_at DESC);
