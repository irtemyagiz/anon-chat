<x-layouts.admin :title="__('Kullanıcılar')">
    <form method="GET" class="card mb-4 grid gap-3 p-3 sm:grid-cols-[1fr_auto_auto_auto]">
        <input name="q" value="{{ request('q') }}" class="input" placeholder="{{ __('E-posta, kullanıcı adı veya ad') }}">
        <select name="rol" class="input"><option value="">{{ __('Tüm roller') }}</option>@foreach (['admin' => __('Yönetici'), 'moderator' => __('Moderatör'), 'creator' => 'Creator', 'customer' => __('Kullanıcı')] as $value => $label)<option value="{{ $value }}" @selected(request('rol') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="durum" class="input"><option value="">{{ __('Tüm durumlar') }}</option>@foreach (['active' => __('Aktif'), 'suspended' => __('Askıda'), 'banned' => __('Engelli')] as $value => $label)<option value="{{ $value }}" @selected(request('durum') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="btn-primary">{{ __('Filtrele') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[44rem] text-sm">
            <thead class="border-b border-line text-left text-xs text-muted uppercase"><tr><th class="px-4 py-3">{{ __('Kullanıcı') }}</th><th class="px-4 py-3">{{ __('Roller') }}</th><th class="px-4 py-3 text-right">{{ __('Puan') }}</th><th class="px-4 py-3">{{ __('Durum') }}</th><th class="px-4 py-3">{{ __('Kayıt') }}</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($users as $user)
                    <tr class="hover:bg-surface-2">
                        <td class="px-4 py-3"><a href="{{ route('admin.users.show', $user) }}" class="font-medium hover:underline">{{ $user->full_name }}</a><span class="block text-xs text-muted">{{ '@'.$user->username }} · {{ $user->email }}</span></td>
                        <td class="px-4 py-3 text-xs">{{ $user->roles->pluck('label')->implode(', ') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $user->wallet?->balance, 0, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$user->status->value === 'active' ? 'completed' : 'rejected'"/></td>
                        <td class="px-4 py-3 text-muted">{{ $user->created_at->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-muted">{{ __('Kullanıcı bulunamadı.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
</x-layouts.admin>
