<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Completed = 'completed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
