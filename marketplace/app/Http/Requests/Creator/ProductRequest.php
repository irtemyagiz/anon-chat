<?php

namespace App\Http\Requests\Creator;

use App\Models\Category;
use App\Services\Points\PointConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Ürün oluşturma/düzenleme. Fiyat USD olarak girilir ("3.99") ve sente çevrilir.
 * Dosya alanlarının içerik güvenliği ayrıca UploadInspector ile denetlenir.
 */
class ProductRequest extends FormRequest
{
    /** Ücretli ürün için en düşük fiyat (USD sent). */
    public const MIN_PAID_CENTS = 50;

    public const MAX_PRICE_CENTS = 100_000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9-]+$/'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'string', 'max:10'],
            'commercial_price' => ['nullable', 'string', 'max:10'],
            'preview_video_url' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//'],
            'current_version' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'requires_supports' => ['nullable', Rule::in(['0', '1'])],
            'print_time_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'filament_type' => ['nullable', 'string', 'max:60'],
            'printer_compatibility' => ['nullable', 'string', 'max:255'],
            'print_technology' => ['nullable', Rule::in(['fdm', 'sla', 'sls', 'other'])],
            'dimension_x_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dimension_y_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dimension_z_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'layer_height_mm' => ['nullable', 'numeric', 'min:0.01', 'max:2'],
            'infill_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'print_notes' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'max:10240'],
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.((int) config('marketplace.storage.max_file_size_mb') * 1024)],
            'preview' => ['nullable', 'file', 'max:'.((int) config('marketplace.storage.max_preview_size_mb') * 1024)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['price' => 'price_cents', 'commercial_price' => 'commercial_price_cents'] as $field => $target) {
                if (blank($this->input($field))) {
                    continue;
                }

                try {
                    $cents = PointConverter::parseUsdToCents((string) $this->input($field));
                } catch (InvalidArgumentException) {
                    $validator->errors()->add($field, __('Fiyatı 3.99 biçiminde, en fazla iki ondalık basamakla girin.'));

                    continue;
                }

                if ($cents !== 0 && $cents < self::MIN_PAID_CENTS) {
                    $validator->errors()->add($field, __('Ücretli ürünlerde en düşük fiyat 0.50 USD (50 POINT) olmalıdır.'));
                } elseif ($cents > self::MAX_PRICE_CENTS) {
                    $validator->errors()->add($field, __('Fiyat en fazla 1000 USD olabilir.'));
                }

                $this->merge([$target => $cents]);
            }

            if ($this->filled('commercial_price') && ($this->input('commercial_price_cents') ?? 0) < ($this->input('price_cents') ?? 0)) {
                $validator->errors()->add('commercial_price', __('Ticari lisans fiyatı kişisel lisans fiyatından düşük olamaz.'));
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return [
            ...$this->safe()->except('images', 'files', 'preview', 'price', 'commercial_price'),
            'price_cents' => (int) $this->input('price_cents'),
            'commercial_price_cents' => $this->filled('commercial_price') ? (int) $this->input('commercial_price_cents') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('ürün başlığı'),
            'slug' => __('SEO adresi'),
            'category_id' => __('kategori'),
            'short_description' => __('kısa açıklama'),
            'description' => __('açıklama'),
            'tags' => __('etiketler'),
            'price' => __('fiyat'),
            'commercial_price' => __('ticari lisans fiyatı'),
            'preview_video_url' => __('video bağlantısı'),
            'images.*' => __('görsel'),
            'files.*' => __('dosya'),
            'preview' => __('3D önizleme dosyası'),
        ];
    }

    /**
     * Kategori seçenekleri: üst kategoriler ve alt kategorileri girintili.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function categoryOptions(): array
    {
        $options = [];
        $all = Category::query()->where('is_active', true)->orderBy('sort_order')->get()->groupBy('parent_id');

        $walk = function (?int $parentId, int $depth) use (&$walk, &$options, $all): void {
            foreach ($all->get($parentId ?? '', collect()) as $category) {
                $options[] = ['id' => $category->id, 'label' => str_repeat('— ', $depth).$category->localizedName()];
                $walk($category->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $options;
    }
}
