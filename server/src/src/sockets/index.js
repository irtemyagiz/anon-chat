const { Server } = require('socket.io');
const { verifyToken } = require('../lib/jwt');
const User = require('../models/User');
const { initMatchmaker } = require('../queue/matchmaker');
const { initChatHandler } = require('./chat');

function setUpSockets(httpServer, corsOptions) {
  const io = new Server(httpServer, {
    cors: corsOptions,
    pingInterval: 25000,
    pingTimeout: 60000,
  });

  io.use(async (socket, next) => {
    try {
      const token =
        socket.handshake.auth?.token ||
        socket.handshake.headers.authorization?.replace(/^Bearer\s+/i, '');
      if (!token) return next(new Error('auth_required'));
      const payload = verifyToken(token);
      const user = await User.findByPk(payload.sub);
      if (!user) return next(new Error('invalid_token'));
      if (user.isBanned) return next(new Error('banned'));
      socket.data.user = user;
      next();
    } catch (err) {
      next(new Error('invalid_token'));
    }
  });

  io.on('connection', (socket) => {
    const user = socket.data.user;
    socket.data.joinedAt = Date.now();
    console.log(`[socket] bağlandı: ${user.id} (${user.nickname})`);

    const matchmaker = initMatchmaker(io, socket);
    initChatHandler(io, socket);

    socket.on('disconnect', (reason) => {
      console.log(`[socket] ayrıldı: ${user.id} sebep=${reason}`);
      matchmaker.cancel();
    });
  });

  return io;
}

module.exports = { setUpSockets };
