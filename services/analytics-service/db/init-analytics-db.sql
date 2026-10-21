CREATE TABLE IF NOT EXISTS monthly_reports (
    report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    year SMALLINT NOT NULL,
    month TINYINT NOT NULL,
    report_json JSON NOT NULL,
    generated_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    UNIQUE KEY uq_monthly_reports_user_period (user_id, year, month),
    KEY idx_monthly_reports_user_period (user_id, year, month),
    CONSTRAINT chk_monthly_reports_year CHECK (year BETWEEN 2000 AND 2100),
    CONSTRAINT chk_monthly_reports_month CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
