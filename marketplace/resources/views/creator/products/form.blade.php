@php
    $editing = $product->exists;
    $mediaDisk = Storage::disk(config('marketplace.storage.public_media_disk'));
    $commercial = $editing ? $product->licenses->first(fn ($l) => $l->license->code === 'commercial' && $l->is_active) : null;
    $missing = $editing ? app(\App\Services\Products\ProductManager::class)->missingRequirements($product) : [];
    $status = $product->status?->value ?? 'draft';
    $fmt = fn (?int $cents) => $cents === null ? '' : \App\Services\Points\PointConverter::formatCents($cents);
    $uploadLimit = ini_get('upload_max_filesize');
@endphp
<x-layouts.creator :title="$editing ? __('Ürünü düzenle') : __('Yeni ürün ekle')">
    @if ($editing)
        <div class="card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2"><span class="text-sm text-muted">{{ __('Durum') }}:</span><x-status-badge :status="$product->status"/></div>
                @if ($product->moderation_note)<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ __('İnceleme notu') }}: {{ $product->moderation_note }}</p>@endif
                @if ($missing && in_array($status, ['draft', 'rejected', 'unpublished'], true))
                    <ul class="mt-2 list-inside list-disc text-xs text-muted">@foreach ($missing as $item)<li>{{ $item }}</li>@endforeach</ul>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($product->isPublished())
                    <a href="{{ route('products.show', $product) }}" class="btn-ghost">{{ __('Ürün sayfası') }}</a>
                @endif
                @if (in_array($status, ['draft', 'rejected', 'unpublished'], true))
                    <form method="POST" action="{{ route('creator.products.submit', $product) }}">@csrf
                        <button class="btn-primary" @disabled($missing)>{{ config('marketplace.products.require_review') ? __('İncelemeye gönder') : __('Yayınla') }}</button>
                    </form>
                @endif
                @if (in_array($status, ['published', 'pending_review'], true))
                    <form method="POST" action="{{ route('creator.products.unpublish', $product) }}" x-data @submit="if (! confirm(@js(__('Ürün yayından kaldırılsın mı?')))) $event.preventDefault()">@csrf
                        <button class="btn-secondary">{{ __('Yayından kaldır') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Silme formları ana formun dışında tutulur (iç içe form olamaz); butonlar form="" ile bağlanır --}}
        @foreach ($product->images as $image)
            <form id="delete-image-{{ $image->id }}" method="POST" action="{{ route('creator.products.images.destroy', [$product, $image]) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach
        @foreach ($product->files as $file)
            <form id="delete-file-{{ $file->uuid }}" method="POST" action="{{ route('creator.products.files.destroy', [$product, $file]) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach
        <form id="delete-preview" method="POST" action="{{ route('creator.products.preview.destroy', $product) }}" class="hidden">@csrf @method('DELETE')</form>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('creator.products.update', $product) : route('creator.products.store') }}" class="space-y-6"
          x-data="{ price: @js(old('price', $editing ? $fmt($product->price_cents) : '')), points() { const v = parseFloat(String(this.price).replace(',', '.')); return isNaN(v) ? 0 : Math.round(v * 100); } }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Temel bilgiler') }}</h2>
            <x-field name="title" :label="__('Ürün başlığı')" :value="$product->title" required maxlength="160"/>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="slug" :label="__('SEO adresi (isteğe bağlı)')" :value="$product->slug" :hint="__('Boş bırakırsan başlıktan oluşturulur. Örn. moduler-masa-organizeri')"/>
                <div>
                    <label for="category_id" class="label">{{ __('Kategori / alt kategori') }}</label>
                    <select id="category_id" name="category_id" class="input" required>
                        <option value="">{{ __('Seçin') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option['id'] }}" @selected((int) old('category_id', $product->category_id) === $option['id'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-field name="short_description" :label="__('Kısa açıklama')" :value="$product->short_description" maxlength="300" :hint="__('Kartlarda ve arama sonuçlarında görünür (en fazla 300 karakter).')"/>
            <div>
                <label for="description" class="label">{{ __('Açıklama') }}</label>
                <textarea id="description" name="description" rows="8" class="input" maxlength="20000">{{ old('description', $product->description) }}</textarea>
                @error('description')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <x-field name="tags" :label="__('Etiketler')" :value="$product->tags?->pluck('name')->implode(', ')" :hint="__('Virgülle ayırın, en fazla 10 etiket. Örn. organizer, masa, modüler')"/>
        </section>

        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Fiyat ve lisans') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="price" class="label">{{ __('Kişisel lisans fiyatı (USD)') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-subtle">$</span>
                        <input id="price" name="price" x-model="price" inputmode="decimal" class="input pl-7" placeholder="3.99" required>
                    </div>
                    <p class="mt-1.5 text-xs text-subtle" x-text="points() === 0 ? @js(__('0 girersen ürün ücretsiz yayınlanır.')) : points().toLocaleString('tr-TR') + ' POINT'"></p>
                    @error('price')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <x-field name="commercial_price" :label="__('Ticari lisans fiyatı (USD, isteğe bağlı)')" :value="$fmt($commercial?->price_cents)" inputmode="decimal" placeholder="9.99" :hint="__('Alıcı baskıları satabilir. Boş bırakırsan yalnızca kişisel lisans sunulur.')"/>
            </div>
        </section>

        <section class="card space-y-4 p-6">
            <div class="flex items-center justify-between"><h2 class="font-bold">{{ __('Görseller') }}</h2><span class="text-xs text-subtle">{{ __('JPG, PNG, WebP · en fazla 10 MB · ilk görsel kapak olur') }}</span></div>
            @if ($editing && $product->images->isNotEmpty())
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($product->images as $image)
                        <div class="group relative aspect-square overflow-hidden rounded-xl border border-line">
                            <img src="{{ $mediaDisk->url($image->path) }}" alt="" class="size-full object-cover" loading="lazy">
                            <button type="submit" form="delete-image-{{ $image->id }}" class="absolute top-1.5 right-1.5 rounded-full bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100 focus:opacity-100" aria-label="{{ __('Görseli kaldır') }}"><x-icon name="x" class="size-4"/></button>
                        </div>
                    @endforeach
                </div>
            @endif
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium">
            @error('images.*')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </section>

        <section class="card space-y-4 p-6">
            <div class="flex items-center justify-between"><h2 class="font-bold">{{ __('İndirilebilir dosyalar') }}</h2><span class="text-xs text-subtle">STL, 3MF, OBJ, STEP, ZIP, RAR, PDF, SVG, DXF</span></div>
            <p class="text-xs text-muted">{{ __('Dosyalar herkese açık olmayan özel depolamada tutulur ve yalnızca satın alanlar indirebilir. Her dosya güvenlik taramasından geçer.') }}</p>
            @if ($editing && $product->files->isNotEmpty())
                <ul class="divide-y divide-line rounded-xl border border-line">
                    @foreach ($product->files as $file)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="min-w-0 truncate">{{ $file->original_name }} <span class="text-xs text-subtle">· {{ Number::fileSize($file->size_bytes) }}</span></span>
                            <span class="flex shrink-0 items-center gap-2">
                                <x-status-badge :status="$file->scan_status"/>
                                <button type="submit" form="delete-file-{{ $file->uuid }}" class="btn-ghost p-1.5" aria-label="{{ __('Dosyayı kaldır') }}"><x-icon name="x" class="size-4"/></button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <input type="file" name="files[]" multiple class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium">
            <p class="text-xs text-subtle">{{ __('Sunucunun dosya başına yükleme sınırı: :limit', ['limit' => $uploadLimit]) }}</p>
            @error('files.*')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </section>

        <section class="card space-y-4 p-6">
            <div class="flex items-center justify-between"><h2 class="font-bold">{{ __('3D önizleme (isteğe bağlı)') }}</h2><span class="text-xs text-subtle">STL, OBJ, 3MF · {{ config('marketplace.storage.max_preview_size_mb') }} MB</span></div>
            <p class="text-xs text-muted">{{ __('Önizleme dosyası ürün sayfasında herkese gösterilir. Satılan dosyanın kendisini değil, detayı azaltılmış bir sürümünü yükleyin.') }}</p>
            @if ($editing && $product->preview_model_path)
                <div class="flex items-center justify-between rounded-xl border border-line px-4 py-2.5 text-sm">
                    <span class="flex items-center gap-2"><x-icon name="cube" class="size-4"/>{{ __('Önizleme yüklü') }} ({{ strtoupper(pathinfo($product->preview_model_path, PATHINFO_EXTENSION)) }})</span>
                    <button type="submit" form="delete-preview" class="btn-ghost px-2 py-1 text-xs">{{ __('Kaldır') }}</button>
                </div>
            @endif
            <input type="file" name="preview" accept=".stl,.obj,.3mf" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm file:font-medium">
            @error('preview')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </section>

        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Baskı bilgileri') }}</h2>
            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="print_technology" class="label">{{ __('Baskı teknolojisi') }}</label>
                    <select id="print_technology" name="print_technology" class="input">
                        <option value="">—</option>
                        @foreach (['fdm' => 'FDM (filament)', 'sla' => 'SLA / reçine', 'sls' => 'SLS', 'other' => __('Diğer')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('print_technology', $product->print_technology) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="requires_supports" class="label">{{ __('Destek gerekir mi?') }}</label>
                    <select id="requires_supports" name="requires_supports" class="input">
                        @php $supports = old('requires_supports', $product->requires_supports === null ? '' : (int) $product->requires_supports); @endphp
                        <option value="">—</option>
                        <option value="1" @selected((string) $supports === '1')>{{ __('Evet') }}</option>
                        <option value="0" @selected((string) $supports === '0')>{{ __('Hayır') }}</option>
                    </select>
                </div>
                <x-field name="print_time_minutes" type="number" :label="__('Baskı süresi (dakika)')" :value="$product->print_time_minutes" min="1"/>
                <x-field name="filament_type" :label="__('Filament / malzeme')" :value="$product->filament_type" placeholder="PLA, PETG…"/>
                <x-field name="layer_height_mm" type="number" step="0.01" :label="__('Katman yüksekliği (mm)')" :value="$product->print_settings['layer_height_mm'] ?? null"/>
                <x-field name="infill_percent" type="number" :label="__('Doluluk (%)')" :value="$product->print_settings['infill_percent'] ?? null" min="0" max="100"/>
                <x-field name="dimension_x_mm" type="number" step="0.01" :label="__('Genişlik X (mm)')" :value="$product->dimension_x_mm"/>
                <x-field name="dimension_y_mm" type="number" step="0.01" :label="__('Derinlik Y (mm)')" :value="$product->dimension_y_mm"/>
                <x-field name="dimension_z_mm" type="number" step="0.01" :label="__('Yükseklik Z (mm)')" :value="$product->dimension_z_mm"/>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="printer_compatibility" :label="__('Yazıcı uyumluluğu')" :value="$product->printer_compatibility" :placeholder="__('Örn. 220×220 mm tabla ve üzeri')"/>
                <x-field name="current_version" :label="__('Model sürümü')" :value="$product->current_version ?? '1.0'"/>
            </div>
            <x-field name="print_notes" :label="__('Baskı önerileri')" :value="$product->print_settings['notes'] ?? null"/>
            <x-field name="preview_video_url" type="url" :label="__('Video önizleme (YouTube / Vimeo, isteğe bağlı)')" :value="$product->preview_video_url" placeholder="https://www.youtube.com/watch?v=…"/>
        </section>

        <section class="card space-y-5 p-6" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between font-bold">{{ __('SEO ayarları (isteğe bağlı)') }} <x-icon name="chevron-down" class="size-4"/></button>
            <div x-show="open" x-cloak class="space-y-5">
                <x-field name="meta_title" :label="__('Meta başlık')" :value="$product->meta_title" maxlength="160"/>
                <x-field name="meta_description" :label="__('Meta açıklama')" :value="$product->meta_description" maxlength="300"/>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('creator.products.index') }}" class="btn-secondary">{{ __('Vazgeç') }}</a>
            <button class="btn-primary px-8">{{ $editing ? __('Değişiklikleri kaydet') : __('Taslak olarak kaydet') }}</button>
        </div>
    </form>
</x-layouts.creator>
