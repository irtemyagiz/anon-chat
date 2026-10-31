<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['subject', 'category', 'priority'])]
class SupportTicket extends Model
{
    public const CATEGORIES = [
        'general' => 'Genel soru',
        'payment' => 'Ödeme ve puan yükleme',
        'refund' => 'Kullanılmamış puan iadesi',
        'download' => 'İndirme / dosya sorunu',
        'creator' => 'Creator ve hakediş',
        'copyright' => 'Telif hakkı',
        'account' => 'Hesap ve güvenlik',
    ];

    public const STATUSES = ['open' => 'Açık', 'answered' => 'Yanıtlandı', 'awaiting_staff' => 'Yanıt bekliyor', 'closed' => 'Kapatıldı'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (SupportTicket $ticket) => $ticket->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<PointPurchase, $this>
     */
    public function pointPurchase(): BelongsTo
    {
        return $this->belongsTo(PointPurchase::class);
    }

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest();
    }
}
