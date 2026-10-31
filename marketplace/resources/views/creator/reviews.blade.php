<x-layouts.creator :title="__('Yorumlar')">
    <section class="card p-6">
        <h2 class="font-bold">{{ __('Değerlendirmeler') }}</h2>
        <div class="mt-3 divide-y divide-line">
            @forelse ($reviews as $review)
                <div class="py-4 text-sm">
                    <p><span class="text-filament-500">{{ str_repeat('★', $review->rating) }}</span> <span class="font-semibold">{{ $review->user->first_name }}</span> · <a href="{{ route('products.show', $review->product) }}#degerlendirmeler" class="text-muted hover:underline">{{ $review->product->title }}</a> <span class="text-xs text-subtle">· {{ $review->created_at->diffForHumans() }}</span></p>
                    @if ($review->body)<p class="mt-1 text-muted">{{ $review->body }}</p>@endif
                    @if ($review->creator_reply)
                        <p class="mt-2 rounded-lg bg-surface-2 px-3 py-2 text-xs"><span class="font-semibold">{{ __('Yanıtın') }}:</span> {{ $review->creator_reply }}</p>
                    @else
                        <form method="POST" action="{{ route('reviews.reply', $review) }}" class="mt-2 flex gap-2">@csrf<input name="reply" class="input py-1.5" placeholder="{{ __('Yanıt yaz') }}" required><button class="btn-secondary shrink-0 px-3 py-1.5">{{ __('Yanıtla') }}</button></form>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-muted">{{ __('Henüz değerlendirme yok.') }}</p>
            @endforelse
        </div>
        {{ $reviews->links() }}
    </section>
    <section class="card mt-6 p-6">
        <h2 class="font-bold">{{ __('Son yorumlar ve sorular') }}</h2>
        <div class="mt-3 divide-y divide-line">
            @forelse ($comments as $comment)
                <div class="py-3 text-sm"><span class="font-semibold">{{ '@'.$comment->user->username }}</span> · <a href="{{ route('products.show', $comment->product) }}#yorumlar" class="text-muted hover:underline">{{ $comment->product->title }}</a><p class="mt-1 text-muted">{{ Str::limit($comment->body, 240) }}</p></div>
            @empty
                <p class="py-4 text-sm text-muted">{{ __('Henüz yorum yok.') }}</p>
            @endforelse
        </div>
        {{ $comments->links() }}
    </section>
</x-layouts.creator>
