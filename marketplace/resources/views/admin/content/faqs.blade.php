<x-layouts.admin :title="__('Sık sorulan sorular')">
    <form method="POST" action="{{ route('admin.content.faqs.store') }}" class="card mb-6 grid gap-3 p-5 sm:grid-cols-[6rem_1fr]">
        @csrf
        <select name="locale" class="input"><option value="tr">TR</option><option value="en">EN</option></select>
        <input name="question" class="input" placeholder="{{ __('Soru') }}" required>
        <textarea name="answer" rows="3" class="input sm:col-span-2" placeholder="{{ __('Cevap') }}" required></textarea>
        <div class="sm:col-span-2"><button class="btn-primary">{{ __('Soru ekle') }}</button></div>
    </form>
    <div class="space-y-3">
        @forelse ($faqs as $faq)
            <form method="POST" action="{{ route('admin.content.faqs.update', $faq) }}" class="card grid gap-2 p-4 sm:grid-cols-[5rem_1fr_5rem]">
                @csrf @method('PUT')
                <select name="locale" class="input py-1.5"><option value="tr" @selected($faq->locale === 'tr')>TR</option><option value="en" @selected($faq->locale === 'en')>EN</option></select>
                <input name="question" value="{{ $faq->question }}" class="input py-1.5" required>
                <input name="sort_order" type="number" value="{{ $faq->sort_order }}" class="input py-1.5">
                <textarea name="answer" rows="2" class="input sm:col-span-3" required>{{ $faq->answer }}</textarea>
                <div class="flex items-center gap-3 sm:col-span-3"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($faq->is_active) class="accent-katman-600">{{ __('Aktif') }}</label><button class="btn-secondary px-3 py-1.5">{{ __('Kaydet') }}</button><button type="submit" form="delete-faq-{{ $faq->id }}" class="btn-ghost px-3 py-1.5 text-red-600">{{ __('Sil') }}</button></div>
            </form>
            <form id="delete-faq-{{ $faq->id }}" method="POST" action="{{ route('admin.content.faqs.destroy', $faq) }}" class="hidden">@csrf @method('DELETE')</form>
        @empty
            <p class="text-sm text-muted">{{ __('Henüz soru yok.') }}</p>
        @endforelse
    </div>
</x-layouts.admin>
