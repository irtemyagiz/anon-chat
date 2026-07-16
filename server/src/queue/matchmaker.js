const { v4: uuidv4 } = require('uuid');
const { Op } = require('sequelize');
const User = require('../models/User');
const UserChat = require('../models/UserChat');
const { UserInterest } = require('../models/Interest');
const Message = require('../models/Message');

const waitingPool = new Map();
const SOULMATE_POOL = new Map();

function publicUserLite(u) {
  return {
    id: u.id,
    nickname: u.nickname,
    username: u.username,
    avatarColor: u.avatarColor,
    avatarSeed: u.avatarSeed,
    avatarStyle: u.avatarStyle || 'adventurer',
    photoUrl: u.photoBase64 ? `data:image/jpeg;base64,${u.photoBase64}` : null,
    bio: u.bio,
    age: u.age,
    gender: u.gender,
    isPlus: u.isPlus && (!u.plusExpiresAt || new Date(u.plusExpiresAt) > new Date()),
  };
}

function isPlus(user) {
  return user.isPlus && (!user.plusExpiresAt || new Date(user.plusExpiresAt) > new Date());
}

function resetCounterIfNeeded(user, counterField, resetField) {
  const now = new Date();
  const last = user[resetField] ? new Date(user[resetField]) : new Date(0);
  const isSameDay =
    last.getUTCFullYear() === now.getUTCFullYear() &&
    last.getUTCMonth() === now.getUTCMonth() &&
    last.getUTCDate() === now.getUTCDate();
  if (!isSameDay) {
    user[counterField] = 0;
    user[resetField] = now;
  }
}

function initMatchmaker(io, socket) {
  const user = socket.data.user;

  // ---------- Regular shuffle match (interest-based) ----------
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
      const me = publicUserLite(user);
      const them = publicUserLite(other.user);

      other.socket.data.roomId = roomId;
      socket.data.roomId = roomId;
      other.socket.data.peerId = user.id;
      socket.data.peerId = other.user.id;
      other.socket.data.revealed = false;
      socket.data.revealed = false;
      other.socket.data.matchMode = 'shuffle';
      socket.data.matchMode = 'shuffle';

      other.socket.join(roomId);
      socket.join(roomId);

      await Promise.all([
        UserChat.findOrCreate({
          where: { userId: user.id, peerId: other.user.id },
          defaults: { roomId, lastReadAt: new Date() },
        }),
        UserChat.findOrCreate({
          where: { userId: other.user.id, peerId: user.id },
          defaults: { roomId, lastReadAt: new Date() },
        }),
      ]).catch((e) => console.warn('[userchat upsert]', e.message));

      other.socket.emit('match:found', { roomId, peer: me, peerAnonymity: me.anonymityEnabled, mode: 'shuffle' });
      socket.emit('match:found', { roomId, peer: them, peerAnonymity: them.anonymityEnabled, mode: 'shuffle' });
      console.log(`[match-shuffle] eşleşti: ${them.id} ↔ ${me.id}`);
      return;
    }

    waitingPool.set(user.id, {
      socket,
      user,
      interestIds: myInterests,
      since: Date.now(),
    });
    socket.emit('match:waiting', { mode: 'shuffle' });
    console.log(`[match-shuffle] bekliyor: ${user.id}`);
  });

  // ---------- Soulmate random match (anonymous, no follow) ----------
  socket.on('match:soulmate:start', async () => {
    if (SOULMATE_POOL.has(user.id)) return;
    if (socket.data.roomId) return;

    // Refresh user from DB to get latest Plus status & count
    let freshUser;
    try {
      freshUser = await User.findByPk(user.id);
      if (freshUser) {
        socket.data.user = freshUser;
      } else {
        freshUser = user;
      }
    } catch (e) {
      freshUser = user;
    }
    const liveUser = freshUser;

    if (!isPlus(liveUser)) {
      resetCounterIfNeeded(liveUser, 'dailySoulmateCount', 'lastSoulmateResetAt');
      if (liveUser.dailySoulmateCount >= 5) {
        return socket.emit('match:soulmate:limit', {
          error: 'daily_limit_reached',
          limit: 5,
          used: liveUser.dailySoulmateCount,
        });
      }
    }

    for (const [otherId, other] of SOULMATE_POOL.entries()) {
      if (otherId === liveUser.id) continue;
      if (other.socket.connected === false) {
        SOULMATE_POOL.delete(otherId);
        continue;
      }
      SOULMATE_POOL.delete(otherId);

      const roomId = uuidv4();
      const me = publicUserLite(liveUser);
      const them = publicUserLite(other.user);

      other.socket.data.roomId = roomId;
      socket.data.roomId = roomId;
      other.socket.data.peerId = liveUser.id;
      socket.data.peerId = other.user.id;
      other.socket.data.revealed = false;
      socket.data.revealed = false;
      other.socket.data.matchMode = 'soulmate';
      socket.data.matchMode = 'soulmate';

      other.socket.join(roomId);
      socket.join(roomId);

      await Promise.all([
        UserChat.findOrCreate({
          where: { userId: liveUser.id, peerId: other.user.id },
          defaults: { roomId, lastReadAt: new Date() },
        }),
        UserChat.findOrCreate({
          where: { userId: other.user.id, peerId: liveUser.id },
          defaults: { roomId, lastReadAt: new Date() },
        }),
      ]).catch((e) => console.warn('[userchat upsert soulmate]', e.message));

      if (!isPlus(liveUser)) {
        liveUser.dailySoulmateCount = (liveUser.dailySoulmateCount || 0) + 1;
        await liveUser.save().catch(() => {});
      }
      if (!isPlus(other.user)) {
        other.user.dailySoulmateCount = (other.user.dailySoulmateCount || 0) + 1;
        await other.user.save().catch(() => {});
      }

      other.socket.emit('match:soulmate:found', { roomId, peer: me });
      socket.emit('match:soulmate:found', { roomId, peer: them });
      console.log(`[match-soulmate] eşleşti: ${them.id} ↔ ${me.id}`);
      return;
    }

    SOULMATE_POOL.set(liveUser.id, { socket, user: liveUser, since: Date.now() });
    socket.emit('match:soulmate:waiting');
    console.log(`[match-soulmate] bekliyor: ${liveUser.id}`);
  });

  socket.on('match:cancel', () => cancel());
  socket.on('match:soulmate:cancel', () => cancelSoulmate());

  function cancel() {
    if (waitingPool.has(user.id)) {
      waitingPool.delete(user.id);
      socket.emit('match:cancelled');
    }
  }

  function cancelSoulmate() {
    if (SOULMATE_POOL.has(user.id)) {
      SOULMATE_POOL.delete(user.id);
      socket.emit('match:soulmate:cancelled');
    }
  }

  return { cancel, cancelSoulmate };
}

setInterval(async () => {
  const now = Date.now();
  for (const [id, entry] of waitingPool.entries()) {
    if (now - entry.since > 60_000) {
      waitingPool.delete(id);
      try { entry.socket.emit('match:timeout'); } catch {}
    }
    if (!entry.socket.connected) waitingPool.delete(id);
  }
  for (const [id, entry] of SOULMATE_POOL.entries()) {
    if (now - entry.since > 60_000) {
      SOULMATE_POOL.delete(id);
      try { entry.socket.emit('match:soulmate:timeout'); } catch {}
    }
    if (!entry.socket.connected) SOULMATE_POOL.delete(id);
  }
}, 15_000).unref();

module.exports = { initMatchmaker };