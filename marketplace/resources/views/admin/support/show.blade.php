<x-layouts.admin :title="$ticket->subject">
    <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        <div>
            @include('support.partials.thread', ['ticket' => $ticket, 'showStaffName' => true])
            <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" enctype="multipart/form-data" class="card mt-6 space-y-3 p-5">
                @csrf
                <textarea name="body" rows="5" class="input" required placeholder="{{ __('Yanıt (kullanıcı e-posta ile bilgilendirilir)') }}"></textarea>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="close" value="1" class="accent-katman-600">{{ __('Yanıtladıktan sonra talebi kapat') }}</label>
                <button class="btn-primary">{{ __('Yanıtla') }}</button>
            </form>
        </div>
        <aside class="space-y-4">
            <div class="card p-5 text-sm">
                <p class="text-muted">{{ __('Kullanıcı') }}</p>
                <p class="font-semibold">{{ $ticket->user->full_name }}</p>
                <p class="text-muted">{{ $ticket->user->email }}</p>
                @can('users.manage')<a href="{{ route('admin.users.show', $ticket->user) }}" class="mt-2 inline-block text-xs font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Kullanıcı detayı') }} →</a>@endcan
                <p class="mt-4 text-muted">{{ __('Konu') }}: {{ __(\App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category) }}</p>
                <p class="text-muted">{{ __('Durum') }}: {{ __(\App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status) }}</p>
            </div>
            @if ($ticket->pointPurchase)
                <div class="card p-5 text-sm">
                    <p class="font-semibold">{{ __('İade istenen yükleme') }}</p>
                    <p class="mt-1 text-muted">{{ number_format($ticket->pointPurchase->points, 0, ',', '.') }} POINT · {{ app(\App\Services\Currency\PriceFormatter::class)->format($ticket->pointPurchase->charge_amount_minor, $ticket->pointPurchase->charge_currency) }} · <x-status-badge :status="$ticket->pointPurchase->status"/></p>
                    @can('payments.manage')<a href="{{ route('admin.payments.index', ['q' => $ticket->pointPurchase->uuid]) }}" class="mt-2 inline-block text-xs font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Ödemeler sayfasında iade et') }} →</a>@endcan
                </div>
            @endif
            @if ($ticket->status !== 'closed')
                <form method="POST" action="{{ route('admin.support.close', $ticket) }}">@csrf<button class="btn-secondary w-full">{{ __('Talebi kapat') }}</button></form>
            @endif
        </aside>
    </div>
</x-layouts.admin>
