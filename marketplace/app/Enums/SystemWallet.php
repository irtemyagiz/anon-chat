<?php

namespace App\Enums;

enum SystemWallet: string
{
    case TopUpClearing = 'topup_clearing';
    case SalesRevenue = 'sales_revenue';
    case Promotions = 'promotions';
    case Adjustments = 'adjustments';
}
