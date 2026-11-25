const net = require('node:net');
const config = require('./config');

let isConnected = false;
let messageHandler = null;
let nativeSocket = null;

function onNotification(handler) {
  messageHandler = handler;
}

function initRedis() {
  if (nativeSocket) {
    nativeSocket.destroy();
  }

  nativeSocket = net.createConnection({ host: config.redis.host, port: config.redis.port }, () => {
    isConnected = true;
    console.log(`[Redis Native TCP] Connected to ${config.redis.host}:${config.redis.port}, subscribing '${config.redis.channel}'`);
    nativeSocket.write(`SUBSCRIBE ${config.redis.channel}\r\n`);
  });

  let buffer = '';

  nativeSocket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');
    while (buffer.includes('\r\n')) {
      const msgIndex = buffer.indexOf('message\r\n');
      if (msgIndex === -1) break;
      const parts = buffer.slice(msgIndex + 'message\r\n'.length).split('\r\n');
      if (parts.length < 4 || parts[1] !== config.redis.channel) break;
      if (messageHandler) {
        messageHandler(parts[3]);
      }
      buffer = parts.slice(4).join('\r\n');
    }
  });

  nativeSocket.on('error', () => {
    isConnected = false;
  });

  nativeSocket.on('close', () => {
    isConnected = false;
    setTimeout(initRedis, 5000).unref();
  });
}

function publishNotification(data) {
  const payload = typeof data === 'string' ? data : JSON.stringify(data);
  try {
    const pubSocket = net.createConnection({ host: config.redis.host, port: config.redis.port }, () => {
      const args = ['PUBLISH', config.redis.channel, payload];
      const resp = `*${args.length}\r\n` + args.map((a) => `$${Buffer.byteLength(a)}\r\n${a}\r\n`).join('');
      pubSocket.write(resp);
      setTimeout(() => pubSocket.end(), 200).unref();
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
