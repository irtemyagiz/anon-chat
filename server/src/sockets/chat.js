const Message = require('../models/Message');
const Report = require('../models/Report');
const Ban = require('../models/Ban');
const User = require('../models/User');
const { Op } = require('sequelize');
const { recordChat } = require('../routes/friends');

const FLAG_PATTERNS = [
  /\b(küfür|amk|oruspu|got|pezevenk)\b/i,
];

function isFlagged(text) {
  return FLAG_PATTERNS.some((re) => re.test(text));
}

function initChatHandler(io, socket) {
  const user = socket.data.user;

  socket.on('chat:message', async ({ content } = {}) => {
    const roomId = socket.data.roomId;
    if (!roomId) return socket.emit('chat:error', { error: 'no_room' });
    if (typeof content !== 'string') return socket.emit('chat:error', { error: 'invalid_content' });
    const trimmed = content.trim().slice(0, 1000);
    if (!trimmed) return socket.emit('chat:error', { error: 'empty' });

    const flagged = isFlagged(trimmed);

    try {
      const msg = await Message.create({
        roomId,
        senderId: user.id,
        content: trimmed,
        flagged,
      });

      socket.to(roomId).emit('chat:message', {
        id: msg.id,
        content: trimmed,
        senderId: user.id,
        createdAt: msg.createdAt,
        flagged,
      });
      socket.emit('chat:sent', { id: msg.id, createdAt: msg.createdAt });
    } catch (err) {
      console.error('[chat:message]', err);
      socket.emit('chat:error', { error: 'server_error' });
    }
  });

  socket.on('chat:typing', ({ typing } = {}) => {
    const roomId = socket.data.roomId;
    if (!roomId) return;
    socket.to(roomId).emit('chat:typing', { userId: user.id, typing: !!typing });
  });

  socket.on('chat:leave', async () => {
    const roomId = socket.data.roomId;
    const peerId = socket.data.peerId;
    if (!roomId) return;
    socket.leave(roomId);
    socket.to(roomId).emit('chat:ended', { by: user.id });
    if (peerId) {
      try {
        await recordChat(user.id, peerId);
      } catch (err) {
        console.warn('[recordChat]', err.message);
      }
    }
    socket.data.roomId = null;
    socket.data.peerId = null;
  });

  socket.on('chat:report', async ({ reason = 'inappropriate', note } = {}) => {
    const roomId = socket.data.roomId;
    const reportedId = socket.data.peerId;
    if (!reportedId) return socket.emit('chat:error', { error: 'no_peer' });

    try {
      await Report.create({ reporterId: user.id, reportedId, roomId, reason, note });

      const cutoff = new Date(Date.now() - 24 * 60 * 60 * 1000);
      const recentCount = await Report.count({
        where: { reportedId, createdAt: { [Op.gt]: cutoff } },
      });

      if (recentCount >= 3) {
        const reportedUser = await User.findByPk(reportedId);
        if (reportedUser) {
          const expiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000);
          await Ban.create({
            deviceId: reportedUser.deviceId,
            userId: reportedUser.id,
            reason: 'auto_3_reports_24h',
            expiresAt,
          });
          reportedUser.isBanned = true;
          await reportedUser.save();
          io.to(reportedUser.id).emit('banned', { expiresAt });
          console.log(`[ban] ${reportedUser.id} 24 saat banlandı (3 şikayet)`);
        }
      }

      socket.emit('chat:reported', { ok: true });
    } catch (err) {
      console.error('[chat:report]', err);
      socket.emit('chat:error', { error: 'server_error' });
    }
  });
}

module.exports = { initChatHandler };
