<x-layouts.admin :title="__('SEO ayarları')">
    <form method="POST" action="{{ route('admin.seo.update') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @method('PUT')
        <div class="card flex flex-wrap items-center gap-4 p-5">
            @if ($defaultImage)<img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($defaultImage) }}" alt="" class="h-16 rounded-lg">@endif
            <div class="flex-1"><label class="label" for="og_image">{{ __('Varsayılan paylaşım görseli (Open Graph, 1200×630 önerilir)') }}</label><input id="og_image" type="file" name="og_image" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm"></div>
        </div>
        @foreach (\App\Models\SeoSetting::ROUTES as $route => $label)
            <div class="card p-5">
                <p class="font-bold">{{ __($label) }} <span class="font-mono text-xs font-normal text-subtle">{{ $route }}</span></p>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    @foreach (['tr' => 'TR', 'en' => 'EN'] as $locale => $code)
                        @php $entry = $entries[$route.'|'.$locale] ?? null; @endphp
                        <div class="space-y-2 rounded-xl bg-surface-2 p-3">
                            <p class="text-xs font-semibold">{{ $code }}</p>
                            <input name="seo[{{ $route }}][{{ $locale }}][meta_title]" value="{{ $entry?->meta_title }}" class="input py-2" placeholder="{{ __('Meta başlık') }}" maxlength="160">
                            <textarea name="seo[{{ $route }}][{{ $locale }}][meta_description]" rows="2" class="input" placeholder="{{ __('Meta açıklama') }}" maxlength="300">{{ $entry?->meta_description }}</textarea>
                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="seo[{{ $route }}][{{ $locale }}][noindex]" value="1" @checked($entry?->noindex) class="accent-katman-600">{{ __('Arama motorlarında gösterme (noindex)') }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <button class="btn-primary">{{ __('Kaydet') }}</button>
    </form>
</x-layouts.admin>
