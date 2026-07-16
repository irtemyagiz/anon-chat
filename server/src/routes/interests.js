const express = require('express');
const { Interest } = require('../models/Interest');
const { authRequired } = require('../middleware/auth');

const router = express.Router();

router.get('/', authRequired, async (req, res) => {
  try {
    const interests = await Interest.findAll({
      order: [['id', 'ASC']],
    });
    res.json({
      interests: interests.map((i) => ({
        id: i.id,
        slug: i.slug,
        nameTr: i.nameTr,
        nameEn: i.nameEn,
        emoji: i.emoji,
      })),
    });
  } catch (err) {
    console.error('[interests GET]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

const DEFAULT_INTERESTS = [
  { slug: 'music', nameTr: 'Müzik', nameEn: 'Music', emoji: '🎵' },
  { slug: 'gaming', nameTr: 'Oyun', nameEn: 'Gaming', emoji: '🎮' },
  { slug: 'tech', nameTr: 'Teknoloji', nameEn: 'Technology', emoji: '💻' },
  { slug: 'sports', nameTr: 'Spor', nameEn: 'Sports', emoji: '⚽' },
  { slug: 'movies', nameTr: 'Film & Dizi', nameEn: 'Movies & TV', emoji: '🎬' },
  { slug: 'books', nameTr: 'Kitap', nameEn: 'Books', emoji: '📚' },
  { slug: 'travel', nameTr: 'Seyahat', nameEn: 'Travel', emoji: '✈️' },
  { slug: 'food', nameTr: 'Yemek', nameEn: 'Food', emoji: '🍕' },
  { slug: 'art', nameTr: 'Sanat', nameEn: 'Art', emoji: '🎨' },
  { slug: 'fashion', nameTr: 'Moda', nameEn: 'Fashion', emoji: '👗' },
  { slug: 'science', nameTr: 'Bilim', nameEn: 'Science', emoji: '🔬' },
  { slug: 'philosophy', nameTr: 'Felsefe', nameEn: 'Philosophy', emoji: '🤔' },
  { slug: 'anime', nameTr: 'Anime', nameEn: 'Anime', emoji: '🌸' },
  { slug: 'photography', nameTr: 'Fotoğrafçılık', nameEn: 'Photography', emoji: '📷' },
  { slug: 'fitness', nameTr: 'Fitness', nameEn: 'Fitness', emoji: '💪' },
  { slug: 'pets', nameTr: 'Evcil Hayvan', nameEn: 'Pets', emoji: '🐾' },
];

router.post('/seed', async (req, res) => {
  try {
    for (const i of DEFAULT_INTERESTS) {
      await Interest.findOrCreate({
        where: { slug: i.slug },
        defaults: i,
      });
    }
    res.json({ ok: true });
  } catch (err) {
    console.error('[interests seed]', err);
    res.status(500).json({ error: 'server_error' });
  }
});

module.exports = router;
