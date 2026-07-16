const express = require('express');
const router = express.Router();

router.get('/', (req, res) => {
  res.json({
    status: 'ok',
    service: 'anon-chat-api',
    uptime: process.uptime(),
    ts: new Date().toISOString(),
  });
});

module.exports = router;
