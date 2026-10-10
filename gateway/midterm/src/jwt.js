/**
 * Module xác thực JWT HMAC-SHA256 thuần bằng node:crypto (Zero-dependency)
 * Tương thích hoàn toàn với thuật toán Java của Auth Service
 */

const crypto = require('node:crypto');

/**
 * Xác thực và giải mã JWT token
 * @param {string} token 
 * @param {string} secret 
 * @returns {{ valid: boolean, payload?: object, error?: string }}
 */
function verifyJwt(token, secret) {
  if (!token || typeof token !== 'string') {
    return { valid: false, error: 'Token không được để trống' };
  }

  const parts = token.trim().split('.');
  if (parts.length !== 3) {
    return { valid: false, error: 'Định dạng JWT không hợp lệ (phải có 3 phần)' };
  }

  const [headerB64, payloadB64, signatureB64] = parts;
  const data = `${headerB64}.${payloadB64}`;

  try {
    // Tính toán chữ ký kỳ vọng bằng HMAC-SHA256
    const expectedSignature = crypto
      .createHmac('sha256', secret)
      .update(data)
      .digest('base64url');

    // So sánh hằng thời gian (Constant-time comparison) để chống Timing Attack
    const sigBuffer = Buffer.from(signatureB64, 'utf8');
    const expectedBuffer = Buffer.from(expectedSignature, 'utf8');

    if (sigBuffer.length !== expectedBuffer.length || !crypto.timingSafeEqual(sigBuffer, expectedBuffer)) {
      return { valid: false, error: 'Chữ ký JWT không hợp lệ (Signature mismatch)' };
    }

    // Giải mã Payload từ Base64Url
    const payloadJson = Buffer.from(payloadB64, 'base64url').toString('utf8');
    const payload = JSON.parse(payloadJson);

    // Kiểm tra thời hạn hết hạn (Expiration Time)
    const nowInSeconds = Math.floor(Date.now() / 1000);
    if (payload.exp && payload.exp < nowInSeconds) {
      return { valid: false, error: 'Token đã hết hạn sử dụng' };
    }

    // Chuẩn hóa userId (Java Auth Service sinh ra key "userId")
    const userId = payload.userId || payload.user_id || payload.id;
    return {
      valid: true,
      payload: {
        ...payload,
        userId: userId ? Number(userId) : null
      }
    };
  } catch (err) {
    return { valid: false, error: `Lỗi giải mã token: ${err.message}` };
  }
}

/**
 * Trích xuất Bearer token từ Authorization header
 * @param {string} authHeader 
 * @returns {string|null}
 */
function extractBearerToken(authHeader) {
  if (!authHeader || typeof authHeader !== 'string') return null;
  const match = authHeader.match(/^Bearer\s+(.+)$/i);
  return match ? match[1].trim() : null;
}

module.exports = {
  verifyJwt,
  extractBearerToken,
};
