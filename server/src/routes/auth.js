const express = require('express');
const bcrypt = require('bcryptjs');
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

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const USERNAME_RE = /^[a-zA-Z0-9_]{3,30}$/;

router.post('/register', async (req, res) => {
  try {
    const { email, password, username, nickname } = req.body || {};
    if (!email || !EMAIL_RE.test(email)) {
      return res.status(400).json({ error: 'invalid_email' });
    }
    if (!password || password.length < 6) {
      return res.status(400).json({ error: 'weak_password' });
    }
    if (!username || !USERNAME_RE.test(username)) {
      return res.status(400).json({ error: 'invalid_username' });
    }
    if (!nickname || nickname.length < 1 || nickname.length > 20) {
      return res.status(400).json({ error: 'invalid_nickname' });
    }

    const normalizedEmail = email.toLowerCase().trim();
    const normalizedUsername = username.toLowerCase().trim();

    const existing = await User.findOne({
      where: { email: normalizedEmail },
    });
    if (existing) {
      return res.status(409).json({ error: 'email_taken' });
    }
    const existingUsername = await User.findOne({
      where: { username: normalizedUsername },
    });
    if (existingUsername) {
      return res.status(409).json({ error: 'username_taken' });
    }

    const passwordHash = await bcrypt.hash(password, 10);
    const user = await User.create({
      email: normalizedEmail,
      passwordHash,
      username: normalizedUsername,
      nickname: nickname.trim(),
      ageConfirmed: true,
      rulesAcceptedAt: new Date(),
    });

    const token = signToken({ sub: user.id });
    res.status(201).json({
      token,
      user: publicUser(user, []),
    });
  } catch (err) {
    console.error('[register]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body || {};
    if (!email || !password) {
      return res.status(400).json({ error: 'missing_credentials' });
    }

    const user = await User.findOne({
      where: { email: email.toLowerCase().trim() },
    });
    if (!user || !user.passwordHash) {
      return res.status(401).json({ error: 'invalid_credentials' });
    }

    const ok = await bcrypt.compare(password, user.passwordHash);
    if (!ok) {
      return res.status(401).json({ error: 'invalid_credentials' });
    }

    user.lastSeenAt = new Date();
    await user.save();

    const interestIds = await fetchInterestIds(user.id);
    const token = signToken({ sub: user.id });
    res.json({ token, user: publicUser(user, interestIds) });
  } catch (err) {
    console.error('[login]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/me', authRequired, async (req, res) => {
  const interestIds = await fetchInterestIds(req.user.id);
  res.json({ user: publicUser(req.user, interestIds) });
});

router.put('/me', authRequired, async (req, res) => {
  try {
    const {
      nickname,
      avatarColor,
      countryCode,
      ageConfirmed,
      rulesAcceptedAt,
      interestIds,
      bio,
      gender,
      age,
      anonymityEnabled,
      photoBase64,
    } = req.body || {};

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
    if (req.body?.avatarStyle !== undefined) {
      req.user.avatarStyle = String(req.body.avatarStyle).slice(0, 30);
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
    if (bio !== undefined) {
      if (bio && bio.length > 200) {
        return res.status(400).json({ error: 'bio_too_long' });
      }
      req.user.bio = bio || null;
    }
    if (gender !== undefined) {
      if (gender && !['male', 'female', 'other'].includes(gender)) {
        return res.status(400).json({ error: 'invalid_gender' });
      }
      req.user.gender = gender || null;
    }
    if (age !== undefined) {
      if (age !== null && (!Number.isInteger(age) || age < 18 || age > 99)) {
        return res.status(400).json({ error: 'invalid_age' });
      }
      req.user.age = age;
    }
    if (anonymityEnabled !== undefined) {
      req.user.anonymityEnabled = !!anonymityEnabled;
    }
    if (photoBase64 !== undefined) {
      if (photoBase64 && photoBase64.length > 2_800_000) {
        return res.status(400).json({ error: 'photo_too_large' });
      }
      req.user.photoBase64 = photoBase64 || null;
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
    console.error('[me PUT]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

function publicUser(u, interestIds = []) {
  const plusActive = u.isPlus && (!u.plusExpiresAt || new Date(u.plusExpiresAt) > new Date());
  return {
    id: u.id,
    email: u.email,
    username: u.username,
    nickname: u.nickname,
    avatarColor: u.avatarColor,
    avatarSeed: u.avatarSeed,
    avatarStyle: u.avatarStyle || 'adventurer',
    photoUrl: u.photoBase64 ? `data:image/jpeg;base64,${u.photoBase64}` : null,
    bio: u.bio,
    gender: u.gender,
    age: u.age,
    countryCode: u.countryCode,
    ageConfirmed: u.ageConfirmed,
    rulesAcceptedAt: u.rulesAcceptedAt,
    anonymityEnabled: u.anonymityEnabled,
    isPlus: plusActive,
    plusExpiresAt: u.plusExpiresAt,
    dailyShuffleCount: u.dailyShuffleCount,
    totalChats: u.totalChats,
    interestIds,
  };
}

module.exports = { router, publicUser };
