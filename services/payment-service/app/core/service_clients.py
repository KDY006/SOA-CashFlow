import os

import httpx


USER_SERVICE_URL = os.getenv("USER_SERVICE_URL", "http://localhost:8001")
TUITION_SERVICE_URL = os.getenv("TUITION_SERVICE_URL", "http://localhost:8002")
OTP_SERVICE_URL = os.getenv("OTP_SERVICE_URL", "http://localhost:8003")
TIMEOUT = float(os.getenv("SERVICE_TIMEOUT_SECONDS", "5"))


class ServiceCallError(Exception):
    pass


def request_json(method: str, url: str, **kwargs):
    try:
        response = httpx.request(method, url, timeout=TIMEOUT, **kwargs)
        response.raise_for_status()
        return response.json() if response.content else {}
    except httpx.HTTPError as exc:
        raise ServiceCallError(f"Service request failed: {method} {url}") from exc


def get_account(user_id: str):
    return request_json("GET", f"{USER_SERVICE_URL}/internal/accounts/{user_id}")


def debit_account(user_id: str, amount: str, transaction_id: str):
    return request_json(
        "POST", f"{USER_SERVICE_URL}/internal/accounts/{user_id}/debit",
        json={"amount": amount, "reference_id": transaction_id},
    )


def refund_account(user_id: str, amount: str, transaction_id: str):
    return request_json(
        "POST", f"{USER_SERVICE_URL}/internal/accounts/{user_id}/credit",
        json={"amount": amount, "reference_id": f"refund:{transaction_id}"},
    )


def get_tuition(tuition_record_id: str):
    return request_json("GET", f"{TUITION_SERVICE_URL}/internal/tuition-records/{tuition_record_id}")


def mark_tuition_paid(tuition_record_id: str, transaction_id: str):
    return request_json(
        "POST", f"{TUITION_SERVICE_URL}/internal/tuition-records/{tuition_record_id}/pay",
        json={"transaction_id": transaction_id},
    )


def request_otp(transaction_id: str, user_id: str):
    return request_json(
        "POST", f"{OTP_SERVICE_URL}/internal/otps",
        json={"transaction_id": transaction_id, "user_id": user_id},
    )


def verify_otp(transaction_id: str, otp_code: str):
    return request_json(
        "POST", f"{OTP_SERVICE_URL}/internal/otps/verify",
        json={"transaction_id": transaction_id, "otp_code": otp_code},
    )
