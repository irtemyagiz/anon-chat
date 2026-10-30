<?php

namespace App\Models;

use App\Enums\FileScanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * İndirilebilir ürün dosyası. Dosyalar yalnızca özel (private) diskte tutulur;
 * "path" asla herkese açık bir URL değildir.
 */
#[Fillable([
    'product_version_id', 'disk', 'path', 'original_name', 'extension',
    'mime_type', 'size_bytes', 'sha256', 'is_active',
])]
#[Hidden(['disk', 'path'])]
class ProductFile extends Model
{
    protected function casts(): array
    {
        return [
            'scan_status' => FileScanStatus::class,
            'scanned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductFile $file): void {
            $file->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isDownloadable(): bool
    {
        return $this->is_active && $this->scan_status === FileScanStatus::Clean;
    }
}
