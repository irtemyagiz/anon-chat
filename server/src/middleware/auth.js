const { verifyToken } = require('../lib/jwt');
const User = require('../models/User');
const { Op } = require('sequelize');

const authRequired = async (req, res, next) => {
  try {
    const header = req.headers.authorization || '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : null;
    if (!token) {
      return res.status(401).json({ error: 'auth_required' });
    }
    const payload = verifyToken(token);
    const user = await User.findByPk(payload.sub);
    if (!user) {
      return res.status(401).json({ error: 'invalid_token' });
    }
    if (user.isBanned) {
      return res.status(403).json({ error: 'banned' });
    }
    req.user = user;
    next();
  } catch (err) {
    console.error('[authRequired]', err.message);
    return res.status(401).json({ error: 'invalid_token' });
  }
};

module.exports = { authRequired };
