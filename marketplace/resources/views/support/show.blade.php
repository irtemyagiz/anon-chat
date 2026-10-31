<x-layouts.account :title="$ticket->subject">
    <p class="-mt-4 mb-6 text-sm text-muted">{{ __(\App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category) }} · <x-status-badge :status="match ($ticket->status) { 'answered' => 'completed', 'closed' => 'unpublished', default => 'pending' }"/>
        @if ($ticket->pointPurchase) · {{ __('İlgili yükleme: :points POINT', ['points' => number_format($ticket->pointPurchase->points, 0, ',', '.')]) }}@endif</p>
    @include('support.partials.thread', ['ticket' => $ticket])
    @if ($ticket->status !== 'closed')
        <form method="POST" action="{{ route('support.reply', $ticket) }}" enctype="multipart/form-data" class="card mt-6 space-y-3 p-5">
            @csrf
            <textarea name="body" rows="4" class="input" required placeholder="{{ __('Yanıtın') }}"></textarea>
            @error('body')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
            <div class="flex gap-2"><button class="btn-primary">{{ __('Gönder') }}</button><button type="submit" form="close-ticket" class="btn-ghost">{{ __('Talebi kapat') }}</button></div>
        </form>
        <form id="close-ticket" method="POST" action="{{ route('support.close', $ticket) }}" class="hidden">@csrf</form>
    @else
        <p class="mt-6 text-sm text-muted">{{ __('Bu talep kapatıldı.') }} <a href="{{ route('support.create') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Yeni talep oluştur') }}</a></p>
    @endif
</x-layouts.account>
