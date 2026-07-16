const jwt = require('jsonwebtoken');

const JWT_SECRET = process.env.JWT_SECRET || 'dev-only-change-me';
const JWT_EXPIRES_IN = process.env.JWT_EXPIRES_IN || '30d';

const signToken = (payload) =>
  jwt.sign(payload, JWT_SECRET, { expiresIn: JWT_EXPIRES_IN });

const verifyToken = (token) => jwt.verify(token, JWT_SECRET);

module.exports = { signToken, verifyToken };
