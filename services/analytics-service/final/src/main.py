"""Analytics Service cuoi ky: FastAPI nhan request va tong hop du lieu tai chinh."""
from __future__ import annotations

import json
import os
import statistics
import time
from collections import defaultdict
from contextlib import asynccontextmanager
from datetime import date, datetime, timezone
from typing import Any

import pymysql
import requests
from fastapi import FastAPI, Header, Query, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, ConfigDict, Field
from pymysql.cursors import DictCursor

# Cau hinh lay tu moi truong de cung mot image chay duoc o local va Docker Compose.
SERVICE_NAME = "analytics-service"
VERSION = "2.0.0-final"
TRANSACTION_SERVICE_URL = os.getenv("TRANSACTION_SERVICE_URL", "http://transaction-service:8082").rstrip("/")
DB_HOST = os.getenv("ANALYTICS_DB_HOST", "analytics-db")
DB_PORT = int(os.getenv("ANALYTICS_DB_PORT", "3306"))
DB_NAME = os.getenv("ANALYTICS_DB_NAME", "analytics_db")
DB_USER = os.getenv("ANALYTICS_DB_USER", "root")
DB_PASSWORD = os.getenv("ANALYTICS_DB_PASSWORD", "root_password")


# Loi nghiep vu co ma HTTP va ma loi rieng, dung de API tra JSON thong nhat.
class AnalyticsError(Exception):
    def __init__(self, status_code: int, code: str, message: str):
        self.status_code = status_code
        self.code = code
        self.message = message


# Pydantic kiem tra du lieu giao dich gui truc tiep vao API aggregate.
class TransactionInput(BaseModel):
    model_config = ConfigDict(extra="ignore")
    id: int | str | None = None
    user_id: int | None = None
    amount: float = Field(ge=0)
    type: str = "EXPENSE"
    category: str | None = None
    category_name_rel: str | None = None
    transaction_date: str | date | datetime | None = None
    date: str | date | datetime | None = None


# Request tao bao cao: co the cung cap giao dich hoac de service tu goi Transaction Service.
class AggregateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")
    user_id: int | None = Field(default=None, ge=1)
    from_date: date | None = None
    to_date: date | None = None
    transactions: list[TransactionInput] | None = None


# Moi thao tac mo ket noi rieng toi database analytics_db (database cua service nay).
def _connect():
    return pymysql.connect(host=DB_HOST, port=DB_PORT, database=DB_NAME,
                           user=DB_USER, password=DB_PASSWORD, charset="utf8mb4",
                           cursorclass=DictCursor, connect_timeout=10, autocommit=False)


# Tao bang tu file SQL dung chung; thu lai vi MySQL co the khoi dong cham hon app.
def _init_database() -> None:
    schema_path = os.getenv("ANALYTICS_DB_SCHEMA", "/app/db/init-analytics-db.sql")
    with open(schema_path, "r", encoding="utf-8") as file:
        schema = file.read()
    last_error: Exception | None = None
    for attempt in range(30):
        connection = None
        try:
            connection = _connect()
            with connection.cursor() as cursor:
                for statement in schema.split(";"):
                    if statement.strip():
                        cursor.execute(statement)
            connection.commit()
            return
        except pymysql.MySQLError as exc:
            last_error = exc
            if attempt == 29:
                break
            time.sleep(2)
        finally:
            if connection:
                connection.close()
    raise RuntimeError(f"Không kết nối/khởi tạo được MySQL {DB_HOST}:{DB_PORT}/{DB_NAME}: {last_error}")


# FastAPI chay ham nay khi khoi dong; API chi san sang sau khi schema duoc nap.
@asynccontextmanager
async def lifespan(_: FastAPI):
    _init_database()
    yield


# Khoi tao ung dung FastAPI; tai lieu Swagger tu sinh tai /docs.
app = FastAPI(title="SOA CashFlow Analytics Service", version=VERSION,
              description="Phân tích dòng tiền, xu hướng, chi tiêu bất thường và lưu báo cáo tháng.",
              lifespan=lifespan)
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["GET", "POST", "OPTIONS"],
                   allow_headers=["Authorization", "Content-Type", "X-User-Id", "X-User-Role"])


# Chuyen loi nghiep vu thanh JSON de doc thay vi tra traceback cho nguoi goi.
@app.exception_handler(AnalyticsError)
async def analytics_error_handler(_: Request, exc: AnalyticsError):
    return JSONResponse(status_code=exc.status_code, content={"error": exc.code, "message": exc.message})


# Dua loi kiem tra dau vao cua FastAPI ve cung dinh dang loi cua API giua ky.
@app.exception_handler(RequestValidationError)
async def validation_error_handler(_: Request, exc: RequestValidationError):
    details = exc.errors()
    message = "; ".join(f"{'/'.join(str(x) for x in item.get('loc', ())) }: {item.get('msg', 'giá trị không hợp lệ')}" for item in details)
    return JSONResponse(status_code=400, content={"error": "VALIDATION_ERROR", "message": message})


# Gateway chuyen X-User-Id tu JWT xuong. USER luon dung ID trong header;
# chi ADMIN moi duoc yeu cau xem user_id khac.
def _resolve_user_id(requested: int | None, x_user_id: str | None, x_user_role: str | None) -> int:
    header_id = (x_user_id or "").strip()
    is_admin = (x_user_role or "").strip().upper() == "ADMIN"
    try:
        if header_id and not (is_admin and requested is not None):
            user_id = int(header_id)
        elif requested is not None:
            user_id = int(requested)
        else:
            user_id = 0
    except ValueError as exc:
        raise AnalyticsError(400, "VALIDATION_ERROR", "X-User-Id/user_id phải là số nguyên") from exc
    if user_id <= 0:
        raise AnalyticsError(400, "VALIDATION_ERROR", "user_id phải là số nguyên dương")
    return user_id


# Chuan hoa ten truong/ngay/loai giao dich de cac phep tinh dung cung mot cau truc.
def _normalise(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    result = []
    for index, row in enumerate(rows):
        try:
            amount = float(row.get("amount", 0))
        except (TypeError, ValueError) as exc:
            raise AnalyticsError(400, "VALIDATION_ERROR", f"transactions[{index}].amount không hợp lệ") from exc
        if amount < 0:
            raise AnalyticsError(400, "VALIDATION_ERROR", f"transactions[{index}].amount không được âm")
        kind = str(row.get("type", "EXPENSE")).upper()
        if kind not in {"INCOME", "EXPENSE", "TRANSFER"}:
            continue
        raw_date = row.get("transaction_date") or row.get("date")
        try:
            tx_date = raw_date if isinstance(raw_date, date) else datetime.fromisoformat(str(raw_date).replace("Z", "+00:00")).date()
            if isinstance(raw_date, datetime):
                tx_date = raw_date.date()
        except (TypeError, ValueError):
            continue
        result.append({"id": row.get("id"), "user_id": row.get("user_id"), "amount": amount,
                       "type": kind, "category": str(row.get("category") or row.get("category_name_rel") or "Khác"),
                       "transaction_date": tx_date.isoformat()})
    return result


# Giao tiep qua HTTP voi Transaction Service, khong doc truc tiep database cua service do.
# Phan trang 100 dong/lan de tuong thich voi gioi han validation cua ca Midterm va Laravel Final.
def _fetch_transactions(user_id: int, start: date | None, end: date | None) -> list[dict[str, Any]]:
    limit, offset, rows = 100, 0, []
    while True:
        params: dict[str, Any] = {"user_id": user_id, "limit": limit, "offset": offset}
        if start:
            params["from_date"] = start.isoformat()
        if end:
            params["to_date"] = end.isoformat()
        try:
            response = requests.get(f"{TRANSACTION_SERVICE_URL}/api/transactions", params=params,
                                    headers={"Accept": "application/json", "X-User-Id": str(user_id)}, timeout=10)
            response.raise_for_status()
            payload = response.json()
        except (requests.RequestException, ValueError) as exc:
            raise AnalyticsError(502, "UPSTREAM_ERROR", f"Không lấy được dữ liệu Transaction Service: {exc}") from exc
        page = payload.get("data", payload) if isinstance(payload, dict) else payload
        if not isinstance(page, list):
            raise AnalyticsError(502, "UPSTREAM_ERROR", "Transaction Service trả về dữ liệu không đúng định dạng")
        rows.extend(_normalise(page))
        if len(page) < limit:
            return rows
        offset += len(page)
        if offset >= 100_000:
            raise AnalyticsError(502, "UPSTREAM_ERROR", "Transaction Service trả về quá 100000 giao dịch cho một lần phân tích")


# Loc lai o Analytics de thong nhat khoang ngay bao gom ca hai dau mut.
def _in_period(rows: list[dict[str, Any]], start: date | None, end: date | None) -> list[dict[str, Any]]:
    return [row for row in rows if (start is None or date.fromisoformat(row["transaction_date"]) >= start)
            and (end is None or date.fromisoformat(row["transaction_date"]) <= end)]


# Thu - chi = dong tien rong; giao dich chuyen khoan khong tinh vao hai tong nay.
def _summary(rows: list[dict[str, Any]]) -> dict[str, Any]:
    income = sum(row["amount"] for row in rows if row["type"] == "INCOME")
    expense = sum(row["amount"] for row in rows if row["type"] == "EXPENSE")
    return {"transaction_count": len(rows), "income": round(income, 2), "expense": round(expense, 2),
            "net_cashflow": round(income - expense, 2), "currency": "VND"}


# Gom cac khoan EXPENSE theo danh muc, sau do tinh ty le tren tong chi tieu.
def _category_breakdown(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    totals: dict[str, float] = defaultdict(float)
    for row in rows:
        if row["type"] == "EXPENSE":
            totals[row["category"]] += row["amount"]
    total = sum(totals.values())
    return [{"category": category, "amount": round(amount, 2),
             "percentage": round(amount * 100 / total, 2) if total else 0.0}
            for category, amount in sorted(totals.items(), key=lambda item: (-item[1], item[0]))]


# Gom theo thang; du bao thang toi bang trung binh dong tien rong toi da 3 thang gan nhat.
def _trends(rows: list[dict[str, Any]], months: int) -> dict[str, Any]:
    monthly: dict[str, dict[str, float]] = defaultdict(lambda: {"income": 0.0, "expense": 0.0})
    for row in rows:
        period = row["transaction_date"][:7]
        if row["type"] in {"INCOME", "EXPENSE"}:
            monthly[period][row["type"].lower()] += row["amount"]
    history = []
    for period in sorted(monthly)[-months:]:
        values = monthly[period]
        history.append({"month": period, "income": round(values["income"], 2), "expense": round(values["expense"], 2),
                        "net_cashflow": round(values["income"] - values["expense"], 2)})
    nets = [item["net_cashflow"] for item in history]
    forecast = round(sum(nets[-3:]) / len(nets[-3:]), 2) if nets else 0.0
    return {"history": history, "forecast": {"method": "trailing_average_3_months",
            "next_month_net_cashflow": forecast, "based_on_months": min(3, len(nets))}}


# Tim khoan chi lech cao theo tung danh muc bang median va MAD; can toi thieu 4 mau.
def _anomalies(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    by_category: dict[str, list[float]] = defaultdict(list)
    for row in rows:
        if row["type"] == "EXPENSE":
            by_category[row["category"]].append(row["amount"])
    flagged = []
    for row in rows:
        if row["type"] != "EXPENSE":
            continue
        amounts = by_category[row["category"]]
        if len(amounts) < 4:
            continue
        median = statistics.median(amounts)
        mad = statistics.median([abs(value - median) for value in amounts])
        threshold = median + 3 * 1.4826 * mad if mad else median * 2
        if row["amount"] > threshold and row["amount"] > median:
            flagged.append({"transaction_id": row["id"], "category": row["category"], "amount": row["amount"],
                            "category_median": round(median, 2), "threshold": round(threshold, 2),
                            "transaction_date": row["transaction_date"]})
    return flagged


# Luu bao cao theo user/nam/thang; unique key giup lan chay sau cap nhat thay vi nhan ban.
def _save_report(user_id: int, report: dict[str, Any]) -> None:
    connection = None
    try:
        connection = _connect()
        with connection.cursor() as cursor:
            cursor.execute("""INSERT INTO monthly_reports (user_id, year, month, report_json, generated_at)
                VALUES (%s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE report_json=VALUES(report_json),
                generated_at=VALUES(generated_at), updated_at=CURRENT_TIMESTAMP""",
                (user_id, report["year"], report["month"], json.dumps(report, ensure_ascii=False),
                 datetime.fromisoformat(report["generated_at"].replace("Z", "+00:00")).replace(tzinfo=None)))
        connection.commit()
    except pymysql.MySQLError as exc:
        if connection:
            connection.rollback()
        raise AnalyticsError(502, "DATA_SOURCE_ERROR", f"Không lưu được báo cáo vào MySQL: {exc}") from exc
    finally:
        if connection:
            connection.close()


# Doc bao cao da luu, luon gioi han toi da 100 ban ghi gan nhat.
def _load_reports(user_id: int, year: int | None, month: int | None) -> list[dict[str, Any]]:
    connection = None
    clauses, params = ["user_id=%s"], [user_id]
    if year is not None:
        clauses.append("year=%s")
        params.append(year)
    if month is not None:
        clauses.append("month=%s")
        params.append(month)
    try:
        connection = _connect()
        with connection.cursor() as cursor:
            cursor.execute("SELECT report_json FROM monthly_reports WHERE " + " AND ".join(clauses)
                           + " ORDER BY year DESC, month DESC LIMIT 100", params)
            result = cursor.fetchall()
        return [item["report_json"] if isinstance(item["report_json"], dict) else json.loads(item["report_json"])
                for item in result]
    except (pymysql.MySQLError, json.JSONDecodeError) as exc:
        raise AnalyticsError(502, "DATA_SOURCE_ERROR", f"Không đọc được báo cáo từ MySQL: {exc}") from exc
    finally:
        if connection:
            connection.close()


# Chon nguon giao dich: dung body neu demo doc lap, neu khong thi goi service ben Transaction.
def _transaction_rows(user_id: int, start: date | None, end: date | None,
                      transactions: list[TransactionInput] | None = None) -> list[dict[str, Any]]:
    if transactions is None:
        rows = _fetch_transactions(user_id, start, end)
    else:
        rows = _normalise([item.model_dump(mode="json", exclude_none=True) for item in transactions])
    return _in_period(rows, start, end)


@app.get("/", include_in_schema=False)
@app.get("/health", tags=["Health"])
def health():
    # Health check nhe de Docker/Gateway xac nhan service dang hoat dong.
    return {"service": SERVICE_NAME, "status": "UP", "version": VERSION}


@app.get("/api/analytics/summary", tags=["Analytics"])
def get_summary(from_date: date | None = None, to_date: date | None = None,
                user_id: int | None = Query(default=None, ge=1),
                x_user_id: str | None = Header(default=None), x_user_role: str | None = Header(default=None)):
    # Tong quan thu, chi va so giao dich trong khoang thoi gian duoc chon.
    uid = _resolve_user_id(user_id, x_user_id, x_user_role)
    if from_date and to_date and from_date > to_date:
        raise AnalyticsError(400, "VALIDATION_ERROR", "from_date phải trước hoặc bằng to_date")
    return {"data": _summary(_transaction_rows(uid, from_date, to_date)),
            "meta": {"user_id": uid, "source": "transaction-service"}}


@app.get("/api/analytics/category-breakdown", tags=["Analytics"])
def get_category_breakdown(from_date: date | None = None, to_date: date | None = None,
                           user_id: int | None = Query(default=None, ge=1),
                           x_user_id: str | None = Header(default=None), x_user_role: str | None = Header(default=None)):
    # Phan bo cac khoan chi theo danh muc de hien thi bieu do co cau chi tieu.
    uid = _resolve_user_id(user_id, x_user_id, x_user_role)
    if from_date and to_date and from_date > to_date:
        raise AnalyticsError(400, "VALIDATION_ERROR", "from_date phải trước hoặc bằng to_date")
    rows = _transaction_rows(uid, from_date, to_date)
    return {"data": _category_breakdown(rows), "meta": {"user_id": uid, "source": "transaction-service"}}


@app.get("/api/analytics/trends", tags=["Analytics"])
def get_trends(months: int = Query(default=6, ge=1, le=36), from_date: date | None = None,
               to_date: date | None = None, user_id: int | None = Query(default=None, ge=1),
               x_user_id: str | None = Header(default=None), x_user_role: str | None = Header(default=None)):
    # Lich su dong tien va du bao; anomalies duoc kem de dashboard goi mot lan.
    uid = _resolve_user_id(user_id, x_user_id, x_user_role)
    if from_date and to_date and from_date > to_date:
        raise AnalyticsError(400, "VALIDATION_ERROR", "from_date phải trước hoặc bằng to_date")
    rows = _transaction_rows(uid, from_date, to_date)
    data = _trends(rows, months)
    data["anomalies"] = _anomalies(rows)
    return {"data": data, "meta": {"user_id": uid, "source": "transaction-service"}}


@app.get("/api/analytics/anomalies", tags=["Analytics"])
def get_anomalies(from_date: date | None = None, to_date: date | None = None,
                  user_id: int | None = Query(default=None, ge=1),
                  x_user_id: str | None = Header(default=None), x_user_role: str | None = Header(default=None)):
    # Endpoint rieng de lay bat thuong ma khong can tai du lieu trends.
    uid = _resolve_user_id(user_id, x_user_id, x_user_role)
    if from_date and to_date and from_date > to_date:
        raise AnalyticsError(400, "VALIDATION_ERROR", "from_date phải trước hoặc bằng to_date")
    return {"data": _anomalies(_transaction_rows(uid, from_date, to_date)),
            "meta": {"user_id": uid, "source": "transaction-service"}}


@app.get("/api/analytics/reports", tags=["Analytics"])
def get_reports(user_id: int | None = Query(default=None, ge=1), year: int | None = Query(default=None, ge=2000, le=2100),
                month: int | None = Query(default=None, ge=1, le=12),
                x_user_id: str | None = Header(default=None), x_user_role: str | None = Header(default=None)):
    # Truy van cac bao cao da aggregate truoc do trong MySQL.
    uid = _resolve_user_id(user_id, x_user_id, x_user_role)
    return {"data": _load_reports(uid, year, month), "meta": {"user_id": uid, "limit": 100}}


@app.post("/api/analytics/aggregate", status_code=201, tags=["Analytics"])
def aggregate(body: AggregateRequest, x_user_id: str | None = Header(default=None),
              x_user_role: str | None = Header(default=None)):
    # Tao bao cao ky theo to_date (hoac thang hien tai) roi upsert xuong MySQL.
    uid = _resolve_user_id(body.user_id, x_user_id, x_user_role)
    if body.from_date and body.to_date and body.from_date > body.to_date:
        raise AnalyticsError(400, "VALIDATION_ERROR", "from_date phải trước hoặc bằng to_date")
    rows = _transaction_rows(uid, body.from_date, body.to_date, body.transactions)
    report = _summary(rows)
    period_date = body.to_date or date.today()
    report.update({"user_id": uid, "year": period_date.year, "month": period_date.month,
                   "category_breakdown": _category_breakdown(rows), "anomalies": _anomalies(rows),
                   "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")})
    _save_report(uid, report)
    return {"data": report, "meta": {"stored": True, "database": "mysql"}}


