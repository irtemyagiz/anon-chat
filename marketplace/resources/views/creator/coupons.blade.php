<x-layouts.creator :title="__('Kuponlar')">
    <section class="card p-6">
        <h2 class="text-lg font-bold">{{ __('Yeni kupon') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('İndirim senin payından düşer: komisyon, indirimli satış tutarı üzerinden hesaplanır. Kupon kodları büyük harfe çevrilir.') }}</p>
        <form method="POST" action="{{ route('creator.coupons.store') }}" x-data="{ type: @js(old('discount_type', 'percent')) }" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <x-field name="code" :label="__('Kupon kodu')" maxlength="40" required placeholder="YAZ2026" class="uppercase" :hint="__('Harf, rakam, tire ve alt çizgi')"/>
            <div>
                <label for="discount_type" class="label">{{ __('İndirim türü') }}</label>
                <select id="discount_type" name="discount_type" x-model="type" class="input">
                    <option value="percent">{{ __('Yüzde') }}</option>
                    <option value="fixed">{{ __('Sabit tutar (USD)') }}</option>
                </select>
            </div>
            <div x-show="type === 'percent'">
                <x-field name="percent" type="number" :label="__('İndirim (%)')" min="1" max="100" step="1" placeholder="15"/>
            </div>
            <div x-show="type === 'fixed'" x-cloak>
                <x-field name="amount" :label="__('İndirim tutarı (USD)')" inputmode="decimal" placeholder="2.00"/>
            </div>
            <div>
                <label for="product_id" class="label">{{ __('Geçerli olduğu ürün') }}</label>
                <select id="product_id" name="product_id" class="input">
                    <option value="">{{ __('Tüm ücretli ürünlerim') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->title }}</option>
                    @endforeach
                </select>
                @error('product_id')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <x-field name="max_redemptions" type="number" :label="__('Kullanım limiti')" min="1" :hint="__('Boş bırakılırsa sınırsız')"/>
            <x-field name="starts_at" type="datetime-local" :label="__('Başlangıç')" :hint="__('Boş bırakılırsa hemen başlar')"/>
            <x-field name="ends_at" type="datetime-local" :label="__('Bitiş')" :hint="__('Boş bırakılırsa süresiz')"/>
            <div class="flex items-end sm:col-span-2 lg:col-span-1">
                <button class="btn-primary w-full">{{ __('Kupon oluştur') }}</button>
            </div>
        </form>
    </section>

    @error('coupon')<p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>@enderror

    <section class="mt-6">
        @if ($coupons->isEmpty())
            <x-empty-state icon="sparkles" :title="__('Henüz kuponun yok')" :description="__('Takipçilerine veya kampanyalarına özel indirim kodları oluşturabilirsin.')"/>
        @else
            <div class="card overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b border-line text-left text-xs text-muted uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Kod') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('İndirim') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Ürün') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('Kullanım') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Geçerlilik') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Durum') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($coupons as $coupon)
                            <tr>
                                <td class="px-5 py-3 font-mono font-semibold">{{ $coupon->code }}</td>
                                <td class="px-5 py-3">{{ $coupon->discountLabel() }}</td>
                                <td class="px-5 py-3 text-muted">{{ $coupon->product?->title ?? __('Tüm ücretli ürünler') }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $coupon->redemptions_count }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td>
                                <td class="px-5 py-3 text-xs whitespace-nowrap text-muted">
                                    {{ $coupon->starts_at?->format('d.m.Y H:i') ?? __('Hemen') }} – {{ $coupon->ends_at?->format('d.m.Y H:i') ?? __('Süresiz') }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($coupon->isUsable())
                                        <span class="inline-flex rounded-full bg-katman-100 px-2.5 py-0.5 text-xs font-semibold text-katman-800 dark:bg-katman-900 dark:text-katman-200">{{ __('Geçerli') }}</span>
                                    @elseif (! $coupon->is_active)
                                        <span class="inline-flex rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-muted">{{ __('Durduruldu') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-filament-400/20 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:text-filament-300">{{ __('Kullanılamıyor') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('creator.coupons.toggle', $coupon) }}">
                                            @csrf
                                            <button class="btn-secondary px-3 py-1.5 text-xs">{{ $coupon->is_active ? __('Durdur') : __('Etkinleştir') }}</button>
                                        </form>
                                        @if ($coupon->redemptions_count === 0)
                                            <form method="POST" action="{{ route('creator.coupons.destroy', $coupon) }}" onsubmit="return confirm(@js(__('Kupon silinsin mi?')))">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn-ghost px-3 py-1.5 text-xs text-red-600">{{ __('Sil') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $coupons->links() }}</div>
        @endif
    </section>
</x-layouts.creator>
