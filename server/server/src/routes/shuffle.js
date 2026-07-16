const express = require('express');
const { Op } = require('sequelize');
const User = require('../models/User');
const Follow = require('../models/Follow');
const { UserInterest } = require('../models/Interest');
const { authRequired } = require('../middleware/auth');
const { publicUser } = require('./auth');

const router = express.Router();

const DAILY_FREE_LIMIT = 50;

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
      where: { followerId: req.user.id },
      attributes: ['followedId'],
    });
    const followingIds = follows.map((f) => f.followedId);
    const excluded = [req.user.id, ...followingIds];

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

    const pool = await User.findAll({
      where,
      order: [['lastSeenAt', 'DESC']],
      limit: 30,
    });

    if (pool.length === 0) {
      return res.json({ user: null, remaining: null });
    }

    let ranked = pool;
    if (myInterestIds.length > 0) {
      ranked = pool.slice().sort((a, b) => {
        return Math.random() - 0.5;
      });
      const withOverlap = [];
      const withoutOverlap = [];
      for (const u of ranked) {
        const uInterests = await UserInterest.findAll({
          where: { userId: u.id },
          attributes: ['interestId'],
        });
        const uIds = uInterests.map((r) => r.interestId);
        const overlap = uIds.filter((id) => myInterestIds.includes(id)).length;
        if (overlap > 0) withOverlap.push({ user: u, overlap });
        else withoutOverlap.push(u);
      }
      withOverlap.sort((a, b) => b.overlap - a.overlap);
      ranked = [...withOverlap.map((x) => x.user), ...withoutOverlap];
    } else {
      ranked = pool.slice().sort(() => Math.random() - 0.5);
    }

    const pick = ranked[0];
    const interestIds = (
      await UserInterest.findAll({ where: { userId: pick.id }, attributes: ['interestId'] })
    ).map((r) => r.interestId);

    req.user.dailyShuffleCount += 1;
    await req.user.save();

    res.json({
      user: publicUser(pick, interestIds),
      remaining: isPlus(req.user) ? null : Math.max(0, DAILY_FREE_LIMIT - req.user.dailyShuffleCount),
      isPlus: isPlus(req.user),
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

router.post('/upgrade', authRequired, async (req, res) => {
  req.user.isPlus = true;
  req.user.plusExpiresAt = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);
  await req.user.save();
  res.json({ ok: true, isPlus: true, expiresAt: req.user.plusExpiresAt });
});

router.post('/downgrade', authRequired, async (req, res) => {
  req.user.isPlus = false;
  req.user.plusExpiresAt = null;
  await req.user.save();
  res.json({ ok: true, isPlus: false });
});

module.exports = router;
