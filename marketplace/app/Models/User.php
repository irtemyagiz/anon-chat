<?php

namespace App\Models;

use App\Enums\CreatorStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'username', 'first_name', 'last_name', 'email', 'password',
    'country_code', 'display_currency', 'locale', 'theme', 'bio',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Veritabanı varsayılanlarıyla aynı model varsayılanları.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'locale' => 'tr',
        'theme' => 'system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'suspended_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('assigned_by', 'created_at');
    }

    /**
     * @return HasOne<CreatorProfile, $this>
     */
    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * @return HasOne<CreatorBalance, $this>
     */
    public function creatorBalance(): HasOne
    {
        return $this->hasOne(CreatorBalance::class, 'creator_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'creator_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<PurchaseEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(PurchaseEntitlement::class);
    }

    /**
     * @return HasMany<PointPurchase, $this>
     */
    public function pointPurchases(): HasMany
    {
        return $this->hasMany(PointPurchase::class);
    }

    /**
     * @return HasMany<UserSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * @return HasMany<Collection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites')->withPivot('created_at');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'creator_id')->withPivot('created_at');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains('name', $permission);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    /**
     * Onaylanmış ve aktif bir creator mağazası var mı?
     */
    public function isApprovedCreator(): bool
    {
        return $this->hasRole(Role::Creator)
            && $this->creatorProfile?->status === CreatorStatus::Approved;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Kullanıcının ürünün belirtilen lisansı (veya herhangi bir lisansı) için geçerli indirme hakkı var mı?
     */
    public function ownsProduct(Product $product, ?int $licenseId = null): bool
    {
        return $this->entitlements()
            ->where('product_id', $product->id)
            ->when($licenseId !== null, fn ($query) => $query->where('license_id', $licenseId))
            ->whereNull('revoked_at')
            ->exists();
    }
}
