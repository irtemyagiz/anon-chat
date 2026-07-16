const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Message = sequelize.define(
  'Message',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    roomId: {
      type: DataTypes.STRING,
      allowNull: false,
      field: 'room_id',
      index: true,
    },
    senderId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'sender_id',
    },
    content: {
      type: DataTypes.TEXT,
      allowNull: false,
      validate: { len: [1, 1000] },
    },
    flagged: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
    },
  },
  {
    tableName: 'messages',
    updatedAt: false,
  }
);

module.exports = Message;
