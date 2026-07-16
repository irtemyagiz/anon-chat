const express = require('express');
const { v4: uuidv4 } = require('uuid');
const User = require('../models/User');
const { UserInterest } = require('../models/Interest');
const { signToken } = require('../lib/jwt');
const { authRequired } = require('../middleware/auth');

const router = express.Router();

async function fetchInterestIds(userId) {
  const rows = await UserInterest.findAll({
    where: { userId },
    attributes: ['interestId'],
  });
  return rows.map((r) => r.interestId);
}

router.post('/device', async (req, res) => {
  try {
    const { deviceId } = req.body || {};
    if (!deviceId || typeof deviceId !== 'string' || deviceId.length < 8) {
      return res.status(400).json({ error: 'invalid_device_id' });
    }

    let user = await User.findOne({ where: { deviceId } });

    if (!user) {
      user = await User.create({
        deviceId,
        nickname: `Anon${Math.floor(1000 + Math.random() * 9000)}`,
      });
    }

    user.lastSeenAt = new Date();
    await user.save();

    const interestIds = await fetchInterestIds(user.id);
    const token = signToken({ sub: user.id });
    res.json({
      token,
      user: publicUser(user, interestIds),
    });
  } catch (err) {
    console.error('[auth/device]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/me', authRequired, async (req, res) => {
  const interestIds = await fetchInterestIds(req.user.id);
  res.json({ user: publicUser(req.user, interestIds) });
});

router.put('/me', authRequired, async (req, res) => {
  try {
    const { nickname, avatarColor, countryCode, ageConfirmed, rulesAcceptedAt, interestIds } = req.body || {};

    if (nickname !== undefined) {
      if (typeof nickname !== 'string' || nickname.length < 1 || nickname.length > 20) {
        return res.status(400).json({ error: 'invalid_nickname' });
      }
      req.user.nickname = nickname.trim();
    }
    if (avatarColor !== undefined) {
      if (!/^#[0-9A-Fa-f]{6}$/.test(avatarColor)) {
        return res.status(400).json({ error: 'invalid_color' });
      }
      req.user.avatarColor = avatarColor;
    }
    if (countryCode !== undefined) {
      req.user.countryCode = countryCode;
    }
    if (ageConfirmed !== undefined) {
      req.user.ageConfirmed = !!ageConfirmed;
    }
    if (rulesAcceptedAt !== undefined) {
      req.user.rulesAcceptedAt = new Date(rulesAcceptedAt);
    }

    await req.user.save();

    if (Array.isArray(interestIds)) {
      await UserInterest.destroy({ where: { userId: req.user.id } });
      if (interestIds.length > 0) {
        const rows = interestIds
          .filter((id) => Number.isInteger(id))
          .map((interestId) => ({ userId: req.user.id, interestId }));
        await UserInterest.bulkCreate(rows);
      }
    }

    const finalInterestIds = await fetchInterestIds(req.user.id);
    res.json({ user: publicUser(req.user, finalInterestIds) });
  } catch (err) {
    console.error('[auth/me PUT]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

function publicUser(u, interestIds = []) {
  return {
    id: u.id,
    nickname: u.nickname,
    avatarColor: u.avatarColor,
    avatarSeed: u.avatarSeed,
    countryCode: u.countryCode,
    ageConfirmed: u.ageConfirmed,
    rulesAcceptedAt: u.rulesAcceptedAt,
    totalChats: u.totalChats,
    interestIds,
  };
}

module.exports = router;
