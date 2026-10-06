import os
import uuid
from contextlib import contextmanager

import redis


redis_client = redis.Redis.from_url(os.getenv("REDIS_URL", "redis://localhost:6379/0"), decode_responses=True)
RELEASE_IF_OWNER = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end"


class LockUnavailable(Exception):
    pass


@contextmanager
def payment_locks(user_id: str, tuition_record_id: str, timeout: int = 10, lease_seconds: int = 30):
    """Acquire both locks in a stable order to avoid deadlocks."""
    keys = sorted((f"lock:account:{user_id}", f"lock:tuition:{tuition_record_id}"))
    token = str(uuid.uuid4())
    acquired = []
    try:
        for key in keys:
            if not redis_client.set(key, token, nx=True, ex=lease_seconds):
                raise LockUnavailable(f"Resource is busy: {key}")
            acquired.append(key)
        yield
    finally:
        for key in reversed(acquired):
            redis_client.eval(RELEASE_IF_OWNER, 1, key, token)
