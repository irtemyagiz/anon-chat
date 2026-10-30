<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kur kaydı. rate_micros: 1 birim baz para biriminin karşı para birimindeki
 * değerinin milyon katı (40.5 TRY => 40500000). Kayıtlar güncellenmez; her
 * yeni kur yeni satır olarak eklenir ve geçmiş korunur.
 */
#[Guarded(['*'])]
class ExchangeRate extends Model
{
    protected function casts(): array
    {
        return [
            'is_manual' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /**
     * Gösterim amaçlı ondalık metin ("40.500000"). Hesaplamalarda kullanılmaz.
     */
    public function formattedRate(): string
    {
        return intdiv($this->rate_micros, 1_000_000).'.'.str_pad((string) ($this->rate_micros % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
