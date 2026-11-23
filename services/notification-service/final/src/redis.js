/**
 * Redis Pub/Sub & Client Manager (ioredis)
 * Phục vụ nhận sự kiện từ các microservices khác và phát sóng Realtime
 */
const Redis = require('ioredis');
const config = require('./config');

let redisPub = null;
let redisSub = null;
let isConnected = false;
let messageHandlers = [];

function initRedis() {
  const redisOptions = {
    host: config.redis.host,
    port: config.redis.port,
    password: config.redis.password,
    retryStrategy: (times) => Math.min(times * 1000, 5000),
    maxRetriesPerRequest: 2,
    lazyConnect: true,
  };

  try {
    redisPub = new Redis(redisOptions);
    redisSub = new Redis(redisOptions);

    redisPub.connect()
      .then(() => {
        isConnected = true;
        console.log(`[Redis] ✅ Đã kết nối Publisher tới ${config.redis.host}:${config.redis.port}`);
      })
      .catch((err) => {
        console.warn(`[Redis Warning] Chưa thể kết nối Publisher: ${err.message}. Chạy chế độ In-Memory fallback.`);
      });

    redisSub.connect()
      .then(() => {
        console.log(`[Redis] ✅ Đã kết nối Subscriber. Đang subscribe channel: '${config.redis.channel}' & '${config.redis.eventsChannel}'`);
        redisSub.subscribe(config.redis.channel, config.redis.eventsChannel, (err) => {
          if (err) console.error(`[Redis Error] Lỗi subscribe channel: ${err.message}`);
        });
      })
      .catch((err) => {
        console.warn(`[Redis Warning] Chưa thể kết nối Subscriber: ${err.message}.`);
      });

    redisSub.on('message', (channel, message) => {
      try {
        messageHandlers.forEach((handler) => handler(channel, message));
      } catch (err) {
        console.error(`[Redis Message Handler Error] ${err.message}`);
      }
    });

    redisPub.on('error', (err) => {
      isConnected = false;
      // Tránh crash process do redis error event
    });
    redisSub.on('error', () => {});

  } catch (err) {
    console.warn(`[Redis Init Error] ${err.message}. Tiếp tục chạy ở chế độ In-Memory fallback.`);
  }
}

/**
 * Đăng ký hàm callback nhận sự kiện từ Redis
 */
function onMessage(handler) {
  messageHandlers.push(handler);
}

/**
 * Publish thông báo lên Redis Channel
 */
async function publish(channel, data) {
  if (!redisPub || !isConnected) return false;
  try {
    const payload = typeof data === 'string' ? data : JSON.stringify(data);
    await redisPub.publish(channel || config.redis.channel, payload);
    return true;
  } catch (err) {
    console.error(`[Redis Publish Error] ${err.message}`);
    return false;
  }
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
  onMessage,
  publish,
  getStatus,
};
