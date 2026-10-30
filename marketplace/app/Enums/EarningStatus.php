<?php

namespace App\Enums;

enum EarningStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
