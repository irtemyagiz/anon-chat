<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
class ExchangeRateLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
        ];
    }
}
