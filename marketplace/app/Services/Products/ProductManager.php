<?php

namespace App\Services\Products;

use App\Enums\FileScanStatus;
use App\Enums\ProductStatus;
use App\Exceptions\InvalidUploadException;
use App\Exceptions\MarketplaceException;
use App\Jobs\GeneratePreviewModel;
use App\Jobs\ScanProductFile;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\CreatorPublishedNotification;
use App\Notifications\ProductReviewedNotification;
use App\Notifications\ProductUpdatedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Files\ImageProcessor;
use App\Services\Files\UploadInspector;
use App\Services\Notifications\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creator ürün yaşam döngüsü: taslak → incelemede → yayında (veya reddedildi /
 * yayından kaldırıldı). Dosyalar özel diske rastgele adlarla kaydedilir ve
 * zararlı yazılım taraması kuyruğa alınır.
 */
class ProductManager
{
    public const PREVIEW_EXTENSIONS = ['stl', 'obj', '3mf'];

    public function __construct(
        private readonly UploadInspector $inspector,
        private readonly ImageProcessor $images,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  ProductRequest ile doğrulanmış alanlar
     */
    public function create(User $creator, array $data): Product
    {
        return DB::transaction(function () use ($creator, $data): Product {
            $product = new Product;
            $product->creator_id = $creator->id;
            $product->fill($this->attributes($data));
            $product->slug = $this->uniqueSlug($data['slug'] ?? null ?: $data['title']);
            $product->save();

            $this->syncLicenses($product, $data);
            $this->syncTags($product, $data['tags'] ?? null);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product = DB::transaction(function () use ($product, $data): Product {
            $previousVersion = $product->current_version;
            $product->fill($this->attributes($data));

            if (filled($data['slug'] ?? null) && Str::slug($data['slug']) !== $product->slug) {
                $product->slug = $this->uniqueSlug($data['slug'], $product->id);
            }

            $product->save();
            $this->syncLicenses($product, $data);
            $this->syncTags($product, $data['tags'] ?? null);

            /** Yayındaki üründe sürüm değişirse sürüm geçmişine eklenir ve satın alanlara bildirilir. */
            if ($product->isPublished() && $product->current_version !== $previousVersion) {
                $version = $product->versions()->firstOrCreate(['version' => $product->current_version], ['released_at' => now(), 'changelog' => $data['changelog'] ?? null]);

                if ($version->wasRecentlyCreated) {
                    $owners = User::query()->whereIn('id', DB::table('purchase_entitlements')->where('product_id', $product->id)->whereNull('revoked_at')->select('user_id'))->get();
                    $this->notifier->send($owners, new ProductUpdatedNotification($product->title, $product->slug, $version->version));
                }
            }

            return $product;
        });

        return $product;
    }

    /**
     * @param  list<UploadedFile>  $uploads
     */
    public function addFiles(Product $product, array $uploads): void
    {
        $limit = (int) config('marketplace.storage.max_files_per_product');

        if ($product->files()->where('is_active', true)->count() + count($uploads) > $limit) {
            throw new InvalidUploadException(__('Bir ürüne en fazla :count dosya eklenebilir.', ['count' => $limit]));
        }

        /** Önce tüm dosyalar doğrulanır; biri bile geçersizse hiçbiri kaydedilmez. */
        $inspected = array_map(fn (UploadedFile $upload) => [$upload, $this->inspector->inspect($upload)], $uploads);
        $disk = config('marketplace.storage.product_files_disk');

        foreach ($inspected as [$upload, $info]) {
            $path = Storage::disk($disk)->putFileAs("{$product->uuid}/files", $upload, Str::random(40).'.'.$info->extension);

            $file = $product->files()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $info->originalName,
                'extension' => $info->extension,
                'mime_type' => $info->mimeType,
                'size_bytes' => $info->sizeBytes,
                'sha256' => $info->sha256,
            ]);

            ScanProductFile::dispatch($file);
        }
    }

    public function removeFile(ProductFile $file): void
    {
        /** İndirme geçmişi korunur: kayıt silinmez, pasifleştirilir. */
        $file->forceFill(['is_active' => false])->save();

        if (! DB::table('downloads')->where('product_file_id', $file->id)->exists()) {
            Storage::disk($file->disk)->delete($file->path);
        }

        /** Otomatik önizleme kaldırılan dosyadan üretilmiş olabilir; kalan dosyalardan yeniden üretilir. */
        $product = $file->product;

        if ($product->preview_auto_generated) {
            $this->removePreview($product);

            $next = $product->files()->where('is_active', true)->where('extension', 'stl')->where('scan_status', FileScanStatus::Clean)->first();

            if ($next !== null) {
                GeneratePreviewModel::dispatch($next);
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $uploads
     */
    public function addImages(Product $product, array $uploads): void
    {
        $limit = (int) config('marketplace.storage.max_images_per_product');

        if ($product->images()->count() + count($uploads) > $limit) {
            throw new InvalidUploadException(__('Bir ürüne en fazla :count görsel eklenebilir.', ['count' => $limit]));
        }

        $disk = config('marketplace.storage.public_media_disk');
        $order = (int) $product->images()->max('sort_order');

        foreach ($uploads as $upload) {
            $stored = $this->images->store($upload, $disk, "products/{$product->uuid}/images");

            $product->images()->create([
                'disk' => $disk,
                'path' => $stored['path'],
                'variants' => ['width' => $stored['width'], 'height' => $stored['height']],
                'alt_text' => Str::limit($product->title, 150, ''),
                'sort_order' => $order += 10,
            ]);
        }

        $this->refreshCover($product);
    }

    public function removeImage(ProductImage $image): void
    {
        Storage::disk($image->disk)->delete($image->path);
        $product = $image->product;
        $image->delete();

        $this->refreshCover($product);
    }

    /**
     * 3D önizleme dosyası herkese açıktır; bu yüzden creator'ın ayrıca yüklediği,
     * tercihen düşük detaylı bir dosya kullanılır. Satılan dosyalar asla önizleme olarak sunulmaz.
     */
    public function setPreview(Product $product, UploadedFile $upload): void
    {
        $info = $this->inspector->inspect($upload, self::PREVIEW_EXTENSIONS, (int) config('marketplace.storage.max_preview_size_mb') * 1024 * 1024);
        $disk = config('marketplace.storage.public_media_disk');
        $old = $product->preview_model_path;

        $path = Storage::disk($disk)->putFileAs("products/{$product->uuid}/preview", $upload, Str::random(32).'.'.$info->extension, 'public');
        $product->forceFill(['preview_model_path' => $path, 'preview_auto_generated' => false])->save();

        if ($old !== null) {
            Storage::disk($disk)->delete($old);
        }
    }

    public function removePreview(Product $product): void
    {
        if ($product->preview_model_path !== null) {
            Storage::disk(config('marketplace.storage.public_media_disk'))->delete($product->preview_model_path);
            $product->forceFill(['preview_model_path' => null, 'preview_auto_generated' => false])->save();
        }
    }

    /**
     * İncelemeye gönderir; onay gerekmiyorsa doğrudan yayınlar.
     *
     * @return list<string> Eksikler (boşsa gönderildi)
     */
    public function submit(Product $product): array
    {
        if (! in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected, ProductStatus::Unpublished], true)) {
            throw new MarketplaceException(__('Bu ürün şu anda incelemeye gönderilemez.'));
        }

        $missing = $this->missingRequirements($product);

        if ($missing !== []) {
            return $missing;
        }

        if (config('marketplace.products.require_review')) {
            $product->forceFill(['status' => ProductStatus::PendingReview, 'moderation_note' => null])->save();
            $this->notifier->staff('products.moderate', __('İnceleme bekleyen ürün'), __(':title incelemeye gönderildi.', ['title' => $product->title]), route('admin.products.show', $product));
        } else {
            $this->publish($product);
        }

        return [];
    }

    /**
     * Satışı olmayan ürün silinir (soft delete); satışı olan ürün yalnızca yayından
     * kaldırılabilir, çünkü satın alanların dosyalara erişimi sürmelidir.
     */
    public function delete(Product $product): void
    {
        if ($product->sales_count > 0 || DB::table('purchase_entitlements')->where('product_id', $product->id)->exists()) {
            throw new MarketplaceException(__('Bu ürünü satın alan veya indiren kullanıcılar olduğu için silinemez; yayından kaldırabilirsin.'));
        }

        $product->delete();
        $this->audit->record('product.deleted', $product, actor: $product->creator);
    }

    public function unpublish(Product $product): void
    {
        if (! in_array($product->status, [ProductStatus::Published, ProductStatus::PendingReview], true)) {
            throw new MarketplaceException(__('Bu ürün yayında değil.'));
        }

        $product->forceFill(['status' => ProductStatus::Unpublished])->save();
    }

    public function approve(Product $product, ?User $admin = null): void
    {
        if ($product->status !== ProductStatus::PendingReview) {
            throw new MarketplaceException(__('Yalnızca incelemedeki ürünler onaylanabilir.'));
        }

        $this->publish($product);
        $this->audit->record('product.approved', $product, ['status' => 'pending_review'], ['status' => 'published'], $admin);
        $this->notifier->send($product->creator, new ProductReviewedNotification($product->title, $product->uuid, true));
    }

    public function reject(Product $product, User $admin, string $note): void
    {
        if ($product->status !== ProductStatus::PendingReview) {
            throw new MarketplaceException(__('Yalnızca incelemedeki ürünler reddedilebilir.'));
        }

        $product->forceFill(['status' => ProductStatus::Rejected, 'moderation_note' => $note])->save();
        $this->audit->record('product.rejected', $product, ['status' => 'pending_review'], ['status' => 'rejected'], $admin, ['note' => $note]);
        $this->notifier->send($product->creator, new ProductReviewedNotification($product->title, $product->uuid, false, $note));
    }

    /**
     * @return list<string>
     */
    public function missingRequirements(Product $product): array
    {
        $missing = [];

        if ($product->images()->doesntExist()) {
            $missing[] = __('En az bir ürün görseli ekleyin.');
        }

        if ($product->files()->where('is_active', true)->doesntExist()) {
            $missing[] = __('En az bir indirilebilir dosya ekleyin.');
        }

        if ($product->files()->where('is_active', true)->where('scan_status', FileScanStatus::Infected)->exists()) {
            $missing[] = __('Zararlı içerik tespit edilen dosyaları kaldırın.');
        }

        if (blank($product->description)) {
            $missing[] = __('Ürün açıklaması yazın.');
        }

        return $missing;
    }

    /**
     * Ürünü yayınlar. İlk yayında takipçilere, yeni sürümde satın alanlara bildirim gider.
     */
    private function publish(Product $product): void
    {
        $firstPublication = $product->published_at === null;

        $product->forceFill([
            'status' => ProductStatus::Published,
            'published_at' => $product->published_at ?? now(),
            'moderation_note' => null,
        ])->save();

        $version = $product->versions()->firstOrCreate(['version' => $product->current_version], ['released_at' => now()]);

        if ($firstPublication) {
            $followers = User::query()->whereIn('id', DB::table('follows')->where('creator_id', $product->creator_id)->select('follower_id'))->get();
            $this->notifier->send($followers, new CreatorPublishedNotification($product->creator->creatorProfile?->store_name ?? $product->creator->username, $product->title, $product->slug));
        } elseif ($version->wasRecentlyCreated) {
            $owners = User::query()->whereIn('id', DB::table('purchase_entitlements')->where('product_id', $product->id)->whereNull('revoked_at')->select('user_id'))->get();
            $this->notifier->send($owners, new ProductUpdatedNotification($product->title, $product->slug, $version->version));
        }
    }

    private function refreshCover(Product $product): void
    {
        $product->forceFill(['cover_image_path' => $product->images()->orderBy('sort_order')->value('path')])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'price_cents' => $data['price_cents'],
            'preview_video_url' => $data['preview_video_url'] ?? null,
            'current_version' => $data['current_version'] ?? '1.0',
            'requires_supports' => isset($data['requires_supports']) && $data['requires_supports'] !== '' ? (bool) $data['requires_supports'] : null,
            'print_time_minutes' => $data['print_time_minutes'] ?? null,
            'filament_type' => $data['filament_type'] ?? null,
            'printer_compatibility' => $data['printer_compatibility'] ?? null,
            'print_technology' => $data['print_technology'] ?? null,
            'dimension_x_mm' => $data['dimension_x_mm'] ?? null,
            'dimension_y_mm' => $data['dimension_y_mm'] ?? null,
            'dimension_z_mm' => $data['dimension_z_mm'] ?? null,
            'print_settings' => array_filter([
                'layer_height_mm' => $data['layer_height_mm'] ?? null,
                'infill_percent' => $data['infill_percent'] ?? null,
                'notes' => $data['print_notes'] ?? null,
            ], fn ($value) => filled($value)) ?: null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    /**
     * Kişisel lisans her zaman ürün fiyatıyla tanımlıdır. Ticari lisans isteğe bağlıdır.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLicenses(Product $product, array $data): void
    {
        $personal = License::query()->where('code', License::Personal)->firstOrFail();
        $product->licenses()->updateOrCreate(['license_id' => $personal->id], ['price_cents' => $product->price_cents, 'is_default' => true, 'is_active' => true]);

        $commercial = License::query()->where('code', License::Commercial)->first();

        if ($commercial !== null) {
            $commercialCents = $data['commercial_price_cents'] ?? null;

            if ($commercialCents !== null && ! $product->is_free) {
                $product->licenses()->updateOrCreate(['license_id' => $commercial->id], ['price_cents' => $commercialCents, 'is_default' => false, 'is_active' => true]);
            } else {
                $product->licenses()->where('license_id', $commercial->id)->update(['is_active' => false]);
            }
        }

        $product->unsetRelation('licenses');
    }

    private function syncTags(Product $product, ?string $tags): void
    {
        $names = collect(explode(',', (string) $tags))
            ->map(fn (string $tag) => Str::limit(trim(preg_replace('/\s+/', ' ', $tag)), 40, ''))
            ->filter()
            ->unique(fn (string $tag) => Str::slug($tag))
            ->take(10);

        $ids = $names->map(fn (string $name) => Tag::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id);

        $product->tags()->sync($ids->filter()->all());
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($source), 150, '') ?: 'model';
        $slug = $base;

        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
