const userConnections = new Map();

function registerClient(userId, req, res) {
  const uid = Number(userId);

  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-cache, no-transform',
    'Connection': 'keep-alive',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-User-Id',
    'X-Accel-Buffering': 'no',
  });

  const welcomeMessage = {
    type: 'CONNECTED',
    message: `Đã kết nối kênh thông báo realtime (SSE) cho User ID: ${uid}`,
    timestamp: new Date().toISOString(),
  };
  res.write(`event: connected\ndata: ${JSON.stringify(welcomeMessage)}\n\n`);

  if (!userConnections.has(uid)) {
    userConnections.set(uid, new Set());
  }
  userConnections.get(uid).add(res);

  const heartbeatTimer = setInterval(() => {
    try {
      res.write(': keep-alive ping\n\n');
    } catch {
      clearInterval(heartbeatTimer);
    }
  }, 25000);

  req.on('close', () => {
    clearInterval(heartbeatTimer);
    const set = userConnections.get(uid);
    if (set) {
      set.delete(res);
      if (set.size === 0) userConnections.delete(uid);
    }
  });
}

function broadcastToUser(userId, notification) {
  const set = userConnections.get(Number(userId));
  if (!set || set.size === 0) return 0;

  const payload = `event: notification\ndata: ${JSON.stringify(notification)}\n\n`;
  let sentCount = 0;

  for (const res of set) {
    try {
      res.write(payload);
      sentCount++;
    } catch (err) {
      console.error(`[SSE Error] ${err.message}`);
    }
  }
  return sentCount;
}

function broadcastToAll(notification) {
  const payload = `event: notification\ndata: ${JSON.stringify(notification)}\n\n`;
  let totalSent = 0;
  for (const set of userConnections.values()) {
    for (const res of set) {
      try {
        res.write(payload);
        totalSent++;
      } catch {}
    }
  }
  return totalSent;
}

function getClientCount() {
  let count = 0;
  for (const set of userConnections.values()) count += set.size;
  return count;
}

module.exports = {
  registerClient,
  broadcastToUser,
  broadcastToAll,
  getClientCount,
};
