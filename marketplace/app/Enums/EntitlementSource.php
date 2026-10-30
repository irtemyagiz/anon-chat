<?php

namespace App\Enums;

enum EntitlementSource: string
{
    case Purchase = 'purchase';
    case Free = 'free';
    case Grant = 'grant';
}
