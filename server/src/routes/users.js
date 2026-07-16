const express = require('express');
const { Op } = require('sequelize');
const User = require('../models/User');
const Follow = require('../models/Follow');
const Friendship = require('../models/Friendship');
const Message = require('../models/Message');
const { UserInterest } = require('../models/Interest');
const { authRequired } = require('../middleware/auth');
const { publicUser } = require('./auth');

const router = express.Router();

function isPlus(user) {
  return user.isPlus && (!user.plusExpiresAt || new Date(user.plusExpiresAt) > new Date());
}

async function fetchPublicBatch(users, viewerIsPlus, viewerId) {
  const ids = users.map((u) => u.id);
  const interestRows = await UserInterest.findAll({
    where: { userId: ids },
    attributes: ['userId', 'interestId'],
  });
  const map = {};
  for (const r of interestRows) {
    if (!map[r.userId]) map[r.userId] = [];
    map[r.userId].push(r.interestId);
  }
  return users.map((u) => {
    const out = publicUser(u, map[u.id] || []);
    out.isPlus = isPlus(u);
    return out;
  });
}

async function getProfile(userId, viewerId, viewerIsPlus) {
  const user = await User.findByPk(userId);
  if (!user) return null;
  const interestIds = (
    await UserInterest.findAll({ where: { userId }, attributes: ['interestId'] })
  ).map((r) => r.interestId);

  const [followerCount, followingCount, viewerFollow, viewerReverseFollow] = await Promise.all([
    Follow.count({ where: { followedId: userId, status: 'accepted' } }),
    Follow.count({ where: { followerId: userId, status: 'accepted' } }),
    viewerId
      ? Follow.findOne({ where: { followerId: viewerId, followedId: userId } })
      : Promise.resolve(null),
    viewerId
      ? Follow.findOne({ where: { followerId: userId, followedId: viewerId, status: 'accepted' } })
      : Promise.resolve(null),
  ]);

  let chatCountWithYou = 0;
  if (viewerId) {
    const myRooms = await Message.findAll({
      where: { senderId: viewerId },
      attributes: ['roomId'],
      group: ['roomId'],
    });
    const roomIds = myRooms.map((r) => r.roomId);
    for (const rid of roomIds) {
      const senders = await Message.findAll({
        where: { roomId: rid },
        attributes: ['senderId'],
        group: ['senderId'],
      });
      if (senders.map((s) => s.senderId).includes(userId)) {
        chatCountWithYou += 1;
      }
    }
  }

  const viewerState = {
    isFollowing: viewerFollow?.status === 'accepted',
    followStatus: viewerFollow?.status || null,
    isFollowedBy: !!viewerReverseFollow,
    chatCountWithYou,
  };

  const showFollowers = viewerId === userId || viewerIsPlus;

  return {
    profile: publicUser(user, interestIds),
    stats: { followerCount, followingCount, chatCountWithYou },
    viewerState,
    followInfo: showFollowers ? { followers: followerCount } : null,
  };
}

router.get('/:id', authRequired, async (req, res) => {
  const data = await getProfile(req.params.id, req.user.id, isPlus(req.user));
  if (!data) return res.status(404).json({ error: 'not_found' });
  res.json(data);
});

router.post('/:id/follow', authRequired, async (req, res) => {
  const targetId = req.params.id;
  if (targetId === req.user.id) {
    return res.status(400).json({ error: 'cannot_follow_self' });
  }
  const target = await User.findByPk(targetId);
  if (!target) return res.status(404).json({ error: 'not_found' });

  const existing = await Follow.findOne({
    where: { followerId: req.user.id, followedId: targetId },
  });
  let follow;
  let created = false;
  if (!existing) {
    follow = await Follow.create({
      followerId: req.user.id,
      followedId: targetId,
      status: 'pending',
    });
    created = true;
  } else if (existing.status === 'rejected') {
    existing.status = 'pending';
    await existing.save();
    follow = existing;
    created = true;
  } else {
    follow = existing;
  }

  res.json({
    ok: true,
    status: follow.status,
    isFollowing: follow.status === 'accepted',
    created,
  });
});

router.post('/:id/follow/accept', authRequired, async (req, res) => {
  const requesterId = req.params.id;
  if (requesterId === req.user.id) {
    return res.status(400).json({ error: 'cannot_accept_self' });
  }
  const existing = await Follow.findOne({
    where: { followerId: requesterId, followedId: req.user.id },
  });
  if (!existing) return res.status(404).json({ error: 'no_request' });
  if (existing.status !== 'pending') {
    return res.status(400).json({ error: 'not_pending' });
  }
  existing.status = 'accepted';
  await existing.save();
  res.json({ ok: true, status: 'accepted' });
});

router.post('/:id/follow/reject', authRequired, async (req, res) => {
  const requesterId = req.params.id;
  const existing = await Follow.findOne({
    where: { followerId: requesterId, followedId: req.user.id },
  });
  if (!existing) return res.status(404).json({ error: 'no_request' });
  existing.status = 'rejected';
  await existing.save();
  res.json({ ok: true, status: 'rejected' });
});

router.delete('/:id/follow', authRequired, async (req, res) => {
  await Follow.destroy({
    where: { followerId: req.user.id, followedId: req.params.id },
  });
  res.json({ ok: true, isFollowing: false });
});

router.get('/me/requests/incoming', authRequired, async (req, res) => {
  const viewerPlus = isPlus(req.user);
  const total = await Follow.count({
    where: { followedId: req.user.id, status: 'pending' },
  });

  if (!viewerPlus) {
    return res.json({
      count: total,
      blurred: true,
      message: 'Plus üyeler takip isteklerini görebilir',
      requests: [],
    });
  }

  const requests = await Follow.findAll({
    where: { followedId: req.user.id, status: 'pending' },
    order: [['createdAt', 'DESC']],
    limit: 50,
  });
  const users = await User.findAll({
    where: { id: requests.map((r) => r.followerId) },
  });
  const publicUsers = await fetchPublicBatch(users, true, req.user.id);
  res.json({
    count: total,
    blurred: false,
    requests: publicUsers,
  });
});

router.get('/me/followers', authRequired, async (req, res) => {
  const viewerPlus = isPlus(req.user);
  const total = await Follow.count({
    where: { followedId: req.user.id, status: 'accepted' },
  });

  if (!viewerPlus) {
    return res.json({
      count: total,
      blurred: true,
      message: 'Plus üyeler takipçilerini görebilir',
      followers: [],
    });
  }

  const followers = await Follow.findAll({
    where: { followedId: req.user.id, status: 'accepted' },
    order: [['createdAt', 'DESC']],
    limit: 100,
  });
  const users = await User.findAll({
    where: { id: followers.map((f) => f.followerId) },
  });
  const publicUsers = await fetchPublicBatch(users, true, req.user.id);
  res.json({
    count: total,
    blurred: false,
    followers: publicUsers,
  });
});

router.get('/me/following', authRequired, async (req, res) => {
  const following = await Follow.findAll({
    where: { followerId: req.user.id, status: 'accepted' },
    order: [['createdAt', 'DESC']],
  });
  const users = await User.findAll({
    where: { id: following.map((f) => f.followedId) },
  });
  const publicUsers = await fetchPublicBatch(users, isPlus(req.user), req.user.id);
  res.json({
    following: publicUsers.map((u) => ({
      ...u,
      followStatus: 'accepted',
    })),
  });
});

router.get('/me/pending-outgoing', authRequired, async (req, res) => {
  const pending = await Follow.findAll({
    where: { followerId: req.user.id, status: 'pending' },
  });
  const users = await User.findAll({
    where: { id: pending.map((p) => p.followedId) },
  });
  res.json({
    pending: users.map((u) => ({
      ...publicUser(u, []),
      followStatus: 'pending',
    })),
  });
});

router.get('/:id/followers', authRequired, async (req, res) => {
  const user = await User.findByPk(req.params.id);
  if (!user) return res.status(404).json({ error: 'not_found' });

  const isOwner = req.user.id === req.params.id;
  const viewerPlus = isPlus(req.user);
  if (!isOwner && !viewerPlus) {
    return res.status(403).json({ error: 'plus_required' });
  }

  const followers = await Follow.findAll({
    where: { followedId: req.params.id, status: 'accepted' },
    order: [['createdAt', 'DESC']],
  });
  const users = await User.findAll({
    where: { id: followers.map((f) => f.followerId) },
  });
  const publicUsers = await fetchPublicBatch(users, true, req.user.id);
  res.json({
    followers: publicUsers,
  });
});

router.get('/:id/following', authRequired, async (req, res) => {
  const following = await Follow.findAll({
    where: { followerId: req.params.id, status: 'accepted' },
  });
  const users = await User.findAll({
    where: { id: following.map((f) => f.followedId) },
  });
  const publicUsers = await fetchPublicBatch(users, isPlus(req.user), req.user.id);
  res.json({
    following: publicUsers,
  });
});

module.exports = router;