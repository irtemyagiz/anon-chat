const express = require('express');
const { Op } = require('sequelize');
const User = require('../models/User');
const Follow = require('../models/Follow');
const Friendship = require('../models/Friendship');
const Message = require('../models/Message');
const { authRequired } = require('../middleware/auth');
const { publicUser } = require('./auth');

const router = express.Router();

const FRIEND_CHAT_THRESHOLD = 10;

function friendKey(a, b) {
  return a < b ? [a, b] : [b, a];
}

async function countMutualChats(userA, userB) {
  const rooms = await Message.findAll({
    where: {
      [Op.or]: [{ senderId: userA }, { senderId: userB }],
    },
    attributes: ['roomId'],
    group: ['roomId'],
  });
  const roomIds = rooms.map((r) => r.roomId);
  let count = 0;
  for (const roomId of roomIds) {
    const senders = await Message.findAll({
      where: { roomId },
      attributes: ['senderId'],
      group: ['senderId'],
    });
    const ids = senders.map((s) => s.senderId);
    if (ids.includes(userA) && ids.includes(userB)) {
      count += 1;
    }
  }
  return count;
}

async function getOrCreateFriendship(userA, userB) {
  const [a, b] = friendKey(userA, userB);
  const [friendship, created] = await Friendship.findOrCreate({
    where: { userAId: a, userBId: b },
    defaults: { userAId: a, userBId: b, mutualChats: 0 },
  });
  return friendship;
}

async function recordChat(userA, userB) {
  const [a, b] = friendKey(userA, userB);
  let friendship = await Friendship.findOne({ where: { userAId: a, userBId: b } });
  if (!friendship) {
    friendship = await Friendship.create({ userAId: a, userBId: b, mutualChats: 0 });
  }
  friendship.mutualChats = (friendship.mutualChats || 0) + 1;
  await friendship.save();
  return friendship;
}

router.get('/', authRequired, async (req, res) => {
  const friendships = await Friendship.findAll({
    where: { [Op.or]: [{ userAId: req.user.id }, { userBId: req.user.id }] },
  });
  const ids = friendships.map((f) => (f.userAId === req.user.id ? f.userBId : f.userAId));
  const users = await User.findAll({ where: { id: ids } });
  const map = {};
  for (const u of users) map[u.id] = u;

  res.json({
    friends: friendships.map((f) => {
      const otherId = f.userAId === req.user.id ? f.userBId : f.userAId;
      const u = map[otherId];
      return {
        ...(u ? publicUser(u, []) : { id: otherId }),
        mutualChats: f.mutualChats,
        since: f.createdAt,
      };
    }),
  });
});

router.get('/suggestions', authRequired, async (req, res) => {
  const friendships = await Friendship.findAll({
    where: {
      [Op.or]: [{ userAId: req.user.id }, { userBId: req.user.id }],
    },
  });
  const friendIds = new Set(friendships.map((f) => (f.userAId === req.user.id ? f.userBId : f.userAId)));

  const follows = await Follow.findAll({
    where: { followerId: req.user.id },
    attributes: ['followedId'],
  });
  const followingIds = follows.map((f) => f.followedId);

  const excludes = [req.user.id, ...friendIds, ...followingIds];

  const candidates = await User.findAll({
    where: { id: { [Op.notIn]: excludes }, isBanned: false },
    order: [['lastSeenAt', 'DESC']],
    limit: 20,
  });

  const out = [];
  for (const u of candidates) {
    const count = await countMutualChats(req.user.id, u.id);
    if (count >= FRIEND_CHAT_THRESHOLD) {
      out.push({
        ...publicUser(u, []),
        mutualChats: count,
      });
    }
  }

  res.json({ suggestions: out, threshold: FRIEND_CHAT_THRESHOLD });
});

router.post('/:userId/auto-add', authRequired, async (req, res) => {
  const otherId = req.params.userId;
  if (otherId === req.user.id) {
    return res.status(400).json({ error: 'cannot_friend_self' });
  }
  const other = await User.findByPk(otherId);
  if (!other) return res.status(404).json({ error: 'not_found' });

  const count = await countMutualChats(req.user.id, otherId);
  if (count < FRIEND_CHAT_THRESHOLD) {
    return res.status(403).json({
      error: 'threshold_not_reached',
      required: FRIEND_CHAT_THRESHOLD,
      current: count,
    });
  }

  const friendship = await getOrCreateFriendship(req.user.id, otherId);
  res.json({
    ok: true,
    friendship: {
      otherId,
      mutualChats: friendship.mutualChats,
      since: friendship.createdAt,
    },
  });
});

router.delete('/:userId', authRequired, async (req, res) => {
  const otherId = req.params.userId;
  const [a, b] = friendKey(req.user.id, otherId);
  await Friendship.destroy({ where: { userAId: a, userBId: b } });
  res.json({ ok: true });
});

module.exports = { router, recordChat, FRIEND_CHAT_THRESHOLD };
