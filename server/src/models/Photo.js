const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Photo = sequelize.define(
  'Photo',
  {
    id: {
      type: DataTypes.UUID,
      defaultValue: DataTypes.UUIDV4,
      primaryKey: true,
    },
    senderId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'sender_id',
      index: true,
    },
    recipientId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'recipient_id',
      index: true,
    },
    contentBase64: {
      type: DataTypes.TEXT,
      allowNull: false,
      field: 'content_base64',
    },
    isOneTime: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
      field: 'is_one_time',
    },
    viewedAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'viewed_at',
    },
    expiresAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'expires_at',
    },
  },
  {
    tableName: 'photos',
  }
);

module.exports = Photo;
