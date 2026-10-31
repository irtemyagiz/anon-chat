<x-layouts.admin :title="__('Webhook kayıtları')">
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="border-b border-line text-left text-xs text-muted uppercase"><tr><th class="px-4 py-3">{{ __('Tarih') }}</th><th class="px-4 py-3">{{ __('Sağlayıcı / olay') }}</th><th class="px-4 py-3">{{ __('İmza') }}</th><th class="px-4 py-3">{{ __('Durum') }}</th><th class="px-4 py-3">IP</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-muted">{{ $event->created_at->format('d.m.Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $event->provider }} · {{ $event->event_type ?? '—' }}<span class="block font-mono text-[0.65rem] text-subtle">{{ Str::limit($event->event_id, 40) }}</span>@if ($event->processing_error)<span class="block text-xs text-red-600 dark:text-red-400">{{ Str::limit($event->processing_error, 120) }}</span>@endif</td>
                        <td class="px-4 py-3"><x-status-badge :status="$event->signature_valid ? 'completed' : 'failed'"/></td>
                        <td class="px-4 py-3"><x-status-badge :status="match ($event->status->value) { 'processed' => 'completed', 'ignored' => 'unpublished', 'failed' => 'failed', default => 'pending' }"/></td>
                        <td class="px-4 py-3 font-mono text-xs text-muted">{{ $event->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-muted">{{ __('Webhook kaydı yok.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $events->links() }}</div>
</x-layouts.admin>
