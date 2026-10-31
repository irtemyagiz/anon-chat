<x-layouts.app :title="__('Blog')">
    <div class="container-page py-12">
        <x-section-heading :eyebrow="__('Katman')" :title="__('Blog')"/>
        @if ($posts->isEmpty())
            <x-empty-state icon="sparkles" :title="__('Henüz yazı yok')"/>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="card p-6 transition hover:border-katman-300 dark:hover:border-katman-700">
                        <p class="text-xs text-subtle">{{ ($post->published_at ?? $post->created_at)->format('d.m.Y') }}</p>
                        <h2 class="mt-2 text-lg font-bold">{{ $post->title }}</h2>
                        @if ($post->excerpt)<p class="mt-2 text-sm text-muted">{{ $post->excerpt }}</p>@endif
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layouts.app>
