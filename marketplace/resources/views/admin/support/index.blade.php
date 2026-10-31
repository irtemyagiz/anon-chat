<x-layouts.admin :title="__('Destek talepleri')">
    @include('admin.partials.status-tabs', ['route' => 'admin.support.index', 'current' => $status, 'tabs' => ['aktif' => __('Yanıt bekleyen'), 'answered' => __('Yanıtlandı'), 'closed' => __('Kapatıldı'), 'tumu' => __('Tümü')]])
    @if ($tickets->isEmpty())
        <x-empty-state icon="mail" :title="__('Bu durumda talep yok')"/>
    @else
        <div class="card divide-y divide-line">
            @foreach ($tickets as $ticket)
                <a href="{{ route('admin.support.show', $ticket) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm hover:bg-surface-2">
                    <span>
                        <span class="font-semibold">{{ $ticket->subject }}</span> @if ($ticket->priority === 'high')<span class="chip border-red-200 text-red-700 dark:border-red-900 dark:text-red-300">{{ __('Öncelikli') }}</span>@endif
                        <span class="block text-xs text-muted">{{ $ticket->user->email }} · {{ __(\App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category) }} · {{ $ticket->last_reply_at?->diffForHumans() }}@if ($ticket->assignee) · {{ '@'.$ticket->assignee->username }}@endif</span>
                    </span>
                    <x-status-badge :status="match ($ticket->status) { 'answered' => 'completed', 'closed' => 'unpublished', default => 'pending' }"/>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $tickets->links() }}</div>
    @endif
</x-layouts.admin>
