const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Friendship = sequelize.define(
  'Friendship',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    userAId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'user_a_id',
      index: true,
    },
    userBId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'user_b_id',
      index: true,
    },
    mutualChats: {
      type: DataTypes.INTEGER,
      defaultValue: 0,
      field: 'mutual_chats',
    },
    chatHistory: {
      type: DataTypes.JSONB,
      defaultValue: [],
      field: 'chat_history',
    },
  },
  {
    tableName: 'friendships',
    indexes: [
      { unique: true, fields: ['user_a_id', 'user_b_id'] },
    ],
  }
);

module.exports = Friendship;
