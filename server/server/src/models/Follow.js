const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Follow = sequelize.define(
  'Follow',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    followerId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'follower_id',
      index: true,
    },
    followedId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'followed_id',
      index: true,
    },
    status: {
      type: DataTypes.ENUM('pending', 'accepted', 'rejected'),
      defaultValue: 'pending',
    },
  },
  {
    tableName: 'follows',
    updatedAt: false,
    indexes: [
      { unique: true, fields: ['follower_id', 'followed_id'] },
      { fields: ['followed_id', 'status'] },
    ],
  }
);

module.exports = Follow;