const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Report = sequelize.define(
  'Report',
  {
    id: {
      type: DataTypes.BIGINT,
      autoIncrement: true,
      primaryKey: true,
    },
    reporterId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'reporter_id',
    },
    reportedId: {
      type: DataTypes.UUID,
      allowNull: false,
      field: 'reported_id',
      index: true,
    },
    roomId: {
      type: DataTypes.STRING,
      field: 'room_id',
    },
    reason: {
      type: DataTypes.STRING(50),
      allowNull: false,
    },
    note: {
      type: DataTypes.STRING(500),
      allowNull: true,
    },
    reviewed: {
      type: DataTypes.BOOLEAN,
      defaultValue: false,
    },
  },
  {
    tableName: 'reports',
    updatedAt: false,
  }
);

module.exports = Report;
