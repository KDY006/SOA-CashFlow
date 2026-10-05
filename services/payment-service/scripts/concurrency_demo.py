"""Manual concurrency demo; requires seeded services and a development OTP."""
import os
from concurrent.futures import ThreadPoolExecutor, as_completed

import httpx


PAYMENT_URL = os.getenv("PAYMENT_URL", "http://localhost:8004/api/v1/payments")
OTP_CODE = os.getenv("OTP_CODE")
if not OTP_CODE:
    raise SystemExit("Set OTP_CODE to the dev OTP from the test mailbox/provider before running.")


def pay(user_id: str, tuition_id: str, key: str):
    with httpx.Client(timeout=30) as client:
        started = client.post(PAYMENT_URL, json={
            "user_id": user_id,
            "tuition_record_id": tuition_id,
            "idempotency_key": key,
        })
        if started.status_code not in (200, 201):
            return {"user_id": user_id, "tuition_id": tuition_id, "http": started.status_code, "body": started.text}
        transaction_id = started.json()["id"]
        done = client.post(f"{PAYMENT_URL}/{transaction_id}/confirm", json={"otp_code": OTP_CODE})
        result = done.json() if done.headers.get("content-type", "").startswith("application/json") else done.text
        return {"user_id": user_id, "tuition_id": tuition_id, "http": done.status_code, "result": result}


def run_case(title: str, attempts: list[tuple[str, str]]):
    print(f"\n{title}")
    results = []
    with ThreadPoolExecutor(max_workers=len(attempts)) as pool:
        futures = [pool.submit(pay, user, tuition, f"demo-{title}-{i}")
                   for i, (user, tuition) in enumerate(attempts)]
        for future in as_completed(futures):
            item = future.result()
            results.append(item)
            print(item)
    print("Successful payments:", sum(
        1 for item in results
        if item.get("result", {}).get("status") == "SUCCEEDED"
    ))


if __name__ == "__main__":
    # Configure IDs to match seed data. Case 1: same account, different tuition.
    users = [x.strip() for x in os.getenv("DEMO_USERS", "user-001,user-001").split(",")]
    tuition_ids = [x.strip() for x in os.getenv("DEMO_TUITIONS", "tuition-001,tuition-002").split(",")]
    run_case("same-account", list(zip(users, tuition_ids)))

    # Case 2: different accounts race to pay the same tuition record.
    race_users = [x.strip() for x in os.getenv("DEMO_RACE_USERS", "user-001,user-002").split(",")]
    shared_tuition = os.getenv("DEMO_SHARED_TUITION", "tuition-shared")
    run_case("same-tuition", [(user, shared_tuition) for user in race_users])
