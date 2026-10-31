@php $mediaDisk = Storage::disk(config('marketplace.storage.public_media_disk')); @endphp
<x-layouts.creator :title="__('Mağaza Ayarları')">
    <form method="POST" action="{{ route('creator.store.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Mağaza bilgileri') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="store_name" :label="__('Mağaza adı')" :value="$profile->store_name" required/>
                <x-field name="designer_name" :label="__('Tasarımcı adı')" :value="$profile->designer_name" required/>
            </div>
            <div>
                <label for="description" class="label">{{ __('Mağaza açıklaması') }}</label>
                <textarea id="description" name="description" rows="4" maxlength="1000" class="input">{{ old('description', $profile->description) }}</textarea>
            </div>
            <p class="text-xs text-subtle">{{ __('Mağaza adresi: :url', ['url' => route('designers.show', $profile)]) }}</p>
        </section>
        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Logo ve kapak görseli') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="logo">{{ __('Logo (kare önerilir)') }}</label>
                    @if ($profile->logo_path)<img src="{{ $mediaDisk->url($profile->logo_path) }}" alt="" class="mb-3 size-20 rounded-2xl object-cover">@endif
                    <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
                    @error('logo')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="cover">{{ __('Kapak görseli (yatay)') }}</label>
                    @if ($profile->cover_path)<img src="{{ $mediaDisk->url($profile->cover_path) }}" alt="" class="mb-3 h-20 w-full rounded-2xl object-cover">@endif
                    <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
                    @error('cover')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
        <section class="card space-y-5 p-6">
            <h2 class="font-bold">{{ __('Sosyal medya bağlantıları') }}</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['website' => __('Web sitesi'), 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'tiktok' => 'TikTok'] as $key => $label)
                    <div>
                        <label for="social_{{ $key }}" class="label">{{ $label }}</label>
                        <input id="social_{{ $key }}" type="url" name="social[{{ $key }}]" value="{{ old("social.$key", $profile->social_links[$key] ?? '') }}" class="input" placeholder="https://">
                        @error("social.$key")<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>
        <div class="flex justify-end"><button class="btn-primary px-8">{{ __('Kaydet') }}</button></div>
    </form>
</x-layouts.creator>
