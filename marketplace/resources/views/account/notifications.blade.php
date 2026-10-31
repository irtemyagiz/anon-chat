<x-layouts.account :title="__('Bildirimler')">
    @if ($notifications->isEmpty())
        <x-empty-state icon="mail" :title="__('Henüz bildirim yok')" :description="__('Satın alma, satış, onay, hakediş ve yorum yanıtları gibi gelişmeler burada görünür.')"/>
    @else
        <div class="mb-4 flex justify-end">
            <form method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn-secondary px-3 py-1.5">{{ __('Tümünü okundu yap') }}</button></form>
        </div>
        <div class="card divide-y divide-line">
            @foreach ($notifications as $notification)
                <a href="{{ route('account.notifications.open', $notification->id) }}" @class(['flex items-start gap-3 p-4 hover:bg-surface-2', 'bg-katman-50/50 dark:bg-katman-950/50' => $notification->read_at === null])>
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-filament-500' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null])></span>
                    <span class="min-w-0">
                        <span class="block font-medium">{{ $notification->data['title'] ?? '' }}</span>
                        <span class="block text-sm text-muted">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-1 block text-xs text-subtle">{{ $notification->created_at->format('d.m.Y H:i') }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</x-layouts.account>
