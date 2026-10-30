<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['extension', 'label', 'mime_types', 'is_archive', 'is_previewable', 'is_active'])]
class FileFormat extends Model
{
    protected function casts(): array
    {
        return [
            'mime_types' => 'array',
            'is_archive' => 'boolean',
            'is_previewable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
