const express = require('express');
const router = express.Router();
const { sequelize } = require('../models');
const User = require('../models/User');
const Follow = require('../models/Follow');

router.get('/db-state', async (req, res) => {
  try {
    const [users] = await sequelize.query('SELECT id, email, username, nickname, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    const [tables] = await sequelize.query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    const [userCols] = await sequelize.query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position");
    return res.json({
      user_count: await User.count(),
      tables: tables.map((t) => t.table_name),
      user_columns: userCols,
      recent_users: users,
    });
  } catch (err) {
    return res.status(500).json({ error: err.message, stack: err.stack });
  }
});

router.get('/raw-query', async (req, res) => {
  try {
    const id = req.query.id;
    if (!id) return res.status(400).json({ error: 'missing id' });
    const [rows] = await sequelize.query('SELECT id, email, nickname FROM users WHERE id = :id', { replacements: { id } });
    return res.json({ rows, count: rows.length });
  } catch (err) {
    return res.status(500).json({ error: err.message });
  }
});

module.exports = router;