<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Completed = 'completed';
    case Refunded = 'refunded';
}
