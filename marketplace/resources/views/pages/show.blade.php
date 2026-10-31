<x-layouts.app :title="$page->meta_title ?: $page->title" :description="$page->meta_description ?: $page->excerpt">
    <article class="container-page max-w-3xl py-12">
        <p class="eyebrow">{{ __(\App\Models\Page::TYPES[$page->type] ?? '') }}</p>
        <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">{{ $page->title }}</h1>
        <p class="mt-2 text-sm text-subtle">{{ __('Son güncelleme: :date', ['date' => $page->updated_at->format('d.m.Y')]) }}</p>
        <div class="prose-katman mt-8">{!! $page->html() !!}</div>
    </article>
</x-layouts.app>
