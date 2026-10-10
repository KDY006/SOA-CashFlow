/**
 * Redis Pub/Sub Client (Hỗ trợ ioredis hoặc Native TCP Socket qua node:net)
 * Lắng nghe các sự kiện cảnh báo từ Transaction Service, Analytics Service
 */

const net = require('node:net');
const config = require('./config');

let isConnected = false;
let messageHandler = null;
let nativeSocket = null;
let ioRedisSub = null;
let ioRedisPub = null;

/**
 * Đăng ký hàm nhận thông báo khi có message mới từ Redis
 */
function onNotification(handler) {
  messageHandler = handler;
}

/**
 * Khởi động kết nối Redis Pub/Sub
 */
function initRedis() {
  // Thử dùng ioredis nếu package đã được cài đặt
  try {
    const Redis = require('ioredis');
    console.log(`[Redis] Phát hiện thư viện ioredis, đang kết nối tới ${config.redis.host}:${config.redis.port}...`);

    ioRedisSub = new Redis({
      host: config.redis.host,
      port: config.redis.port,
      retryStrategy: (times) => Math.min(times * 1000, 5000),
      maxRetriesPerRequest: 1,
      lazyConnect: true,
    });

    ioRedisPub = new Redis({
      host: config.redis.host,
      port: config.redis.port,
      retryStrategy: (times) => Math.min(times * 1000, 5000),
      maxRetriesPerRequest: 1,
      lazyConnect: true,
    });

    ioRedisSub.connect().then(() => {
      isConnected = true;
      console.log(`[Redis] Đã kết nối thành công qua ioredis. Đang subscribe channel: '${config.redis.channel}'`);
      ioRedisSub.subscribe(config.redis.channel, (err) => {
        if (err) console.error(`[Redis Error] Lỗi subscribe: ${err.message}`);
      });
    }).catch((err) => {
      console.warn(`[Redis Warning] Chưa thể kết nối tới Redis qua ioredis (${err.message}). Chuyển sang Native TCP fallback.`);
      initNativeTcpRedis();
    });

    ioRedisPub.connect().catch(() => {});

    ioRedisSub.on('message', (channel, message) => {
      if (channel === config.redis.channel && messageHandler) {
        messageHandler(message);
      }
    });

    ioRedisSub.on('close', () => { isConnected = false; });
    ioRedisSub.on('connect', () => { isConnected = true; });

    return;
  } catch {
    // Nếu chưa cài ioredis, dùng kết nối TCP thuần của Node.js (Zero-dependency)
    initNativeTcpRedis();
  }
}

/**
 * Kết nối Redis thuần qua socket TCP node:net (RESP protocol)
 */
function initNativeTcpRedis() {
  if (nativeSocket) {
    nativeSocket.destroy();
  }

  console.log(`[Redis Native TCP] Đang kết nối tới ${config.redis.host}:${config.redis.port}...`);
  nativeSocket = net.createConnection({ host: config.redis.host, port: config.redis.port }, () => {
    isConnected = true;
    console.log(`[Redis Native TCP] Kết nối thành công! Đang SUBSCRIBE channel '${config.redis.channel}'...`);
    
    // Gửi lệnh SUBSCRIBE dạng RESP protocol thô: SUBSCRIBE <channel>\r\n
    nativeSocket.write(`SUBSCRIBE ${config.redis.channel}\r\n`);
  });

  let buffer = '';

  nativeSocket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');

    // Phân tích cú pháp message trong kênh Pub/Sub
    // Định dạng RESP: *3\r\n$7\r\nmessage\r\n$<len>\r\n<channel>\r\n$<len>\r\n<payload>\r\n
    while (buffer.includes('\r\n')) {
      const msgIndex = buffer.indexOf('message\r\n');
      if (msgIndex !== -1) {
        const afterType = buffer.slice(msgIndex + 'message\r\n'.length);
        const parts = afterType.split('\r\n');

        // parts[0]: $channelLen, parts[1]: channel, parts[2]: $payloadLen, parts[3]: payload
        if (parts.length >= 4 && parts[1] === config.redis.channel) {
          const payload = parts[3];
          if (messageHandler) {
            messageHandler(payload);
          }
          buffer = parts.slice(4).join('\r\n');
          continue;
        }
      }
      break;
    }
  });

  nativeSocket.on('error', (err) => {
    isConnected = false;
    // Bỏ qua log spam nếu container redis chưa sẵn sàng lúc khởi động
  });

  nativeSocket.on('close', () => {
    isConnected = false;
    // Tự động kết nối lại sau 5 giây nếu bị ngắt
    setTimeout(() => {
      initNativeTcpRedis();
    }, 5000);
  });
}

/**
 * Xuất bản (Publish) thông báo qua Redis để các instance khác cùng nhận được
 */
function publishNotification(data) {
  const payload = typeof data === 'string' ? data : JSON.stringify(data);

  if (ioRedisPub && ioRedisPub.status === 'ready') {
    return ioRedisPub.publish(config.redis.channel, payload);
  }

  // Fallback gửi qua TCP thuần nếu đang dùng socket
  try {
    const pubSocket = net.createConnection({ host: config.redis.host, port: config.redis.port }, () => {
      pubSocket.write(`PUBLISH ${config.redis.channel} "${payload.replace(/"/g, '\\"')}"\r\n`);
      setTimeout(() => pubSocket.end(), 200);
    });
    pubSocket.on('error', () => {});
  } catch {}
}

function getStatus() {
  return {
    connected: isConnected,
    host: config.redis.host,
    port: config.redis.port,
    channel: config.redis.channel,
  };
}

module.exports = {
  initRedis,
  onNotification,
  publishNotification,
  getStatus,
};
