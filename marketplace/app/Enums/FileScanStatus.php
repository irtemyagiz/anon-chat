<?php

namespace App\Enums;

enum FileScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
}
