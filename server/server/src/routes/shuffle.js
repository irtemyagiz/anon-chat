const express = require('express');
const { Op } = require('sequelize');
const User = require('../models/User');
const Follow = require('../models/Follow');
const { UserInterest } = require('../models/Interest');
const { authRequired } = require('../middleware/auth');
const { publicUser } = require('./auth');

const router = express.Router();

const DAILY_FREE_LIMIT = 50;

const PLUS_TIERS = {
  '1d': { days: 1, label: '1 Gün', price: 9.99 },
  '1w': { days: 7, label: '1 Hafta', price: 24.99 },
  '1m': { days: 30, label: '1 Ay', price: 49.99 },
};

function tierMs(tier) {
  const t = PLUS_TIERS[tier];
  return t ? t.days * 24 * 60 * 60 * 1000 : 0;
}

async function resetIfNeeded(user) {
  const now = new Date();
  const last = user.lastShuffleResetAt ? new Date(user.lastShuffleResetAt) : new Date(0);
  const isSameDay =
    last.getUTCFullYear() === now.getUTCFullYear() &&
    last.getUTCMonth() === now.getUTCMonth() &&
    last.getUTCDate() === now.getUTCDate();
  if (!isSameDay) {
    user.dailyShuffleCount = 0;
    user.lastShuffleResetAt = now;
    await user.save();
  }
}

function isPlus(user) {
  return user.isPlus && (!user.plusExpiresAt || new Date(user.plusExpiresAt) > new Date());
}

router.get('/next', authRequired, async (req, res) => {
  try {
    await resetIfNeeded(req.user);

    if (!isPlus(req.user) && req.user.dailyShuffleCount >= DAILY_FREE_LIMIT) {
      return res.status(403).json({
        error: 'daily_limit_reached',
        limit: DAILY_FREE_LIMIT,
        remaining: 0,
        plusRequired: true,
      });
    }

    const myInterests = await UserInterest.findAll({
      where: { userId: req.user.id },
      attributes: ['interestId'],
    });
    const myInterestIds = myInterests.map((r) => r.interestId);

    const follows = await Follow.findAll({
      where: { followerId: req.user.id, status: 'accepted' },
      attributes: ['followedId'],
    });
    const followingIds = follows.map((f) => f.followedId);
    const pendingFollows = await Follow.findAll({
      where: { followerId: req.user.id, status: 'pending' },
      attributes: ['followedId'],
    });
    const excluded = [
      req.user.id,
      ...followingIds,
      ...pendingFollows.map((f) => f.followedId),
    ];

    const genderFilter = req.query.gender || null;
    const countryFilter = req.query.country || null;

    const where = {
      id: { [Op.notIn]: excluded },
      ageConfirmed: true,
      isBanned: false,
    };
    if (genderFilter && ['male', 'female', 'other'].includes(genderFilter)) {
      where.gender = genderFilter;
    }
    if (countryFilter && countryFilter.length === 2) {
      where.countryCode = countryFilter;
    }

    const limit = parseInt(req.query.limit || '30', 10);
    const pool = await User.findAll({
      where,
      order: [['lastSeenAt', 'DESC']],
      limit: Math.min(50, Math.max(1, limit)),
    });

    req.user.dailyShuffleCount += 1;
    await req.user.save();

    let users = pool.map((u) => ({ ...u.dataValues, isPlus: isPlus(u) }));
    if (myInterestIds.length > 0 && users.length > 0) {
      const interestRows = await UserInterest.findAll({
        where: { userId: users.map((u) => u.id) },
        attributes: ['userId', 'interestId'],
      });
      const userMap = {};
      for (const r of interestRows) {
        if (!userMap[r.userId]) userMap[r.userId] = [];
        userMap[r.userId].push(r.interestId);
      }
      users.sort((a, b) => {
        const aOverlap = (userMap[a.id] || []).filter((id) => myInterestIds.includes(id)).length;
        const bOverlap = (userMap[b.id] || []).filter((id) => myInterestIds.includes(id)).length;
        return bOverlap - aOverlap;
      });
    }

    const interestMap = {};
    const userIds = users.map((u) => u.id);
    const interestRows = await UserInterest.findAll({
      where: { userId: userIds },
      attributes: ['userId', 'interestId'],
    });
    for (const r of interestRows) {
      if (!interestMap[r.userId]) interestMap[r.userId] = [];
      interestMap[r.userId].push(r.interestId);
    }

    const enriched = users.map((u) => ({
      ...publicUser(u, interestMap[u.id] || []),
      isPlus: isPlus(u),
    }));

    res.json({
      users: enriched,
      remaining: isPlus(req.user) ? null : Math.max(0, DAILY_FREE_LIMIT - req.user.dailyShuffleCount),
      isPlus: isPlus(req.user),
      limit: isPlus(req.user) ? null : DAILY_FREE_LIMIT,
    });
  } catch (err) {
    console.error('[shuffle next]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/status', authRequired, async (req, res) => {
  await resetIfNeeded(req.user);
  res.json({
    isPlus: isPlus(req.user),
    used: req.user.dailyShuffleCount,
    limit: isPlus(req.user) ? null : DAILY_FREE_LIMIT,
    remaining: isPlus(req.user) ? null : Math.max(0, DAILY_FREE_LIMIT - req.user.dailyShuffleCount),
  });
});

router.get('/plus/info', authRequired, async (req, res) => {
  const plusActive = isPlus(req.user);
  const expiresAt = plusActive ? req.user.plusExpiresAt : null;
  const remainingMs = expiresAt ? new Date(expiresAt).getTime() - Date.now() : 0;
  const remainingDays = Math.max(0, Math.ceil(remainingMs / (24 * 60 * 60 * 1000)));

  res.json({
    isPlus: plusActive,
    expiresAt,
    remainingMs,
    remainingDays,
    tiers: Object.entries(PLUS_TIERS).map(([id, t]) => ({ id, ...t })),
  });
});

router.post('/upgrade', authRequired, async (req, res) => {
  const { tier = '1m' } = req.body || {};
  const ms = tierMs(tier);
  if (!ms) return res.status(400).json({ error: 'invalid_tier' });

  const now = Date.now();
  const currentExpires = req.user.plusExpiresAt && new Date(req.user.plusExpiresAt).getTime() > now
    ? new Date(req.user.plusExpiresAt).getTime()
    : now;

  req.user.isPlus = true;
  req.user.plusExpiresAt = new Date(currentExpires + ms);
  await req.user.save();

  res.json({
    ok: true,
    isPlus: true,
    expiresAt: req.user.plusExpiresAt,
    tier,
    tierLabel: PLUS_TIERS[tier].label,
  });
});

router.post('/downgrade', authRequired, async (req, res) => {
  req.user.isPlus = false;
  req.user.plusExpiresAt = null;
  await req.user.save();
  res.json({ ok: true, isPlus: false });
});

module.exports = router;