<?php

namespace App\Models;

use App\Enums\EntitlementSource;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kullanıcının bir ürün + lisans için indirme hakkı. (user, product, license)
 * benzersizdir; bu kısıt çift satın almayı veritabanı seviyesinde de engeller.
 */
#[Guarded(['*'])]
class PurchaseEntitlement extends Model
{
    protected function casts(): array
    {
        return [
            'source' => EntitlementSource::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
