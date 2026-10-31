<x-layouts.account :title="__('Destek taleplerim')">
    <div class="mb-4 flex justify-end"><a href="{{ route('support.create') }}" class="btn-primary">{{ __('Yeni talep') }}</a></div>
    @if ($tickets->isEmpty())
        <x-empty-state icon="mail" :title="__('Henüz destek talebin yok')" :description="__('Ödeme, indirme, hakediş veya hesabınla ilgili bir sorun yaşarsan buradan bize yazabilirsin.')">
            <a href="{{ route('faq') }}" class="btn-secondary">{{ __('Sık sorulan sorular') }}</a>
        </x-empty-state>
    @else
        <div class="card divide-y divide-line">
            @foreach ($tickets as $ticket)
                <a href="{{ route('support.show', $ticket) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-surface-2">
                    <span><span class="font-semibold">{{ $ticket->subject }}</span><span class="block text-xs text-muted">{{ __(\App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category) }} · {{ $ticket->last_reply_at?->diffForHumans() }}</span></span>
                    <x-status-badge :status="match ($ticket->status) { 'answered' => 'completed', 'closed' => 'unpublished', default => 'pending' }"/>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $tickets->links() }}</div>
    @endif
</x-layouts.account>
