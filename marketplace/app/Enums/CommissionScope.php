<?php

namespace App\Enums;

enum CommissionScope: string
{
    case Default = 'default';
    case Creator = 'creator';
    case Category = 'category';
    case Campaign = 'campaign';
}
