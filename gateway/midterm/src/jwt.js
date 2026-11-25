const crypto = require('node:crypto');

function verifyJwt(token, secret) {
  if (!token || typeof token !== 'string') {
    return { valid: false, error: 'Token không được để trống' };
  }

  const parts = token.trim().split('.');
  if (parts.length !== 3) {
    return { valid: false, error: 'Định dạng JWT không hợp lệ' };
  }

  const [headerB64, payloadB64, signatureB64] = parts;
  try {
    const expectedSignature = crypto
      .createHmac('sha256', secret)
      .update(`${headerB64}.${payloadB64}`)
      .digest('base64url');

    const sigBuffer = Buffer.from(signatureB64, 'utf8');
    const expectedBuffer = Buffer.from(expectedSignature, 'utf8');
    if (sigBuffer.length !== expectedBuffer.length || !crypto.timingSafeEqual(sigBuffer, expectedBuffer)) {
      return { valid: false, error: 'Chữ ký JWT không hợp lệ' };
    }

    const payload = JSON.parse(Buffer.from(payloadB64, 'base64url').toString('utf8'));
    if (payload.exp && payload.exp < Math.floor(Date.now() / 1000)) {
      return { valid: false, error: 'Token đã hết hạn sử dụng' };
    }

    const userId = payload.userId || payload.user_id || payload.id;
    return {
      valid: true,
      payload: {
        ...payload,
        userId: userId ? Number(userId) : null,
      },
    };
  } catch (err) {
    return { valid: false, error: `Lỗi giải mã token: ${err.message}` };
  }
}

function extractBearerToken(authHeader) {
  if (!authHeader || typeof authHeader !== 'string') return null;
  const match = authHeader.match(/^Bearer\s+(.+)$/i);
  return match ? match[1].trim() : null;
}

module.exports = { verifyJwt, extractBearerToken };
