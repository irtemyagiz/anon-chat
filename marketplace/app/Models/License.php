<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'summary', 'allows_commercial_use', 'is_active', 'sort_order'])]
class License extends Model
{
    public const Personal = 'personal';

    public const Commercial = 'commercial';

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'summary' => 'array',
            'allows_commercial_use' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
