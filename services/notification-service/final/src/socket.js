/**
 * Socket.io WebSocket Manager
 * Hỗ trợ giao tiếp Realtime 2 chiều hiện đại (Web / Mobile Apps)
 */
const { Server } = require('socket.io');

let io = null;

function initSocket(httpServer) {
  io = new Server(httpServer, {
    cors: {
      origin: '*',
      methods: ['GET', 'POST'],
    },
    transports: ['websocket', 'polling'],
  });

  io.on('connection', (socket) => {
    const userId = socket.handshake.query.userId || socket.handshake.auth.userId;
    if (userId) {
      const room = `user:${userId}`;
      socket.join(room);
      console.log(`[Socket.io] Client ${socket.id} đã tham gia room '${room}'`);
    }

    socket.on('join', (data) => {
      if (data && data.userId) {
        socket.join(`user:${data.userId}`);
        socket.emit('joined', { success: true, room: `user:${data.userId}` });
      }
    });

    socket.on('disconnect', () => {
      // client ngắt kết nối
    });
  });

  return io;
}

/**
 * Phát sự kiện thông báo tới một user qua Socket.io
 */
function emitToUser(userId, event, data) {
  if (!io) return 0;
  const room = `user:${userId}`;
  io.to(room).emit(event || 'notification:new', data);
  const sockets = io.sockets.adapter.rooms.get(room);
  return sockets ? sockets.size : 0;
}

/**
 * Phát sự kiện broadcast cho toàn bộ clients
 */
function broadcastAll(event, data) {
  if (!io) return;
  io.emit(event || 'notification:broadcast', data);
}

function getSocketClientCount() {
  if (!io) return 0;
  return io.engine.clientsCount || 0;
}

module.exports = {
  initSocket,
  emitToUser,
  broadcastAll,
  getSocketClientCount,
};
