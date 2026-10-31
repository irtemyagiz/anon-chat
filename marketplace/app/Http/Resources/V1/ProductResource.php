<?php

namespace App\Http\Resources\V1;

use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Models\ProductLicense;
use App\Services\Currency\CurrencyResolver;
use App\Services\Currency\PriceFormatter;
use App\Services\Files\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Ürün. Liste uçlarında özet, detay ucunda (ilişkiler yüklüyse) görseller, lisanslar,
 * dosya listesi (yalnızca ad/biçim/boyut; dosya yolu asla dönmez) ve sürümler eklenir.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk(config('marketplace.storage.public_media_disk'));
        $cover = $this->cover_image_path;

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'is_free' => $this->is_free,
            'price' => self::price($this->price_cents, $request),
            'category' => $this->whenLoaded('category', fn () => ['slug' => $this->category->slug, 'name' => $this->category->localizedName()]),
            'designer' => $this->whenLoaded('creator', fn () => $this->creator->creatorProfile ? [
                'store_name' => $this->creator->creatorProfile->store_name,
                'store_slug' => $this->creator->creatorProfile->store_slug,
            ] : null),
            'cover_image' => $cover ? [
                'url' => $disk->url($cover),
                'small' => $disk->url(ImageProcessor::variantPath($cover, 480)),
                'medium' => $disk->url(ImageProcessor::variantPath($cover, 960)),
            ] : null,
            'stats' => [
                'likes' => (int) $this->likes_count,
                'downloads' => (int) $this->downloads_count,
                'sales' => (int) $this->sales_count,
                'rating_average' => $this->rating_average !== null ? (string) $this->rating_average : null,
                'reviews' => (int) $this->reviews_count,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'url' => route('products.show', $this->resource),

            'description' => $this->when($this->relationLoaded('licenses'), fn () => $this->description),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ProductImage $image) => [
                'url' => Storage::disk($image->disk)->url($image->path),
                'medium' => Storage::disk($image->disk)->url($image->variants['sizes'][960] ?? $image->path),
                'small' => Storage::disk($image->disk)->url($image->variants['sizes'][480] ?? $image->path),
            ])->values()),
            'licenses' => $this->whenLoaded('licenses', fn () => $this->licenses->where('is_active', true)->map(fn (ProductLicense $license) => [
                'id' => $license->id,
                'code' => $license->license->code,
                'name' => $license->license->name[app()->getLocale()] ?? $license->license->code,
                'allows_commercial_use' => (bool) $license->license->allows_commercial_use,
                'is_default' => $license->is_default,
                'price' => self::price($license->price_cents, $request),
            ])->values()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'files' => $this->whenLoaded('files', fn () => $this->files->where('is_active', true)->map(fn (ProductFile $file) => [
                'name' => $file->original_name,
                'extension' => $file->extension,
                'size_bytes' => (int) $file->size_bytes,
            ])->values()),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($version) => [
                'version' => $version->version,
                'changelog' => $version->changelog,
                'released_at' => $version->released_at?->toIso8601String(),
            ])->values()),
            'print' => $this->when($this->relationLoaded('licenses'), fn () => [
                'technology' => $this->print_technology,
                'filament' => $this->filament_type,
                'requires_supports' => $this->requires_supports,
                'print_time_minutes' => $this->print_time_minutes,
                'dimensions_mm' => array_filter(['x' => $this->dimension_x_mm, 'y' => $this->dimension_y_mm, 'z' => $this->dimension_z_mm], fn ($value) => $value !== null),
            ]),
        ];
    }

    /**
     * USD fiyat, puan karşılığı ve istenen görüntüleme para birimindeki tutar.
     * ?currency=TRY|USD ile seçilebilir; verilmezse kullanıcının/ülkenin para birimi.
     *
     * @return array{usd_cents: int, points: int, currency: string, amount_minor: int, formatted: string}
     */
    public static function price(int $usdCents, Request $request): array
    {
        $resolver = app(CurrencyResolver::class);
        $requested = strtoupper((string) $request->query('currency'));
        $currency = $resolver->isSupported($requested) ? $requested : $resolver->displayCurrency($request->user(), $request);

        return app(PriceFormatter::class)->present($usdCents, $currency);
    }
}
