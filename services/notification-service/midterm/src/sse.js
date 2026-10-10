/**
 * Quản lý Kênh Realtime Server-Sent Events (SSE) thuần Node.js
 * Đẩy thông báo tức thì tới trình duyệt mà không cần Socket.io hay thư viện ngoài
 */

// Map lưu danh sách kết nối SSE: userId -> Set(res)
const userConnections = new Map();

/**
 * Đăng ký một kết nối SSE mới cho người dùng
 * @param {number} userId 
 * @param {http.IncomingMessage} req 
 * @param {http.ServerResponse} res 
 */
function registerClient(userId, req, res) {
  const uid = Number(userId);

  // Thiết lập HTTP Headers chuẩn Server-Sent Events
  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-cache, no-transform',
    'Connection': 'keep-alive',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
    'X-Accel-Buffering': 'no', // Ngăn Reverse Proxy / Gateway đệm gói tin
  });

  // Gửi thông điệp chào mừng khởi tạo kết nối
  const welcomeMessage = {
    type: 'CONNECTED',
    message: `Đã kết nối thành công kênh thông báo thời gian thực (SSE) cho User ID: ${uid}`,
    timestamp: new Date().toISOString(),
  };
  res.write(`event: connected\ndata: ${JSON.stringify(welcomeMessage)}\n\n`);

  // Lưu response vào tập hợp kết nối của user
  if (!userConnections.has(uid)) {
    userConnections.set(uid, new Set());
  }
  userConnections.get(uid).add(res);

  console.log(`[SSE] User ${uid} đã mở kết nối realtime (Tổng kết nối hiện tại: ${getClientCount()})`);

  // Heartbeat định kỳ (mỗi 25 giây) để giữ kết nối TCP sống qua các Gateway/Proxies
  const heartbeatTimer = setInterval(() => {
    try {
      res.write(': keep-alive ping\n\n');
    } catch {
      clearInterval(heartbeatTimer);
    }
  }, 25000);

  // Xử lý khi Client ngắt kết nối (đóng tab, reload trang)
  req.on('close', () => {
    clearInterval(heartbeatTimer);
    const set = userConnections.get(uid);
    if (set) {
      set.delete(res);
      if (set.size === 0) {
        userConnections.delete(uid);
      }
    }
    console.log(`[SSE] User ${uid} đã đóng kết nối realtime (Còn lại: ${getClientCount()})`);
  });
}

/**
 * Đẩy thông báo thời gian thực tới một người dùng cụ thể
 * @param {number} userId 
 * @param {object} notification 
 * @returns {number} Số lượng clients nhận được thông báo
 */
function broadcastToUser(userId, notification) {
  const uid = Number(userId);
  const set = userConnections.get(uid);

  if (!set || set.size === 0) {
    return 0; // User hiện không online trên kênh SSE
  }

  const payload = `event: notification\ndata: ${JSON.stringify(notification)}\n\n`;
  let sentCount = 0;

  for (const res of set) {
    try {
      res.write(payload);
      sentCount++;
    } catch (err) {
      console.error(`[SSE Error] Gửi thông báo thất bại cho socket: ${err.message}`);
    }
  }

  return sentCount;
}

/**
 * Đẩy thông báo tới tất cả người dùng đang kết nối
 * @param {object} notification 
 */
function broadcastToAll(notification) {
  const payload = `event: notification\ndata: ${JSON.stringify(notification)}\n\n`;
  let sentCount = 0;

  for (const set of userConnections.values()) {
    for (const res of set) {
      try {
        res.write(payload);
        sentCount++;
      } catch {}
    }
  }

  return sentCount;
}

/**
 * Lấy tổng số lượng Client đang kết nối SSE
 */
function getClientCount() {
  let count = 0;
  for (const set of userConnections.values()) {
    count += set.size;
  }
  return count;
}

module.exports = {
  registerClient,
  broadcastToUser,
  broadcastToAll,
  getClientCount,
};
