<x-layouts.admin :title="__('Loglar')">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['denetim' => __('Denetim kayıtları'), 'yonetici' => __('Yönetici işlemleri'), 'guvenlik' => __('Güvenlik olayları')] as $key => $label)
            <a href="{{ route('admin.logs', ['tur' => $key]) }}" @class(['chip', 'border-ink bg-ink text-canvas' => $tab === $key, 'hover:text-ink' => $tab !== $key])>{{ $label }}</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[48rem] text-sm">
            <tbody class="divide-y divide-line">
                @forelse ($records as $record)
                    <tr>
                        <td class="px-4 py-3 align-top whitespace-nowrap text-muted">{{ \Illuminate\Support\Carbon::parse($record->created_at)->format('d.m.Y H:i:s') }}</td>
                        @if ($tab === 'denetim')
                            <td class="px-4 py-3 align-top"><span class="font-mono text-xs font-semibold">{{ $record->event }}</span><span class="block text-xs text-muted">{{ $record->actor?->username ?? __('sistem') }} · {{ class_basename((string) $record->auditable_type) }} #{{ $record->auditable_id }}</span></td>
                            <td class="px-4 py-3 align-top font-mono text-[0.7rem] text-muted">@if ($record->old_values)<span class="text-red-600 dark:text-red-400">- {{ json_encode($record->old_values, JSON_UNESCAPED_UNICODE) }}</span><br>@endif @if ($record->new_values)<span class="text-katman-700 dark:text-katman-300">+ {{ json_encode($record->new_values, JSON_UNESCAPED_UNICODE) }}</span><br>@endif @if ($record->context){{ json_encode($record->context, JSON_UNESCAPED_UNICODE) }}@endif</td>
                            <td class="px-4 py-3 align-top font-mono text-xs text-subtle">{{ $record->ip_address }}</td>
                        @elseif ($tab === 'yonetici')
                            <td class="px-4 py-3 align-top"><span class="font-mono text-xs font-semibold">{{ $record->action }}</span><span class="block text-xs text-muted">{{ $record->username }} · {{ $record->method }} /{{ $record->route }}</span></td>
                            <td class="px-4 py-3 align-top font-mono text-[0.7rem] break-all text-muted">{{ Str::limit($record->payload, 300) }}</td>
                            <td class="px-4 py-3 align-top font-mono text-xs text-subtle">{{ $record->ip_address }}</td>
                        @else
                            <td class="px-4 py-3 align-top"><span class="font-mono text-xs font-semibold">{{ $record->type }}</span> <x-status-badge :status="$record->severity === 'high' ? 'failed' : 'pending'"/><span class="block text-xs text-muted">{{ $record->username ?? '—' }}</span></td>
                            <td class="px-4 py-3 align-top font-mono text-[0.7rem] break-all text-muted">{{ Str::limit($record->context, 300) }}</td>
                            <td class="px-4 py-3 align-top font-mono text-xs text-subtle">{{ $record->ip_address }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-muted">{{ __('Kayıt yok.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $records->links() }}</div>
</x-layouts.admin>
