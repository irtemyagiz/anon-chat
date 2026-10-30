<?php

namespace App\Models;

use App\Enums\CreatorStatus;
use Database\Factories\CreatorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'designer_name', 'store_name', 'store_slug', 'description',
    'logo_path', 'cover_path', 'social_links', 'terms_accepted_at',
])]
class CreatorProfile extends Model
{
    /** @use HasFactory<CreatorProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CreatorStatus::class,
            'social_links' => 'array',
            'approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
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
     * @return HasMany<CreatorVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(CreatorVerification::class);
    }

    /**
     * Satıcı kimlik/vergi doğrulaması onaylanmış mı? Hakediş ödemesi için zorunludur.
     */
    public function isVerified(): bool
    {
        return $this->verifications()->where('status', 'approved')->exists();
    }
}
