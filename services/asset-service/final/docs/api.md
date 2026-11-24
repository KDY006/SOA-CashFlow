# REST API — Asset Service Final

Base: `http://localhost:8083` (direct local), or `http://localhost:8080` (Gateway for `/api/...`). `Authorization: Bearer <token>` for all business endpoints. JSON snake_case; success returns direct object/array (not wrapper). Health public. GET/PUT/DELETE/PATCH may use `?user_id=...`; POST CRUD may use body `user_id` or query. Normal users may only target themselves; admin may target another user. No fallback user 1. Use Auth's JWT, not spoofed X-User-Id headers.

## Endpoints (23 business APIs + health)

| Method | URI | Request | Success response/status |
|---|---|---|---|
| GET | /api/assets | optional user_id | Asset[] 200 |
| GET | /api/assets/{id} | id | Asset 200 |
| POST | /api/assets | AssetInput | Asset 201 + Location |
| PUT | /api/assets/{id} | AssetInput | Asset 200 |
| DELETE | /api/assets/{id} | id | DeleteResponse 200 |
| POST | /api/assets/depreciation | DepreciationInput | DepreciationResult 200 |
| GET | /api/assets/net-worth | optional user_id | NetWorthResponse 200 |
| GET | /api/assets/{id}/history | id | HistoryResponse[] 200 |
| GET | /api/assets/{id}/valuation | id, optional as_of (YYYY-MM-DD) | ValuationResponse 200 |
| GET | /api/loans | optional user_id | Loan[] 200 |
| GET | /api/loans/{id} | id | Loan 200 |
| POST | /api/loans | LoanInput | Loan 201 + Location |
| PUT | /api/loans/{id} | LoanInput | Loan 200 |
| DELETE | /api/loans/{id} | id | DeleteResponse 200 |
| POST | /api/loans/calculate | LoanCalculationInput | LoanCalculationResult 200 |
| GET | /api/loans/{id}/schedule | id | LoanInstallment[] 200 |
| PATCH | /api/loans/{id}/status | LoanStatusInput | Loan 200 |
| GET | /api/investments | optional user_id | Investment[] 200 |
| GET | /api/investments/{id} | id | Investment 200 |
| POST | /api/investments | InvestmentInput | Investment 201 + Location |
| PUT | /api/investments/{id} | InvestmentInput | Investment 200 |
| DELETE | /api/investments/{id} | id | DeleteResponse 200 |
| GET | /api/investments/summary | optional user_id | PortfolioResponse 200 |
| GET | / or /health | none | service/status/version 200 |
| GET | /health/ready | none | status/database 200 or 503 |

## Request bodies (example)

**AssetInput** (currency defaults to VND; purchase_date/note nullable, useful_life_years may be 0 for non-depreciable asset):
```json
{"asset_name":"Laptop demo","asset_type":"DEVICE","purchase_price":12000000,"current_value":10000000,"salvage_value":2000000,"useful_life_years":4,"purchase_date":"2026-01-01","currency":"VND","note":"demo"}
```
**LoanInput** (start_date required, status optional ACTIVE/CLOSED, default ACTIVE):
```json
{"loan_title":"Demo loan","principal_amount":12000000,"interest_rate":12,"term_months":12,"start_date":"2026-01-01"}
```
**InvestmentInput** (quantity up to 4 decimal places, prices up to 2):
```json
{"symbol":"DEMO","investment_type":"STOCK","quantity":10,"buy_price":100000,"current_price":120000}
```
**DepreciationInput**: `{"purchase_price":12000000,"salvage_value":2000000,"useful_life_years":4}`.

**LoanCalculationInput**: `{"principal_amount":1000,"interest_rate":12,"term_months":12}`. Rate in percent per year, NOT fractional rate; request key remains `interest_rate` as in midterm.

**LoanStatusInput**: `{"status":"CLOSED"}`.

## Response fields

- Asset: id, user_id, asset_name, asset_type, purchase_price, current_value, salvage_value, useful_life_years, purchase_date, currency, note, created_at, updated_at.
- Loan: id, user_id, loan_title, principal_amount, interest_rate, term_months, start_date, status, created_at, updated_at.
- Investment: id, user_id, symbol, investment_type, quantity, buy_price, current_price, created_at, updated_at, cost_basis, market_value, profit_loss, roi (percent, null for zero cost).
- DeleteResponse: message, id.
- DepreciationResult: purchase_price, salvage_value, useful_life_years, annual_depreciation, monthly_depreciation.
- LoanCalculationResult: principal_amount, annual_interest_rate, term_months, monthly_payment, total_payment, total_interest.
- HistoryResponse: id, asset_id, value, recorded_at.
- ValuationResponse: asset_id, as_of, elapsed_years, estimated_book_value, currency, method.
- LoanInstallment: period, due_date, payment, principal, interest, remaining_balance.
- PortfolioResponse: user_id, total_cost, total_value, profit_loss, roi, currency.
- NetWorthResponse: user_id, total_net_worth, currency, total_assets, total_debt, investment_value, excluded_non_vnd_assets, basis.

Example calculate loan response:
```json
{"principal_amount":1000,"annual_interest_rate":12,"term_months":12,"monthly_payment":88.85,"total_payment":1066.19,"total_interest":66.19}
```
Example depreciation response:
```json
{"purchase_price":12000000,"salvage_value":2000000,"useful_life_years":4,"annual_depreciation":2500000,"monthly_depreciation":208333.33}
```

## Errors

| HTTP | Meaning |
|---|---|
| 400 | invalid JSON/model, invalid bounds/precision, incompatible dates or user_id |
| 401 | missing/invalid/expired JWT or missing user identity |
| 403 | normal user tries to target another user |
| 404 | endpoint or owned record not found |
| 405 | unsupported HTTP method |
| 409 | conflicting concurrent EF update/delete |
| 500 | internal/database error; details logged server-side |
| 503 | readiness check cannot connect to DB |

`{"error":"Validation failed","details":{"AssetName":["The AssetName field is required."]}}` is an example validation error. Business errors include `error` and `trace_id`. Routing errors (404/405) may have empty bodies. Gateway may return its own error schema. Swagger lists API models; examples above and Postman aid demo.
