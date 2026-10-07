import http from 'k6/http';
import { check, sleep } from 'k6';

// k6 Stress test / Concurrency benchmark for SOA-CashFlow
export const options = {
  stages: [
    { duration: '30s', target: 50 },  // Ramp up to 50 concurrent users
    { duration: '1m', target: 200 },  // Spike to 200 concurrent users
    { duration: '30s', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should complete within 500ms
    http_req_failed: ['rate<0.01'],   // Error rate should be less than 1%
  },
};

const BASE_URL = __ENV.GATEWAY_URL || 'http://localhost:8080';

export default function () {
  // 1. Test Gateway Health / Transaction listing
  const res = http.get(`${BASE_URL}/api/transactions`);
  check(res, {
    'status is 200 or 401': (r) => r.status === 200 || r.status === 401,
  });

  // 2. Test Concurrency on Transaction creation
  const payload = JSON.stringify({
    amount: Math.floor(Math.random() * 100000) + 1000,
    type: 'expense',
    category: 'shopping',
    description: 'Concurrency stress test item',
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
  };

  const postRes = http.post(`${BASE_URL}/api/transactions`, payload, params);
  check(postRes, {
    'create status accepted or ok': (r) => [200, 201, 401].includes(r.status),
  });

  sleep(0.5);
}
