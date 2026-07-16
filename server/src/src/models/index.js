const { Sequelize } = require('sequelize');

const sequelize = new Sequelize(process.env.DATABASE_URL, {
  dialect: 'postgres',
  logging: process.env.NODE_ENV === 'development' ? console.log : false,
  pool: {
    max: 10,
    min: 0,
    acquire: 30000,
    idle: 10000,
  },
  define: {
    timestamps: true,
    underscored: true,
  },
});

const connectDB = async () => {
  try {
    await sequelize.authenticate();
    console.log('[db] PostgreSQL bağlantısı başarılı');
    await sequelize.sync({ alter: true });
    console.log('[db] Tablolar senkronize edildi');
    try {
      const User = require('./User');
      const [updated] = await User.update(
        { ageConfirmed: true, rulesAcceptedAt: new Date() },
        { where: { ageConfirmed: false } }
      );
      if (updated > 0) console.log(`[db] ${updated} kullanıcıya ageConfirmed=true set edildi`);
    } catch (e) {
      console.warn('[db] ageConfirmed backfill hatası:', e.message);
    }
  } catch (err) {
    console.error('[db] Bağlantı hatası:', err.message);
    throw err;
  }
};

module.exports = { sequelize, connectDB };
