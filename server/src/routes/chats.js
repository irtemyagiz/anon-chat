const express = require('express');
const { Op } = require('sequelize');
const UserChat = require('../models/UserChat');
const User = require('../models/User');
const Message = require('../models/Message');
const { authRequired } = require('../middleware/auth');
const { publicUser } = require('./auth');

const router = express.Router();

const MAX_PINS = 3;

async function getPeerMap(peerIds) {
  if (peerIds.length === 0) return new Map();
  const users = await User.findAll({ where: { id: peerIds } });
  const map = new Map();
  for (const u of users) map.set(u.id, u);
  return map;
}

router.get('/', authRequired, async (req, res) => {
  try {
    const chats = await UserChat.findAll({
      where: {
        userId: req.user.id,
        deletedAt: null,
      },
      order: [
        ['pinnedAt', 'DESC NULLS LAST'],
        ['updatedAt', 'DESC'],
      ],
    });

    if (chats.length === 0) {
      return res.json({ chats: [], unreadTotal: 0 });
    }

    const peerIds = [...new Set(chats.map((c) => c.peerId))];
    const peerMap = await getPeerMap(peerIds);

    const enriched = [];
    let unreadTotal = 0;

    for (const chat of chats) {
      const peer = peerMap.get(chat.peerId);
      if (!peer) continue;

      const lastMsg = await Message.findOne({
        where: { roomId: chat.roomId },
        order: [['createdAt', 'DESC']],
      });

      const unreadCount = await Message.count({
        where: {
          roomId: chat.roomId,
          senderId: chat.peerId,
          createdAt: { [Op.gt]: chat.lastReadAt || new Date(0) },
        },
      });
      unreadTotal += unreadCount;

      enriched.push({
        peer: publicUser(peer, []),
        roomId: chat.roomId,
        lastMessage: lastMsg ? {
          content: lastMsg.content,
          senderId: lastMsg.senderId,
          createdAt: lastMsg.createdAt,
        } : null,
        pinnedAt: chat.pinnedAt,
        unreadCount,
        isPinned: !!chat.pinnedAt,
      });
    }

    res.json({ chats: enriched, unreadTotal });
  } catch (err) {
    console.error('[chats list]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/:peerId/pin', authRequired, async (req, res) => {
  try {
    const pinnedCount = await UserChat.count({
      where: { userId: req.user.id, pinnedAt: { [Op.not]: null } },
    });
    if (pinnedCount >= MAX_PINS) {
      return res.status(400).json({ error: 'max_pins_reached', limit: MAX_PINS });
    }
    const [chat, created] = await UserChat.findOrCreate({
      where: { userId: req.user.id, peerId: req.params.peerId },
      defaults: { roomId: `pending_${req.user.id}_${req.params.peerId}` },
    });
    chat.pinnedAt = new Date();
    chat.deletedAt = null;
    await chat.save();
    res.json({ ok: true, pinned: true, pinnedAt: chat.pinnedAt });
  } catch (err) {
    console.error('[chats pin]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/:peerId/unpin', authRequired, async (req, res) => {
  try {
    const chat = await UserChat.findOne({
      where: { userId: req.user.id, peerId: req.params.peerId },
    });
    if (!chat) return res.status(404).json({ error: 'not_found' });
    chat.pinnedAt = null;
    await chat.save();
    res.json({ ok: true, pinned: false });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.delete('/:peerId', authRequired, async (req, res) => {
  try {
    const chat = await UserChat.findOne({
      where: { userId: req.user.id, peerId: req.params.peerId },
    });
    if (!chat) return res.status(404).json({ error: 'not_found' });
    chat.deletedAt = new Date();
    chat.pinnedAt = null;
    await chat.save();
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/:peerId/read', authRequired, async (req, res) => {
  try {
    const chat = await UserChat.findOne({
      where: { userId: req.user.id, peerId: req.params.peerId },
    });
    if (!chat) return res.status(404).json({ error: 'not_found' });
    chat.lastReadAt = new Date();
    await chat.save();
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

router.post('/sync/:peerId/:roomId', authRequired, async (req, res) => {
  try {
    const [chat] = await UserChat.findOrCreate({
      where: { userId: req.user.id, peerId: req.params.peerId },
      defaults: { roomId: req.params.roomId },
    });
    if (chat.roomId !== req.params.roomId) {
      chat.roomId = req.params.roomId;
      await chat.save();
    }
    res.json({ ok: true, chat });
  } catch (err) {
    res.status(500).json({ error: 'server_error' });
  }
});

module.exports = router;