"""Native Python analytics API for SOA CashFlow midterm (stdlib HTTP/urllib)."""
from __future__ import annotations

import json
import os
import statistics
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import date, datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

HOST = os.getenv("HOST", "0.0.0.0")
PORT = int(os.getenv("PORT", "8084"))
TRANSACTION_SERVICE_URL = os.getenv("TRANSACTION_SERVICE_URL", "http://transaction-service:8082")
MONGO_URI = os.getenv("MONGO_URI", "")


def _date(value: Any) -> date | None:
    if not value:
        return None
    if isinstance(value, datetime):
        return value.date()
    if isinstance(value, date):
        return value
    try:
        return datetime.fromisoformat(str(value).replace("Z", "+00:00")).date()
    except ValueError:
        return None


def _normalise(rows: Any) -> list[dict[str, Any]]:
    if not isinstance(rows, list):
        raise ValueError("transactions phải là một mảng")
    result = []
    for index, row in enumerate(rows):
        if not isinstance(row, dict):
            raise ValueError(f"transactions[{index}] phải là object")
        try:
            amount = float(row.get("amount", 0))
        except (TypeError, ValueError):
            raise ValueError(f"transactions[{index}].amount không hợp lệ")
        if amount < 0:
            raise ValueError(f"transactions[{index}].amount không được âm")
        kind = str(row.get("type", "EXPENSE")).upper()
        if kind not in {"INCOME", "EXPENSE", "TRANSFER"}:
            continue
        tx_date = _date(row.get("transaction_date") or row.get("date"))
        if tx_date is None:
            continue
        result.append({
            "id": row.get("id"), "user_id": row.get("user_id"),
            "amount": amount, "type": kind,
            "category": str(row.get("category") or row.get("category_name_rel") or "Khác"),
            "transaction_date": tx_date.isoformat(),
        })
    return result


def _fetch_transactions(user_id: int, start: date | None, end: date | None) -> list[dict[str, Any]]:
    params: dict[str, str] = {"user_id": str(user_id), "limit": "1000"}
    if start:
        params["from_date"] = start.isoformat() + " 00:00:00"
    if end:
        params["to_date"] = end.isoformat() + " 23:59:59"
    url = TRANSACTION_SERVICE_URL.rstrip("/") + "/api/transactions?" + urllib.parse.urlencode(params)
    request = urllib.request.Request(url, headers={"Accept": "application/json", "X-User-Id": str(user_id)})
    try:
        with urllib.request.urlopen(request, timeout=8) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as exc:
        raise RuntimeError(f"Không lấy được dữ liệu Transaction Service: {exc}")
    rows = payload.get("data", payload) if isinstance(payload, dict) else payload
    return _normalise(rows)


def _period(rows: list[dict[str, Any]], start: date | None, end: date | None) -> list[dict[str, Any]]:
    return [r for r in rows if (start is None or date.fromisoformat(r["transaction_date"]) >= start)
            and (end is None or date.fromisoformat(r["transaction_date"]) <= end)]


def summary(rows: list[dict[str, Any]]) -> dict[str, Any]:
    income = sum(r["amount"] for r in rows if r["type"] == "INCOME")
    expense = sum(r["amount"] for r in rows if r["type"] == "EXPENSE")
    return {"transaction_count": len(rows), "income": round(income, 2),
            "expense": round(expense, 2), "net_cashflow": round(income-expense, 2),
            "currency": "VND"}


def category_breakdown(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    groups: dict[str, float] = defaultdict(float)
    total = 0.0
    for row in rows:
        if row["type"] == "EXPENSE":
            groups[row["category"]] += row["amount"]
            total += row["amount"]
    return [{"category": name, "amount": round(amount, 2),
             "percentage": round(amount * 100 / total, 2) if total else 0.0}
            for name, amount in sorted(groups.items(), key=lambda item: (-item[1], item[0]))]


def trends(rows: list[dict[str, Any]], months: int = 6) -> dict[str, Any]:
    monthly: dict[str, dict[str, float]] = defaultdict(lambda: {"income": 0.0, "expense": 0.0})
    for row in rows:
        month = row["transaction_date"][:7]
        if row["type"] in {"INCOME", "EXPENSE"}:
            monthly[month][row["type"].lower()] += row["amount"]
    history = []
    for month in sorted(monthly)[-months:]:
        item = monthly[month]
        history.append({"month": month, "income": round(item["income"], 2),
                        "expense": round(item["expense"], 2),
                        "net_cashflow": round(item["income"]-item["expense"], 2)})
    nets = [item["net_cashflow"] for item in history]
    forecast = round(sum(nets[-3:]) / len(nets[-3:]), 2) if nets else 0.0
    return {"history": history, "forecast": {"method": "trailing_average_3_months",
            "next_month_net_cashflow": forecast, "based_on_months": min(3, len(nets))}}


def anomalies(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    by_category: dict[str, list[float]] = defaultdict(list)
    for row in rows:
        if row["type"] == "EXPENSE":
            by_category[row["category"]].append(row["amount"])
    flagged = []
    for row in rows:
        if row["type"] != "EXPENSE":
            continue
        values = by_category[row["category"]]
        if len(values) < 4:
            continue
        median = statistics.median(values)
        deviations = [abs(value-median) for value in values]
        mad = statistics.median(deviations)
        threshold = median + 3 * 1.4826 * mad if mad else median * 2
        if row["amount"] > threshold and row["amount"] > median:
            flagged.append({"transaction_id": row["id"], "category": row["category"],
                            "amount": row["amount"], "category_median": round(median, 2),
                            "threshold": round(threshold, 2),
                            "transaction_date": row["transaction_date"]})
    return flagged


def _save_report(user_id: int, report: dict[str, Any]) -> None:
    if not MONGO_URI:
        return
    try:
        from pymongo import MongoClient
        client = MongoClient(MONGO_URI, serverSelectionTimeoutMS=1500)
        client.analytics_db.monthly_reports.update_one(
            {"user_id": user_id, "year": report["year"], "month": report["month"]},
            {"$set": report}, upsert=True)
        client.close()
    except Exception as exc:
        raise RuntimeError(f"Không lưu được báo cáo vào MongoDB: {exc}")


class AnalyticsHandler(BaseHTTPRequestHandler):
    server_version = "CashFlowAnalyticsNative/1.0"

    def _send(self, status: int, payload: dict[str, Any]) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-User-Id")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.end_headers()
        self.wfile.write(body)

    def _body(self) -> dict[str, Any]:
        length = int(self.headers.get("Content-Length", "0"))
        if length > 1_000_000:
            raise ValueError("Body vượt quá 1 MB")
        value = json.loads(self.rfile.read(length) or b"{}")
        if not isinstance(value, dict):
            raise ValueError("Body phải là JSON object")
        return value

    def do_OPTIONS(self) -> None:
        self._send(204, {})

    def do_GET(self) -> None:
        parsed = urllib.parse.urlsplit(self.path)
        path = parsed.path.rstrip("/") or "/"
        query = urllib.parse.parse_qs(parsed.query)
        if path in {"/", "/health"}:
            return self._send(200, {"service": "analytics-service", "status": "UP", "version": "1.0.0-midterm"})
        if path not in {"/api/analytics/summary", "/api/analytics/trends", "/api/analytics/category-breakdown"}:
            return self._send(404, {"error": "NOT_FOUND", "message": "Endpoint không tồn tại"})
        try:
            user_id = int((query.get("user_id") or [self.headers.get("X-User-Id", "")])[0])
            if user_id <= 0:
                raise ValueError("user_id phải là số nguyên dương")
            start = _date((query.get("from_date") or [None])[0])
            end = _date((query.get("to_date") or [None])[0])
            if start and end and start > end:
                raise ValueError("from_date phải trước hoặc bằng to_date")
            rows = _period(_fetch_transactions(user_id, start, end), start, end)
            if path.endswith("/summary"):
                data = summary(rows)
            elif path.endswith("/trends"):
                months = int((query.get("months") or ["6"])[0])
                if not 1 <= months <= 36:
                    raise ValueError("months phải trong khoảng 1..36")
                data = trends(rows, months)
                data["anomalies"] = anomalies(rows)
            else:
                data = category_breakdown(rows)
            return self._send(200, {"data": data, "meta": {"user_id": user_id, "source": "transaction-service"}})
        except ValueError as exc:
            return self._send(400, {"error": "VALIDATION_ERROR", "message": str(exc)})
        except RuntimeError as exc:
            return self._send(502, {"error": "UPSTREAM_ERROR", "message": str(exc)})

    def do_POST(self) -> None:
        if urllib.parse.urlsplit(self.path).path.rstrip("/") != "/api/analytics/aggregate":
            return self._send(404, {"error": "NOT_FOUND", "message": "Endpoint không tồn tại"})
        try:
            body = self._body()
            user_id = int(body.get("user_id", self.headers.get("X-User-Id", "0")))
            if user_id <= 0:
                raise ValueError("user_id phải là số nguyên dương")
            start, end = _date(body.get("from_date")), _date(body.get("to_date"))
            if start and end and start > end:
                raise ValueError("from_date phải trước hoặc bằng to_date")
            rows = _normalise(body["transactions"]) if "transactions" in body else _fetch_transactions(user_id, start, end)
            rows = _period(rows, start, end)
            report = summary(rows)
            report.update({"user_id": user_id, "year": end.year if end else date.today().year,
                           "month": end.month if end else date.today().month,
                           "category_breakdown": category_breakdown(rows),
                           "anomalies": anomalies(rows), "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z"})
            _save_report(user_id, report)
            return self._send(201, {"data": report, "meta": {"stored": bool(MONGO_URI)}})
        except (ValueError, json.JSONDecodeError) as exc:
            return self._send(400, {"error": "VALIDATION_ERROR", "message": str(exc)})
        except RuntimeError as exc:
            return self._send(502, {"error": "DATA_SOURCE_ERROR", "message": str(exc)})

    def log_message(self, fmt: str, *args: Any) -> None:
        print(f"[{self.log_date_time_string()}] {self.address_string()} {fmt % args}")


if __name__ == "__main__":
    print(f"Analytics Service (Native Python) listening on {HOST}:{PORT}")
    ThreadingHTTPServer((HOST, PORT), AnalyticsHandler).serve_forever()
