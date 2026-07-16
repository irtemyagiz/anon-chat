const express = require('express');
const { Op } = require('sequelize');
const Photo = require('../models/Photo');
const User = require('../models/User');
const { authRequired } = require('../middleware/auth');

const router = express.Router();

function isPlus(user) {
  return user.isPlus && (!user.plusExpiresAt || new Date(user.plusExpiresAt) > new Date());
}

router.post('/upload', authRequired, async (req, res) => {
  try {
    const { recipientId, contentBase64, isOneTime = false } = req.body || {};
    if (!recipientId || !contentBase64) {
      return res.status(400).json({ error: 'missing_fields' });
    }
    if (typeof contentBase64 !== 'string' || contentBase64.length > 2_800_000) {
      return res.status(400).json({ error: 'photo_too_large' });
    }

    if (isOneTime && !isPlus(req.user)) {
      return res.status(403).json({ error: 'plus_required_for_one_time' });
    }

    const recipient = await User.findByPk(recipientId);
    if (!recipient) return res.status(404).json({ error: 'recipient_not_found' });

    const photo = await Photo.create({
      senderId: req.user.id,
      recipientId,
      contentBase64,
      isOneTime,
      expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
    });

    res.status(201).json({
      id: photo.id,
      senderId: photo.senderId,
      recipientId: photo.recipientId,
      isOneTime: photo.isOneTime,
      createdAt: photo.createdAt,
      expiresAt: photo.expiresAt,
    });
  } catch (err) {
    console.error('[photo upload]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

router.get('/:id', authRequired, async (req, res) => {
  const photo = await Photo.findByPk(req.params.id);
  if (!photo) return res.status(404).json({ error: 'not_found' });
  if (photo.recipientId !== req.user.id && photo.senderId !== req.user.id) {
    return res.status(403).json({ error: 'forbidden' });
  }
  if (photo.expiresAt && new Date(photo.expiresAt) < new Date()) {
    return res.status(410).json({ error: 'expired' });
  }

  if (photo.isOneTime) {
    if (photo.recipientId !== req.user.id) {
      return res.status(403).json({ error: 'forbidden' });
    }
    if (!photo.viewedAt) {
      photo.viewedAt = new Date();
      await photo.save();
      setTimeout(async () => {
        try {
          await Photo.destroy({ where: { id: photo.id } });
        } catch {}
      }, 30_000);
    }
  }

  res.json({
    id: photo.id,
    senderId: photo.senderId,
    recipientId: photo.recipientId,
    imageUrl: `data:image/jpeg;base64,${photo.contentBase64}`,
    isOneTime: photo.isOneTime,
    viewedAt: photo.viewedAt,
    createdAt: photo.createdAt,
  });
});

router.get('/inbox/list', authRequired, async (req, res) => {
  const photos = await Photo.findAll({
    where: { recipientId: req.user.id },
    order: [['createdAt', 'DESC']],
    limit: 50,
  });
  res.json({
    photos: photos.map((p) => ({
      id: p.id,
      senderId: p.senderId,
      isOneTime: p.isOneTime,
      viewedAt: p.viewedAt,
      createdAt: p.createdAt,
      expiresAt: p.expiresAt,
    })),
  });
});

module.exports = router;
