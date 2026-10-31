<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Devam eden parçalı yükleme. Parçalar yerel diskte "chunks/{id}.part" dosyasına eklenir.
 * Kayıtlar yalnızca servis içinden forceCreate ile oluşturulur.
 */
#[Guarded(['*'])]
class UploadSession extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'received_bytes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'received_bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function partPath(): string
    {
        return Storage::disk('local')->path("chunks/{$this->id}.part");
    }

    public function isComplete(): bool
    {
        return $this->received_bytes === $this->size_bytes;
    }

    /**
     * Oturumu ve diskteki parça dosyasını siler.
     */
    public function discard(): void
    {
        @unlink($this->partPath());
        $this->delete();
    }
}
