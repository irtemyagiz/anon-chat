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
    deviceId: {
      type: DataTypes.STRING,
      allowNull: false,
      unique: true,
      field: 'device_id',
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
