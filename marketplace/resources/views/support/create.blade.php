<x-layouts.account :title="__('Yeni destek talebi')">
    <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" class="card space-y-5 p-6" x-data="{ category: '{{ old('category', $category ?? 'general') }}' }">
        @csrf
        <div>
            <label class="label" for="category">{{ __('Konu') }}</label>
            <select id="category" name="category" x-model="category" class="input">
                @foreach (\App\Models\SupportTicket::CATEGORIES as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
            </select>
        </div>
        <div x-show="category === 'refund'" x-cloak class="space-y-2">
            <label class="label" for="point_purchase_id">{{ __('İade istenen puan yüklemesi') }}</label>
            <select id="point_purchase_id" name="point_purchase_id" class="input" :disabled="category !== 'refund'">
                <option value="">{{ __('Seçin') }}</option>
                @foreach ($purchases as $purchase)
                    <option value="{{ $purchase->id }}" @selected((int) old('point_purchase_id') === $purchase->id)>{{ $purchase->paid_at?->format('d.m.Y') }} · {{ number_format($purchase->points, 0, ',', '.') }} POINT · {{ app(\App\Services\Currency\PriceFormatter::class)->format($purchase->charge_amount_minor, $purchase->charge_currency) }}</option>
                @endforeach
            </select>
            @error('point_purchase_id')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            <p class="text-xs text-muted">{{ __('Yalnızca harcanmamış puanlar iade edilebilir. İade, ödemeyi yaptığın yönteme yapılır.') }}</p>
        </div>
        <x-field name="subject" :label="__('Başlık')" required maxlength="160"/>
        <div>
            <label class="label" for="body">{{ __('Mesajın') }}</label>
            <textarea id="body" name="body" rows="6" class="input" required minlength="10" maxlength="5000">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="attachment">{{ __('Ek dosya (PDF, JPG, PNG · isteğe bağlı)') }}</label>
            <input id="attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-sm">
            @error('attachment')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary">{{ __('Gönder') }}</button>
    </form>
</x-layouts.account>
