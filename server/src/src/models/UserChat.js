const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const UserChat = sequelize.define(
  'UserChat',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    userId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'user_id',
      index: true,
    },
    peerId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'peer_id',
      index: true,
    },
    roomId: {
      type: DataTypes.STRING,
      allowNull: false,
      field: 'room_id',
    },
    pinnedAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'pinned_at',
    },
    deletedAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'deleted_at',
    },
    lastReadAt: {
      type: DataTypes.DATE,
      allowNull: true,
      field: 'last_read_at',
    },
  },
  {
    tableName: 'user_chats',
    indexes: [
      { unique: true, fields: ['user_id', 'peer_id'] },
    ],
  }
);

module.exports = UserChat;