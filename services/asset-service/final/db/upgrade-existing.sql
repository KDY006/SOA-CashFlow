-- Run only against an EXISTING final volume after backup and reviewing invalid rows.
-- Fresh final volumes already have these constraints; do not run this twice.
USE asset_db;
ALTER TABLE assets ADD CONSTRAINT ck_assets_values CHECK (user_id > 0 AND purchase_price >= 0 AND current_value >= 0 AND salvage_value >= 0 AND salvage_value <= purchase_price AND useful_life_years BETWEEN 0 AND 1000);
ALTER TABLE loans ADD CONSTRAINT ck_loans_values CHECK (user_id > 0 AND principal_amount > 0 AND interest_rate BETWEEN 0 AND 999.99 AND term_months BETWEEN 1 AND 1200 AND status IN ('ACTIVE','CLOSED'));
ALTER TABLE investments ADD CONSTRAINT ck_investments_values CHECK (user_id > 0 AND quantity > 0 AND buy_price >= 0 AND current_price >= 0);
ALTER TABLE asset_history ADD CONSTRAINT ck_history_value CHECK (value >= 0), ADD INDEX idx_asset_history_date (asset_id, recorded_at);
INSERT INTO asset_history (asset_id,value,recorded_at)
SELECT a.id,a.current_value,CURRENT_TIMESTAMP FROM assets a
WHERE NOT EXISTS (SELECT 1 FROM asset_history h WHERE h.asset_id=a.id);
