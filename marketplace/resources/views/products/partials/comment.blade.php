@php $isCreator = $comment->user_id === $product->creator_id; @endphp
<article class="py-4" x-data="{ reply: false, report: false }">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-semibold">{{ $comment->user->first_name }}</span>
        @if ($isCreator)<span class="chip border-katman-200 text-katman-700 dark:border-katman-800 dark:text-katman-300">{{ __('Tasarımcı') }}</span>@endif
        <span class="text-xs text-subtle">{{ $comment->created_at->diffForHumans() }}</span>
    </div>
    <p class="mt-1 text-sm whitespace-pre-line text-muted">{{ $comment->body }}</p>
    @auth
        <div class="mt-2 flex gap-3 text-xs text-subtle">
            @if ($comment->parent_id === null && auth()->user()->hasVerifiedEmail())<button type="button" @click="reply = ! reply" class="hover:text-ink">{{ __('Yanıtla') }}</button>@endif
            @if ($comment->user_id === auth()->id())
                <form method="POST" action="{{ route('comments.destroy', $comment) }}">@csrf @method('DELETE')<button class="hover:text-ink">{{ __('Sil') }}</button></form>
            @else
                <button type="button" @click="report = ! report" class="hover:text-ink">{{ __('Şikâyet et') }}</button>
            @endif
        </div>
        <form x-show="report" x-cloak method="POST" action="{{ route('comments.report', $comment) }}" class="mt-2 flex gap-2">@csrf
            <select name="reason" class="input py-1.5 text-xs"><option value="spam">{{ __('Spam') }}</option><option value="abuse">{{ __('Hakaret / taciz') }}</option><option value="other">{{ __('Diğer') }}</option></select>
            <button class="btn-secondary shrink-0 px-3 py-1.5 text-xs">{{ __('Gönder') }}</button>
        </form>
        @if ($comment->parent_id === null)
            <form x-show="reply" x-cloak method="POST" action="{{ route('products.comment', $product) }}" class="mt-2 flex gap-2">@csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <input name="body" class="input py-1.5 text-sm" placeholder="{{ __('Yanıt yaz') }}" required minlength="2" maxlength="2000">
                <button class="btn-secondary shrink-0 px-3 py-1.5">{{ __('Gönder') }}</button>
            </form>
        @endif
    @endauth
    @if ($comment->relationLoaded('replies') && $comment->replies->isNotEmpty())
        <div class="mt-3 ml-4 border-l-2 border-line pl-4">
            @foreach ($comment->replies as $reply)
                @include('products.partials.comment', ['comment' => $reply, 'product' => $product])
            @endforeach
        </div>
    @endif
</article>
