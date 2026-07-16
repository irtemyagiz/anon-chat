const { verifyToken } = require('../lib/jwt');
const User = require('../models/User');
const Ban = require('../models/Ban');

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
    const activeBan = await Ban.findOne({
      where: {
        deviceId: user.deviceId,
        expiresAt: { [require('sequelize').Op.gt]: new Date() },
      },
    });
    if (activeBan) {
      return res.status(403).json({
        error: 'banned',
        expiresAt: activeBan.expiresAt,
      });
    }
    req.user = user;
    next();
  } catch (err) {
    return res.status(401).json({ error: 'invalid_token' });
  }
};

module.exports = { authRequired };
