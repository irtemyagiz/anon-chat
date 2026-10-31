@php
    $usd = fn (int $cents) => ($cents < 0 ? '-' : '').'$'.\App\Services\Points\PointConverter::formatCents(abs($cents));
    $withdrawable = $balance->withdrawableCents();
    $verified = $verification?->status->value === 'approved';
    $fieldLabels = ['account_holder' => __('Hesap sahibi (ad soyad / unvan)'), 'iban' => 'IBAN', 'swift' => 'SWIFT / BIC', 'bank_name' => __('Banka adı'), 'email' => __('Hesap e-postası')];
@endphp
<x-layouts.creator :title="__('Hakedişler')">
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([[__('Çekilebilir'), $withdrawable], [__('Bekleyen (iade süresi)'), (int) $balance->pending_cents], [__('Talepte (rezerve)'), (int) $balance->reserved_cents], [__('Ödenen'), (int) $balance->paid_cents]] as [$label, $cents])
            <div class="card p-5"><p class="text-xs text-muted">{{ $label }}</p><p class="mt-2 font-display text-2xl font-extrabold tabular-nums">{{ $usd($cents) }}</p></div>
        @endforeach
    </div>

    {{-- 1. Satıcı doğrulaması --}}
    <section class="card mt-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold">{{ __('1. Satıcı doğrulaması') }}</h2>
            @if ($verification)<x-status-badge :status="match ($verification->status->value) { 'approved' => 'completed', 'rejected' => 'rejected', default => 'pending' }"/>@endif
        </div>
        @if ($verified)
            <p class="mt-2 text-sm text-muted">{{ __('Kimlik ve vergi bilgilerin onaylandı (:name).', ['name' => $verification->legal_name]) }}</p>
        @elseif ($verification?->status->value === 'pending')
            <p class="mt-2 text-sm text-muted">{{ __('Bilgilerin inceleniyor. Onaylandığında hakediş talebi oluşturabilirsin.') }}</p>
        @else
            @if ($verification?->review_note)<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ __('Ret gerekçesi') }}: {{ $verification->review_note }}</p>@endif
            <p class="mt-2 text-sm text-muted">{{ __('Yasal düzenlemeler gereği ödeme yapılmadan önce kimlik ve vergi bilgilerin doğrulanır. Bilgiler şifreli saklanır ve yalnızca yetkili yöneticiler görebilir.') }}</p>
            <form method="POST" action="{{ route('creator.payouts.verify') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2" x-data="{ type: '{{ old('entity_type', 'individual') }}', country: '{{ old('country_code', auth()->user()->country_code) }}' }">
                @csrf
                <x-field name="legal_name" :label="__('Yasal ad soyad / şirket unvanı')" required/>
                <div>
                    <label class="label" for="entity_type">{{ __('Satıcı türü') }}</label>
                    <select id="entity_type" name="entity_type" x-model="type" class="input">
                        <option value="individual">{{ __('Bireysel') }}</option><option value="sole_proprietor">{{ __('Şahıs şirketi') }}</option><option value="company">{{ __('Şirket (Ltd./A.Ş.)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="country_code">{{ __('Vergi mukimi olunan ülke') }}</label>
                    <select id="country_code" name="country_code" x-model="country" class="input">
                        @foreach (config('countries') as $code => $names)<option value="{{ $code }}">{{ $names[app()->getLocale() === 'en' ? 1 : 0] }}</option>@endforeach
                    </select>
                </div>
                <div x-show="country === 'TR' && type === 'individual'"><x-field name="identity_number" :label="__('T.C. kimlik numarası')" inputmode="numeric" maxlength="11" x-bind:disabled="!(country === 'TR' && type === 'individual')"/></div>
                <div x-show="country === 'TR' && type !== 'individual'"><x-field name="tax_number" :label="__('Vergi numarası')" inputmode="numeric" maxlength="11" x-bind:disabled="!(country === 'TR' && type !== 'individual')"/></div>
                <div x-show="country === 'TR' && type !== 'individual'"><x-field name="tax_office" :label="__('Vergi dairesi')" x-bind:disabled="!(country === 'TR' && type !== 'individual')"/></div>
                <div x-show="country !== 'TR'"><x-field name="tax_number" :label="__('Vergi kimlik numarası (varsa)')" x-bind:disabled="country === 'TR'"/></div>
                <div class="sm:col-span-2">
                    <label class="label" for="address">{{ __('Yasal adres') }}</label>
                    <textarea id="address" name="address" rows="2" class="input" required>{{ old('address') }}</textarea>
                    @error('address')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="document">{{ __('Kimlik / vergi levhası belgesi (PDF, JPG, PNG · isteğe bağlı)') }}</label>
                    <input id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
                    @error('document')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm text-muted sm:col-span-2"><input type="checkbox" name="consent" value="1" class="mt-0.5 accent-katman-600" required>{{ __('Bilgilerin doğru olduğunu ve hakediş ödemeleri için işlenmesine onay verdiğimi beyan ederim (KVKK).') }}</label>
                @error('consent')<p class="text-xs text-red-600 dark:text-red-400 sm:col-span-2">{{ $message }}</p>@enderror
                <div class="sm:col-span-2"><button class="btn-primary">{{ __('Doğrulamaya gönder') }}</button></div>
            </form>
        @endif
    </section>

    {{-- 2. Hakediş talebi --}}
    <section class="card mt-6 p-6">
        <h2 class="font-bold">{{ __('2. Hakediş talebi') }}</h2>
        @if (! $verified)
            <p class="mt-2 text-sm text-muted">{{ __('Talep oluşturmak için önce satıcı doğrulamasının onaylanması gerekir.') }}</p>
        @elseif ($withdrawable < $minimumCents)
            <p class="mt-2 text-sm text-muted">{{ __('En az :min çekilebilir bakiye olduğunda talep oluşturabilirsin.', ['min' => $usd($minimumCents)]) }}</p>
        @else
            <form method="POST" action="{{ route('creator.payouts.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2" x-data="{ method: '{{ old('method', array_key_first($methods)) }}', fields: @js(collect($methods)->map(fn ($m) => $m['fields'])) }">
                @csrf
                <div>
                    <label class="label" for="method">{{ __('Ödeme yöntemi') }}</label>
                    <select id="method" name="method" x-model="method" class="input">
                        @foreach ($methods as $key => $method)<option value="{{ $key }}">{{ __($method['label']) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="amount">{{ __('Tutar (USD)') }}</label>
                    <input id="amount" name="amount" value="{{ old('amount', \App\Services\Points\PointConverter::formatCents($withdrawable)) }}" inputmode="decimal" class="input" required>
                    <p class="mt-1.5 text-xs text-subtle">{{ __('En fazla :max · TL ödemelerde ödeme günündeki kur uygulanır.', ['max' => $usd($withdrawable)]) }}</p>
                    @error('amount')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                @foreach ($fieldLabels as $field => $label)
                    <div x-show="fields[method].includes('{{ $field }}')">
                        <label class="label" for="details_{{ $field }}">{{ $label }}</label>
                        <input id="details_{{ $field }}" name="details[{{ $field }}]" value="{{ old("details.$field") }}" class="input" :disabled="! fields[method].includes('{{ $field }}')" @if ($field === 'iban') placeholder="TR00 0000 0000 0000 0000 0000 00" @endif>
                        @error("details.$field")<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2"><button class="btn-primary">{{ __('Talep oluştur') }}</button></div>
            </form>
        @endif
    </section>

    <h2 class="mt-10 mb-4 text-lg font-bold">{{ __('Taleplerim') }}</h2>
    @if ($requests->isEmpty())
        <x-empty-state icon="wallet" :title="__('Henüz hakediş talebin yok')"/>
    @else
        <div class="card divide-y divide-line">
            @foreach ($requests as $payoutRequest)
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
                    <div>
                        <span class="font-semibold tabular-nums">{{ $usd($payoutRequest->amount_cents) }}</span>
                        <span class="text-muted">· {{ __(config('marketplace.payouts.methods.'.(auth()->user()->country_code === 'TR' ? 'TR' : 'default').'.'.$payoutRequest->method.'.label', $payoutRequest->method)) }} · {{ $payoutRequest->maskedAccount() }}</span>
                        <span class="block text-xs text-subtle">{{ $payoutRequest->created_at->format('d.m.Y H:i') }}@if ($payoutRequest->payout) · {{ __('Ödendi: :amount (ref. :ref)', ['amount' => app(\App\Services\Currency\PriceFormatter::class)->format($payoutRequest->payout->paid_amount_minor, $payoutRequest->payout->paid_currency), 'ref' => $payoutRequest->payout->reference]) }}@endif</span>
                        @if ($payoutRequest->review_note && $payoutRequest->status->value === 'rejected')<span class="block text-xs text-red-600 dark:text-red-400">{{ $payoutRequest->review_note }}</span>@endif
                    </div>
                    <x-status-badge :status="match ($payoutRequest->status->value) { 'paid' => 'paid', 'rejected' => 'rejected', 'approved' => 'completed', default => 'pending' }"/>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</x-layouts.creator>
