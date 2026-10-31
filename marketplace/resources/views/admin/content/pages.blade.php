<x-layouts.admin :title="__('Sayfalar ve blog')">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div class="flex gap-2"><a href="{{ route('admin.content.faqs') }}" class="chip hover:text-ink">{{ __('SSS') }}</a><a href="{{ route('admin.content.banners') }}" class="chip hover:text-ink">{{ __('Bannerlar') }}</a></div>
        <a href="{{ route('admin.content.pages.create') }}" class="btn-primary">{{ __('Yeni sayfa') }}</a>
    </div>
    <div class="card divide-y divide-line">
        @forelse ($pages as $page)
            <a href="{{ route('admin.content.pages.edit', $page) }}" class="flex items-center justify-between gap-3 p-4 text-sm hover:bg-surface-2">
                <span><span class="font-semibold">{{ $page->title }}</span><span class="block text-xs text-muted">{{ __(\App\Models\Page::TYPES[$page->type]) }} · {{ strtoupper($page->locale) }} · /{{ $page->type === 'blog' ? 'blog' : 'sayfa' }}/{{ $page->slug }}</span></span>
                <x-status-badge :status="$page->is_published ? 'published' : 'draft'"/>
            </a>
        @empty
            <p class="p-6 text-center text-sm text-muted">{{ __('Henüz sayfa yok.') }}</p>
        @endforelse
    </div>
</x-layouts.admin>
