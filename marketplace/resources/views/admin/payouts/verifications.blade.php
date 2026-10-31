<x-layouts.admin :title="__('Satıcı doğrulamaları')">
    @if ($verifications->isEmpty())
        <x-empty-state icon="shield" :title="__('Doğrulama başvurusu yok')"/>
    @else
        <div class="space-y-3">
            @foreach ($verifications as $verification)
                <div class="card p-5" x-data="{ reject: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm">
                            <p><span class="font-semibold">{{ $verification->legal_name }}</span> <x-status-badge :status="match ($verification->status->value) { 'approved' => 'completed', 'rejected' => 'rejected', default => 'pending' }"/></p>
                            <p class="mt-1 text-muted">{{ $verification->creatorProfile->store_name }} · {{ $verification->creatorProfile->user->email }} · {{ ['individual' => __('Bireysel'), 'sole_proprietor' => __('Şahıs şirketi'), 'company' => __('Şirket')][$verification->entity_type] ?? $verification->entity_type }} · {{ $verification->country_code }}</p>
                            <p class="mt-1 font-mono text-xs">{{ $verification->identity_number ? 'TCKN '.$verification->identity_number : '' }} {{ $verification->tax_number ? 'VKN '.$verification->tax_number : '' }} {{ $verification->tax_office }}</p>
                            <p class="mt-1 text-xs text-muted">{{ $verification->address }}</p>
                            @if ($verification->review_note)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $verification->review_note }}</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($verification->document_path)<a href="{{ route('admin.payouts.verifications.document', $verification) }}" class="btn-ghost px-3 py-1.5">{{ __('Belgeyi indir') }}</a>@endif
                            @if ($verification->status->value === 'pending')
                                <form method="POST" action="{{ route('admin.payouts.verifications.review', $verification) }}">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="btn-primary px-3 py-1.5">{{ __('Onayla') }}</button></form>
                                <button type="button" @click="reject = ! reject" class="btn-secondary px-3 py-1.5">{{ __('Reddet') }}</button>
                            @endif
                        </div>
                    </div>
                    <form x-show="reject" x-cloak method="POST" action="{{ route('admin.payouts.verifications.review', $verification) }}" class="mt-3 flex gap-2">@csrf @method('PUT')
                        <input type="hidden" name="status" value="rejected"><input name="note" class="input" placeholder="{{ __('Gerekçe') }}" required><button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $verifications->links() }}</div>
    @endif
</x-layouts.admin>
