<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['locale', 'question', 'answer', 'sort_order', 'is_active'])]
class Faq extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
