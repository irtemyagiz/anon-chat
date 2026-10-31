<x-layouts.admin :title="$page->exists ? __('Sayfayı düzenle') : __('Yeni sayfa')">
    <form method="POST" action="{{ $page->exists ? route('admin.content.pages.update', $page) : route('admin.content.pages.store') }}" class="card space-y-5 p-6">
        @csrf @if ($page->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-3">
            <div><label class="label" for="type">{{ __('Tür') }}</label><select id="type" name="type" class="input">@foreach (\App\Models\Page::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type', $page->type) === $value)>{{ __($label) }}</option>@endforeach</select></div>
            <div><label class="label" for="locale">{{ __('Dil') }}</label><select id="locale" name="locale" class="input"><option value="tr" @selected(old('locale', $page->locale) === 'tr')>Türkçe</option><option value="en" @selected(old('locale', $page->locale) === 'en')>English</option></select></div>
            <x-field name="slug" :label="__('Adres (slug)')" :value="$page->slug" :hint="__('Boşsa başlıktan oluşturulur')"/>
        </div>
        <x-field name="title" :label="__('Başlık')" :value="$page->title" required/>
        <x-field name="excerpt" :label="__('Özet (isteğe bağlı)')" :value="$page->excerpt"/>
        <div>
            <label class="label" for="body">{{ __('İçerik (Markdown)') }}</label>
            <textarea id="body" name="body" rows="22" class="input font-mono text-sm" required>{{ old('body', $page->body) }}</textarea>
            <p class="mt-1.5 text-xs text-subtle">{{ __('## Başlık, **kalın**, - liste, [bağlantı](https://…) biçimleri desteklenir. HTML etiketleri güvenlik nedeniyle temizlenir.') }}</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="meta_title" :label="__('Meta başlık')" :value="$page->meta_title"/>
            <x-field name="meta_description" :label="__('Meta açıklama')" :value="$page->meta_description"/>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published)) class="accent-katman-600">{{ __('Yayında') }}</label>
            <label class="text-xs text-muted">{{ __('Yayın tarihi (isteğe bağlı)') }} <input type="datetime-local" name="published_at" value="{{ $page->published_at?->format('Y-m-d\TH:i') }}" class="input mt-1"></label>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn-primary">{{ __('Kaydet') }}</button>
            @if ($page->exists && $page->is_published)<a href="{{ $page->type === 'blog' ? route('blog.show', $page->slug) : route('pages.show', $page->slug) }}" target="_blank" class="btn-ghost">{{ __('Sitede görüntüle') }}</a>@endif
            @if ($page->exists)<button type="submit" form="delete-page" class="btn-ghost text-red-600">{{ __('Sil') }}</button>@endif
        </div>
    </form>
    @if ($page->exists)
        <form id="delete-page" method="POST" action="{{ route('admin.content.pages.destroy', $page) }}" class="hidden" x-data @submit="if (! confirm(@js(__('Sayfa silinsin mi?')))) $event.preventDefault()">@csrf @method('DELETE')</form>
    @endif
</x-layouts.admin>
