<x-layouts.admin :title="__('Yorum moderasyonu')">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['sikayetli' => __('Şikâyet edilen'), 'gizli' => __('Gizlenen'), 'tumu' => __('Tüm yorumlar'), 'degerlendirmeler' => __('Değerlendirmeler')] as $key => $label)
            <a href="{{ route('admin.moderation.comments', ['tur' => $key]) }}" @class(['chip', 'border-ink bg-ink text-canvas' => $tab === $key, 'hover:text-ink' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </div>
    <div class="card divide-y divide-line">
        @forelse ($comments as $item)
            <div class="flex flex-col gap-3 p-4 text-sm sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p><span class="font-semibold">{{ '@'.$item->user->username }}</span> · <a href="{{ route('products.show', $item->product) }}" class="text-muted hover:underline" target="_blank">{{ $item->product->title }}</a> <span class="text-xs text-subtle">· {{ $item->created_at->format('d.m.Y H:i') }}</span></p>
                    @if ($tab === 'degerlendirmeler')<p class="text-filament-500">{{ str_repeat('★', $item->rating) }}</p>@endif
                    <p class="mt-1 whitespace-pre-line text-muted">{{ Str::limit($item->body, 400) ?: '—' }}</p>
                    <p class="mt-1 text-xs"><x-status-badge :status="$item->status === 'published' ? 'completed' : 'rejected'"/>@if ($tab !== 'degerlendirmeler' && $item->reports_count) <span class="text-red-600 dark:text-red-400">{{ trans_choice(':count şikâyet|:count şikâyet', $item->reports_count, ['count' => $item->reports_count]) }}</span>@endif</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    @if ($tab === 'degerlendirmeler')
                        <form method="POST" action="{{ route('admin.moderation.reviews.update', $item) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $item->status === 'published' ? 'hidden' : 'published' }}"><button class="btn-secondary px-3 py-1.5 text-xs">{{ $item->status === 'published' ? __('Gizle') : __('Yayınla') }}</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.moderation.comments.update', $item) }}">@csrf @method('PUT')<input type="hidden" name="action" value="{{ $item->status === 'published' ? 'hide' : 'publish' }}"><button class="btn-secondary px-3 py-1.5 text-xs">{{ $item->status === 'published' ? __('Gizle') : __('Yayınla') }}</button></form>
                        <form method="POST" action="{{ route('admin.moderation.comments.update', $item) }}">@csrf @method('PUT')<input type="hidden" name="action" value="delete"><button class="btn-ghost px-3 py-1.5 text-xs text-red-600">{{ __('Sil') }}</button></form>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-muted">{{ __('Kayıt yok.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $comments->links() }}</div>
</x-layouts.admin>
