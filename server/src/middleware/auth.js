const { verifyToken } = require('../lib/jwt');
const User = require('../models/User');

const authRequired = async (req, res, next) => {
  try {
    const header = req.headers.authorization || '';
    const token = header.startsWith('Bearer ') ? header.slice(7) : null;
    if (!token) {
      return res.status(401).json({ error: 'auth_required' });
    }
    let payload;
    try {
      payload = verifyToken(token);
    } catch (jwtErr) {
      console.error('[auth] jwt verify failed:', jwtErr.message);
      return res.status(401).json({ error: 'invalid_token', reason: 'jwt' });
    }

    let user;
    try {
      user = await User.findByPk(payload.sub);
    } catch (dbErr) {
      console.error('[auth] User.findByPk threw:', dbErr.message);
      return res.status(500).json({ error: 'db_error', message: dbErr.message });
    }

    if (!user) {
      const count = await User.count().catch(() => -1);
      console.error('[auth] user not found sub=' + payload.sub + ' db_count=' + count);
      return res.status(401).json({ error: 'invalid_token', reason: 'not_found', db_count: count });
    }
    if (user.isBanned) {
      return res.status(403).json({ error: 'banned' });
    }
    req.user = user;
    next();
  } catch (err) {
    console.error('[authRequired] unexpected:', err);
    return res.status(401).json({ error: 'invalid_token' });
  }
};

module.exports = { authRequired };
