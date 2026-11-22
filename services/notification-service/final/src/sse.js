/**
 * Server-Sent Events (SSE) Manager
 * Kênh truyền phát thời gian thực qua giao thức HTTP (tương thích 100% với Web PHP Frontend)
 */

// Lưu trữ danh sách kết nối SSE: Map<userId, Set<Response>>
const clientMap = new Map();

function registerClient(userId, req, res) {
  const uid = Number(userId);

  // Thiết lập HTTP Header chuẩn Server-Sent Events
  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-cache, no-transform',
    'Connection': 'keep-alive',
    'Access-Control-Allow-Origin': '*',
    'X-Accel-Buffering': 'no', // Chống Nginx buffer SSE
  });

  if (!clientMap.has(uid)) {
    clientMap.set(uid, new Set());
  }
  clientMap.get(uid).add(res);

  // Gửi sự kiện chào mừng kết nối ban đầu
  const initMsg = JSON.stringify({
    type: 'CONNECTED',
    userId: uid,
    message: 'Đã thiết lập luồng thông báo Realtime SSE thành công',
    connectedAt: new Date().toISOString(),
  });
  res.write(`event: connected\ndata: ${initMsg}\n\n`);

  // Heartbeat Ping mỗi 25 giây để giữ kết nối qua Gateway/Proxy
  const heartbeatTimer = setInterval(() => {
    try {
      res.write(': keep-alive ping\n\n');
    } catch (_) {
      clearInterval(heartbeatTimer);
    }
  }, 25000);

  // Dọn dẹp khi client đóng tab hoặc ngắt kết nối
  req.on('close', () => {
    clearInterval(heartbeatTimer);
    const set = clientMap.get(uid);
    if (set) {
      set.delete(res);
      if (set.size === 0) clientMap.delete(uid);
    }
  });
}

function broadcastToUser(userId, notification) {
  const uid = Number(userId);
  const clients = clientMap.get(uid);
  if (!clients || clients.size === 0) return 0;

  const payload = JSON.stringify(notification);
  let delivered = 0;

  for (const client of clients) {
    try {
      client.write(`event: notification\ndata: ${payload}\n\n`);
      delivered++;
    } catch (err) {
      clients.delete(client);
    }
  }

  return delivered;
}

function getClientCount() {
  let count = 0;
  for (const set of clientMap.values()) count += set.size;
  return count;
}

module.exports = {
  registerClient,
  broadcastToUser,
  getClientCount,
};
