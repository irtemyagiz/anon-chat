const express = require('express');
const jwt = require('jsonwebtoken');
const { Op, fn, col, literal } = require('sequelize');
const User = require('../models/User');
const Follow = require('../models/Follow');
const Friendship = require('../models/Friendship');
const Message = require('../models/Message');
const Report = require('../models/Report');
const Ban = require('../models/Ban');
const Photo = require('../models/Photo');
const UserChat = require('../models/UserChat');
const { sequelize } = require('../models');
const { publicUser } = require('./auth');

const router = express.Router();

const ADMIN_EMAIL = 'admin@admin.com';
const ADMIN_PASSWORD = '20042005Qq!q';

function adminAuthRequired(req, res, next) {
  const header = req.headers.authorization || '';
  const token = header.startsWith('Bearer ') ? header.slice(7) : null;
  if (!token) return res.status(401).json({ error: 'admin_auth_required' });
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET || 'dev-only-change-me');
    if (payload.role !== 'admin') return res.status(403).json({ error: 'not_admin' });
    req.admin = payload;
    next();
  } catch {
    return res.status(401).json({ error: 'invalid_token' });
  }
}

router.post('/login', (req, res) => {
  const { email, password } = req.body || {};
  if (email === ADMIN_EMAIL && password === ADMIN_PASSWORD) {
    const token = jwt.sign(
      { sub: 'admin', role: 'admin', email },
      process.env.JWT_SECRET || 'dev-only-change-me',
      { expiresIn: '7d' }
    );
    return res.json({ token, admin: { email } });
  }
  return res.status(401).json({ error: 'invalid_credentials' });
});

router.get('/stats', adminAuthRequired, async (req, res) => {
  try {
    const fiveMinAgo = new Date(Date.now() - 5 * 60 * 1000);
    const oneDayAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);

    const [
      totalUsers,
      plusUsers,
      onlineUsers,
      newToday,
      bannedUsers,
      totalMessages,
      totalRooms,
      totalFollows,
      totalFriendships,
      totalPhotos,
      reportsLast24h,
      activeBans,
      pendingReports,
    ] = await Promise.all([
      User.count(),
      User.count({ where: { isPlus: true, plusExpiresAt: { [Op.gt]: new Date() } } }),
      User.count({ where: { lastSeenAt: { [Op.gt]: fiveMinAgo } } }),
      User.count({ where: { createdAt: { [Op.gt]: oneDayAgo } } }),
      User.count({ where: { isBanned: true } }),
      Message.count(),
      Message.count({ attributes: ['roomId'], group: ['roomId'] }).then((arr) => arr.length),
      Follow.count({ where: { status: 'accepted' } }),
      Friendship.count(),
      Photo.count(),
      Report.count({ where: { createdAt: { [Op.gt]: oneDayAgo } } }),
      Ban.count({ where: { expiresAt: { [Op.gt]: new Date() } } }),
      Report.count({ where: { reviewed: false } }),
    ]);

    const byGender = await User.findAll({
      attributes: ['gender', [fn('COUNT', col('id')), 'count']],
      group: ['gender'],
    });

    res.json({
      totalUsers,
      plusUsers,
      onlineUsers,
      newToday,
      bannedUsers,
      totalMessages,
      totalRooms,
      totalFollows,
      totalFriendships,
      totalPhotos,
      reportsLast24h,
      activeBans,
      pendingReports,
      genderBreakdown: byGender.map((g) => ({ gender: g.gender, count: Number(g.dataValues.count) })),
    });
  } catch (err) {
    console.error('[admin stats]', err);
    res.status(500).json({ error: 'server_error', message: err.message });
  }
});

async function enrichReports(reports) {
  const reporterIds = [...new Set(reports.map((r) => r.reporterId))];
  const reportedIds = [...new Set(reports.map((r) => r.reportedId))];
  const userIds = [...new Set([...reporterIds, ...reportedIds])];
  const roomIds = [...new Set(reports.map((r) => r.roomId).filter(Boolean))];

  const users = await User.findAll({ where: { id: userIds } });
  const userMap = {};
  for (const u of users) userMap[u.id] = publicUser(u, []);

  const messages = await Message.findAll({
    where: { roomId: roomIds },
    order: [['createdAt', 'DESC']],
  });
  const messageByRoom = {};
  for (const m of messages) {
    if (!messageByRoom[m.roomId]) messageByRoom[m.roomId] = m;
  }

  return reports.map((r) => ({
    ...r.dataValues,
    reporter: userMap[r.reporterId] || null,
    reported: userMap[r.reportedId] || null,
    lastMessage: messageByRoom[r.roomId]
      ? {
          id: messageByRoom[r.roomId].id,
          content: messageByRoom[r.roomId].content,
          senderId: messageByRoom[r.roomId].senderId,
          createdAt: messageByRoom[r.roomId].createdAt,
          flagged: messageByRoom[r.roomId].flagged,
        }
      : null,
  }));
}

router.get('/reports', adminAuthRequired, async (req, res) => {
  try {
    const where = {};
    if (req.query.pending === 'true') where.reviewed = false;
    const reports = await Report.findAll({
      where,
      order: [['createdAt', 'DESC']],
      limit: 100,
    });
    const enriched = await enrichReports(reports);
    res.json({ reports: enriched });
  } catch (err) {
    console.error('[admin reports]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.delete('/reports/:id', adminAuthRequired, async (req, res) => {
  try {
    const report = await Report.findByPk(req.params.id);
    if (!report) return res.status(404).json({ error: 'not_found' });
    await report.destroy();
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/reports/:id/ban', adminAuthRequired, async (req, res) => {
  try {
    const report = await Report.findByPk(req.params.id);
    if (!report) return res.status(404).json({ error: 'not_found' });
    const user = await User.findByPk(report.reportedId);
    if (!user) return res.status(404).json({ error: 'user_not_found' });

    const expiresAt = new Date(Date.now() + 24 * 60 * 60 * 1000);
    await Ban.create({
      deviceId: user.deviceId || `admin-ban-${user.id}`,
      userId: user.id,
      reason: `admin_action: ${report.reason}`,
      expiresAt,
    });
    user.isBanned = true;
    await user.save();

    report.reviewed = true;
    await report.save();

    res.json({ ok: true, banned: true });
  } catch (err) {
    console.error('[admin ban]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/reports/:id/dismiss', adminAuthRequired, async (req, res) => {
  try {
    const report = await Report.findByPk(req.params.id);
    if (!report) return res.status(404).json({ error: 'not_found' });
    report.reviewed = true;
    await report.save();
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/messages/recent', adminAuthRequired, async (req, res) => {
  try {
    const limit = Math.min(20, parseInt(req.query.limit || '5', 10));
    const messages = await Message.findAll({
      order: [['createdAt', 'DESC']],
      limit,
    });
    const senderIds = [...new Set(messages.map((m) => m.senderId))];
    const users = await User.findAll({ where: { id: senderIds } });
    const userMap = {};
    for (const u of users) userMap[u.id] = publicUser(u, []);
    res.json({
      messages: messages.map((m) => ({
        id: m.id,
        roomId: m.roomId,
        content: m.content,
        senderId: m.senderId,
        flagged: m.flagged,
        createdAt: m.createdAt,
        sender: userMap[m.senderId] || null,
      })),
    });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/users', adminAuthRequired, async (req, res) => {
  try {
    const page = parseInt(req.query.page || '1', 10);
    const limit = Math.min(100, parseInt(req.query.limit || '50', 10));
    const search = req.query.q || '';
    const where = {};
    if (search) {
      where[Op.or] = [
        { email: { [Op.iLike]: `%${search}%` } },
        { username: { [Op.iLike]: `%${search}%` } },
        { nickname: { [Op.iLike]: `%${search}%` } },
      ];
    }
    if (req.query.banned === 'true') where.isBanned = true;
    if (req.query.plus === 'true') {
      where.isPlus = true;
      where.plusExpiresAt = { [Op.gt]: new Date() };
    }

    const { count, rows } = await User.findAndCountAll({
      where,
      order: [['createdAt', 'DESC']],
      limit,
      offset: (page - 1) * limit,
    });

    res.json({
      total: count,
      page,
      users: rows.map((u) => ({
        ...publicUser(u, []),
        email: u.email,
        isBanned: u.isBanned,
        lastSeenAt: u.lastSeenAt,
        createdAt: u.createdAt,
      })),
    });
  } catch (err) {
    console.error('[admin users]', err);
    res.status(500).json({ error: 'server_error', message: err.message });
  }
});

router.get('/users/:id', adminAuthRequired, async (req, res) => {
  const u = await User.findByPk(req.params.id);
  if (!u) return res.status(404).json({ error: 'not_found' });
  res.json({
    user: {
      ...publicUser(u, []),
      email: u.email,
      username: u.username,
      isBanned: u.isBanned,
      lastSeenAt: u.lastSeenAt,
      createdAt: u.createdAt,
    },
  });
});

router.patch('/users/:id', adminAuthRequired, async (req, res) => {
  const u = await User.findByPk(req.params.id);
  if (!u) return res.status(404).json({ error: 'not_found' });

  const { isBanned, isPlus, plusDays } = req.body || {};
  if (isBanned !== undefined) {
    u.isBanned = !!isBanned;
    if (isBanned) {
      await Ban.create({
        deviceId: u.deviceId || `admin-ban-${u.id}`,
        userId: u.id,
        reason: 'admin_action',
        expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000),
      });
    } else {
      await Ban.destroy({ where: { userId: u.id } });
    }
  }
  if (isPlus !== undefined) {
    u.isPlus = !!isPlus;
    if (u.isPlus) {
      u.plusExpiresAt = new Date(Date.now() + (plusDays || 30) * 24 * 60 * 60 * 1000);
    } else {
      u.plusExpiresAt = null;
    }
  }
  await u.save();
  res.json({ ok: true, user: publicUser(u, []) });
});

router.delete('/users/:id', adminAuthRequired, async (req, res) => {
  const u = await User.findByPk(req.params.id);
  if (!u) return res.status(404).json({ error: 'not_found' });
  await Promise.all([
    Follow.destroy({ where: { [Op.or]: [{ followerId: u.id }, { followedId: u.id }] } }),
    Friendship.destroy({ where: { [Op.or]: [{ userAId: u.id }, { userBId: u.id }] } }),
    Message.destroy({ where: { senderId: u.id } }),
    Ban.destroy({ where: { userId: u.id } }),
    Photo.destroy({ where: { [Op.or]: [{ senderId: u.id }, { recipientId: u.id }] } }),
    Report.destroy({ where: { [Op.or]: [{ reporterId: u.id }, { reportedId: u.id }] } }),
    UserChat.destroy({ where: { [Op.or]: [{ userId: u.id }, { peerId: u.id }] } }),
  ]);
  await u.destroy();
  res.json({ ok: true });
});

module.exports = router;