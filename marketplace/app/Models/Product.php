<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Policies\ProductPolicy;
use App\Services\Catalog\CatalogCache;
use App\Services\Points\PointConverter;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Fiyat USD sent olarak tutulur (price_cents). 1 USD = 100 POINT olduğundan
 * puan karşılığı sent değerine eşittir; dönüşüm PointConverter üzerinden yapılır.
 */
#[Fillable([
    'category_id', 'title', 'slug', 'short_description', 'description', 'price_cents',
    'cover_image_path', 'preview_model_path', 'preview_video_url', 'current_version',
    'print_settings', 'requires_supports', 'print_time_minutes', 'filament_type',
    'printer_compatibility', 'print_technology', 'dimension_x_mm', 'dimension_y_mm',
    'dimension_z_mm', 'meta_title', 'meta_description',
])]
#[UsePolicy(ProductPolicy::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'price_cents' => 0,
        'preview_auto_generated' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_free' => 'boolean',
            'is_featured' => 'boolean',
            'print_settings' => 'array',
            'requires_supports' => 'boolean',
            'preview_auto_generated' => 'boolean',
            'published_at' => 'datetime',
            'dimension_x_mm' => 'decimal:2',
            'dimension_y_mm' => 'decimal:2',
            'dimension_z_mm' => 'decimal:2',
            'rating_average' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->uuid ??= (string) Str::uuid();
        });

        static::saving(function (Product $product): void {
            $product->is_free = (int) $product->price_cents === 0;
        });

        /** Ana sayfa önbelleği, bir ürün yayına girip çıktığında veya öne çıkarıldığında yenilenir. */
        $forgetHomeCache = function (Product $product): void {
            if ($product->wasChanged(['status', 'is_featured', 'category_id', 'price_cents']) || ! $product->exists || $product->wasRecentlyCreated) {
                CatalogCache::forget();
            }
        };
        static::saved($forgetHomeCache);
        static::deleted($forgetHomeCache);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function additionalCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    /**
     * @return HasMany<ProductLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(ProductLicense::class);
    }

    /**
     * @return HasMany<ProductFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->latest('released_at');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    public function pricePoints(): int
    {
        return PointConverter::usdCentsToPoints($this->price_cents);
    }

    /**
     * YouTube/Vimeo bağlantısını gizlilik dostu gömme adresine çevirir.
     */
    public function videoEmbedUrl(): ?string
    {
        $url = (string) $this->preview_video_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match)) {
            return 'https://www.youtube-nocookie.com/embed/'.$match[1];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $match)) {
            return 'https://player.vimeo.com/video/'.$match[1].'?dnt=1';
        }

        return null;
    }

    /**
     * Varsayılan lisans; ürün için tanımlı değilse null.
     */
    public function defaultLicense(): ?ProductLicense
    {
        return $this->licenses->firstWhere('is_default', true) ?? $this->licenses->first();
    }
}
