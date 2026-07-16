const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Ban = sequelize.define(
  'Ban',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    deviceId: {
      type: DataTypes.STRING,
      allowNull: false,
      field: 'device_id',
      index: true,
    },
    userId: {
      type: DataTypes.UUID,
      allowNull: true,
      field: 'user_id',
    },
    reason: {
      type: DataTypes.STRING(100),
      allowNull: false,
    },
    expiresAt: {
      type: DataTypes.DATE,
      allowNull: false,
      field: 'expires_at',
    },
  },
  {
    tableName: 'bans',
    updatedAt: false,
  }
);

module.exports = Ban;
