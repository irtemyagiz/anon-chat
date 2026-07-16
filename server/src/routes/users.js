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

async function getProfile(userId, viewerId) {
  const user = await User.findByPk(userId);
  if (!user) return null;
  const interestIds = (
    await UserInterest.findAll({ where: { userId }, attributes: ['interestId'] })
  ).map((r) => r.interestId);

  const [followerCount, followingCount, isFollowing, isFriend, mutualChats, followRecord] = await Promise.all([
    Follow.count({ where: { followedId: userId } }),
    Follow.count({ where: { followerId: userId } }),
    viewerId
      ? Follow.findOne({ where: { followerId: viewerId, followedId: userId } })
      : Promise.resolve(null),
    viewerId
      ? Friendship.findOne({
          where: {
            [Op.or]: [
              { userAId: viewerId, userBId: userId },
              { userAId: userId, userBId: viewerId },
            ],
          },
        })
      : Promise.resolve(null),
    viewerId
      ? Message.count({
          where: {
            senderId: viewerId,
          },
        })
      : Promise.resolve(0),
    viewerId
      ? Follow.findOne({
          where: { followerId: userId, followedId: viewerId },
        })
      : Promise.resolve(null),
  ]);

  const canSeeFollowers = viewerId === userId || (user.isPlus && (!user.plusExpiresAt || new Date(user.plusExpiresAt) > new Date()));

  return {
    profile: publicUser(user, interestIds),
    stats: {
      followerCount,
      followingCount,
      mutualChats,
    },
    viewerState: {
      isFollowing: !!isFollowing,
      isFollowedBy: !!followRecord,
      isFriend: !!isFriend,
      chatCountWithYou: mutualChats,
    },
    followInfo: canSeeFollowers ? { followers: followerCount } : null,
  };
}

router.get('/:id', authRequired, async (req, res) => {
  const data = await getProfile(req.params.id, req.user.id);
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

  const [follow, created] = await Follow.findOrCreate({
    where: { followerId: req.user.id, followedId: targetId },
  });

  const isFollowing = !!follow;
  res.json({ ok: true, isFollowing });
});

router.delete('/:id/follow', authRequired, async (req, res) => {
  await Follow.destroy({
    where: { followerId: req.user.id, followedId: req.params.id },
  });
  res.json({ ok: true, isFollowing: false });
});

router.get('/:id/followers', authRequired, async (req, res) => {
  const user = await User.findByPk(req.params.id);
  if (!user) return res.status(404).json({ error: 'not_found' });

  const isOwner = req.user.id === req.params.id;
  const isPlus = req.user.isPlus && (!req.user.plusExpiresAt || new Date(req.user.plusExpiresAt) > new Date());
  if (!isOwner && !isPlus) {
    return res.status(403).json({ error: 'plus_required' });
  }

  const followers = await Follow.findAll({
    where: { followedId: req.params.id },
    order: [['createdAt', 'DESC']],
  });
  const users = await User.findAll({
    where: { id: followers.map((f) => f.followerId) },
  });
  const interestIdsMap = {};
  for (const u of users) {
    const rows = await UserInterest.findAll({ where: { userId: u.id }, attributes: ['interestId'] });
    interestIdsMap[u.id] = rows.map((r) => r.interestId);
  }
  res.json({
    followers: users.map((u) => ({
      ...publicUser(u, interestIdsMap[u.id] || []),
      followedAt: followers.find((f) => f.followerId === u.id)?.createdAt,
    })),
  });
});

router.get('/:id/following', authRequired, async (req, res) => {
  const following = await Follow.findAll({
    where: { followerId: req.params.id },
  });
  const users = await User.findAll({
    where: { id: following.map((f) => f.followedId) },
  });
  res.json({
    following: users.map((u) => publicUser(u, [])),
  });
});

module.exports = router;
