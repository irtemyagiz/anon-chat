const express = require('express');
const http = require('http');
const cors = require('cors');
const helmet = require('helmet');
const morgan = require('morgan');
const rateLimit = require('express-rate-limit');
require('dotenv').config();

const { connectDB } = require('./models');
const { setUpSockets } = require('./sockets');

const authRoutes = require('./routes/auth');
const interestsRoutes = require('./routes/interests');
const healthRoutes = require('./routes/health');
const usersRoutes = require('./routes/users');
const shuffleRoutes = require('./routes/shuffle');
const friendsRoutes = require('./routes/friends');
const photosRoutes = require('./routes/photos');
const adminRoutes = require('./routes/admin');
const chatsRoutes = require('./routes/chats');
const debugRoutes = require('./routes/debug');

const app = express();
const server = http.createServer(app);

const ALLOWED_ORIGINS = process.env.ALLOWED_ORIGINS || '*';
const corsOptions =
  ALLOWED_ORIGINS === '*'
    ? { origin: true, credentials: true }
    : {
        origin: ALLOWED_ORIGINS.split(',').map((s) => s.trim()),
        credentials: true,
      };

app.use(helmet({ crossOriginResourcePolicy: false }));
app.use(cors(corsOptions));
app.use(express.json({ limit: '64kb' }));
app.use(morgan(process.env.NODE_ENV === 'production' ? 'combined' : 'dev'));

const apiLimiter = rateLimit({
  windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '60000', 10),
  max: parseInt(process.env.RATE_LIMIT_MAX || '120', 10),
  standardHeaders: true,
  legacyHeaders: false,
});
app.use('/api', apiLimiter);

app.use('/api/health', healthRoutes);
app.use('/api/auth', authRoutes.router);
app.use('/api/interests', interestsRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/shuffle', shuffleRoutes);
app.use('/api/friends', friendsRoutes.router);
app.use('/api/photos', photosRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api/chats', chatsRoutes);
app.use('/api/debug', debugRoutes);

app.get('/', (req, res) => {
  res.json({
    name: 'anon-chat-api',
    version: '1.0.0',
    docs: '/api/health',
  });
});

app.use((err, req, res, next) => {
  console.error('[unhandled]', err);
  res.status(500).json({ error: 'server_error' });
});

const PORT = process.env.PORT || 3000;

async function start() {
  try {
    await connectDB();
    setUpSockets(server, corsOptions);
    server.listen(PORT, '0.0.0.0', () => {
      console.log(`[server] HTTP + Socket.IO dinleniyor :${PORT}`);
    });
  } catch (err) {
    console.error('[startup] Hata:', err.message);
    process.exit(1);
  }
}

start();

process.on('unhandledRejection', (err) => {
  console.error('[unhandledRejection]', err);
});
