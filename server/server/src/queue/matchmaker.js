const { v4: uuidv4 } = require('uuid');
const { Op } = require('sequelize');
const User = require('../models/User');
const { UserInterest } = require('../models/Interest');
const Message = require('../models/Message');

const waitingPool = new Map();

function initMatchmaker(io, socket) {
  const user = socket.data.user;

  socket.on('match:start', async ({ interestIds = [] } = {}) => {
    if (waitingPool.has(user.id)) return;
    if (socket.data.roomId) return;

    const myInterests = Array.isArray(interestIds) ? interestIds : [];

    for (const [otherId, other] of waitingPool.entries()) {
      if (otherId === user.id) continue;
      if (other.socket.connected === false) {
        waitingPool.delete(otherId);
        continue;
      }
      const overlap = other.interestIds.filter((id) => myInterests.includes(id));
      const compatible =
        myInterests.length === 0 ||
        other.interestIds.length === 0 ||
        overlap.length > 0;
      if (!compatible) continue;

      waitingPool.delete(otherId);
      const roomId = uuidv4();
      const me = { id: user.id, nickname: user.nickname, avatarColor: user.avatarColor, avatarSeed: user.avatarSeed };
      const them = { id: other.user.id, nickname: other.user.nickname, avatarColor: other.user.avatarColor, avatarSeed: other.user.avatarSeed };

      other.socket.data.roomId = roomId;
      socket.data.roomId = roomId;

      other.socket.join(roomId);
      socket.join(roomId);

      other.socket.data.peerId = user.id;
      socket.data.peerId = other.user.id;

      other.socket.emit('match:found', { roomId, peer: me });
      socket.emit('match:found', { roomId, peer: them });

      console.log(`[match] eşleşti: ${them.id} ↔ ${me.id} (room ${roomId})`);
      return;
    }

    waitingPool.set(user.id, {
      socket,
      user,
      interestIds: myInterests,
      since: Date.now(),
    });
    socket.emit('match:waiting');
    console.log(`[match] bekliyor: ${user.id} (havuz ${waitingPool.size})`);
  });

  socket.on('match:cancel', () => {
    cancel();
  });

  function cancel() {
    if (waitingPool.has(user.id)) {
      waitingPool.delete(user.id);
      socket.emit('match:cancelled');
    }
  }

  return { cancel };
}

setInterval(async () => {
  const now = Date.now();
  for (const [id, entry] of waitingPool.entries()) {
    if (now - entry.since > 60_000) {
      waitingPool.delete(id);
      try {
        entry.socket.emit('match:timeout');
      } catch {}
    }
    if (!entry.socket.connected) {
      waitingPool.delete(id);
    }
  }
}, 15_000).unref();

module.exports = { initMatchmaker };
