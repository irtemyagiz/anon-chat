const { DataTypes } = require('sequelize');
const { sequelize } = require('./index');

const Interest = sequelize.define(
  'Interest',
  {
    id: {
      type: DataTypes.INTEGER,
      autoIncrement: true,
      primaryKey: true,
    },
    slug: {
      type: DataTypes.STRING(30),
      allowNull: false,
      unique: true,
    },
    nameTr: {
      type: DataTypes.STRING(50),
      allowNull: false,
      field: 'name_tr',
    },
    nameEn: {
      type: DataTypes.STRING(50),
      allowNull: false,
      field: 'name_en',
    },
    emoji: {
      type: DataTypes.STRING(10),
      defaultValue: '🏷️',
    },
  },
  {
    tableName: 'interests',
  }
);

const UserInterest = sequelize.define(
  'UserInterest',
  {
    userId: {
      type: DataTypes.UUID,
      field: 'user_id',
      primaryKey: true,
    },
    interestId: {
      type: DataTypes.INTEGER,
      field: 'interest_id',
      primaryKey: true,
    },
  },
  {
    tableName: 'user_interests',
    timestamps: false,
  }
);

module.exports = { Interest, UserInterest };
