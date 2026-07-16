const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const User = sequelize.define(
  'User',
  {
    id: {
      type: DataTypes.UUID,
      defaultValue: DataTypes.UUIDV4,
      primaryKey: true,
    },
    email: {
      type: DataTypes.STRING(120),
      allowNull: true,
      unique: true,
    },
    passwordHash: {
      type: DataTypes.STRING,
      allowNull: true,
      field: 'password_hash',
    },
    username: {
      type: DataTypes.STRING(30),
      allowNull: true,
      unique: true,
    },
    nickname: {
      type: DataTypes.STRING(20),
      allowNull: false,
    },
    avatarColor: {
      type: DataTypes.STRING(7),
      defaultValue: '#6366F1',
      field: 'avatar_color',
    },
    avatarSeed: {
      type: DataTypes.STRING,
      defaultValue: () => Math.random().toString(36).slice(2, 10),
      field: 'avatar_seed',
    },
    photoBase64: {
      type: DataTypes.TEXT,
      allowNull: true,
      field: 'photo_base64',
    },
    bio: {
      type: DataTypes.STRING(200),
      allowNull: true,
    },
    gender: {
      type: DataTypes.ENUM('male', 'female', 'other', null),
      allowNull: true,
    },
    age: {
      type: DataTypes.INTEGER,
      allowNull: true,
      validate: { min: 18, max: 99 },
    },
    countryCode: {
      type: DataTypes.STRING(2),
      allowNull: true,
      field: 'country_code',
    },
    ageConfirmed: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
      field: 'age_confirmed',
    },
    rulesAcceptedAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'rules_accepted_at',
    },
    anonymityEnabled: {
      type: DataTypes.BOOLEAN,
      defaultValue: true,
      field: 'anonymity_enabled',
    },
    isPlus: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
      field: 'is_plus',
    },
    plusExpiresAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'plus_expires_at',
    },
    dailyShuffleCount: {
      type: DataTypes.INTEGER,
      defaultValue: 0,
      field: 'daily_shuffle_count',
    },
    lastShuffleResetAt: {
      type: DataTypes.DATE,
      defaultValue: DataTypes.NOW,
      field: 'last_shuffle_reset_at',
    },
    isBanned: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
      field: 'is_banned',
    },
    lastSeenAt: {
      type: DataTypes.DATE,
      defaultValue: DataTypes.NOW,
      field: 'last_seen_at',
    },
    totalChats: {
      type: DataTypes.INTEGER,
      defaultValue: 0,
      field: 'total_chats',
    },
  },
  {
    tableName: 'users',
  }
);

module.exports = User;
