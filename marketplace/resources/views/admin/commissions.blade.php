@php $percent = fn (int $bps) => rtrim(rtrim(number_format($bps / 100, 2, ',', ''), '0'), ','); @endphp
<x-layouts.admin :title="__('Komisyon')">
    <section class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-end sm:justify-between">
        <div><h2 class="font-bold">{{ __('Varsayılan platform komisyonu') }}</h2><p class="text-sm text-muted">{{ __('Şu an %:rate · creator payı %:share', ['rate' => $percent($defaultRate), 'share' => $percent(10000 - $defaultRate)]) }}</p></div>
        <form method="POST" action="{{ route('admin.commissions.default') }}" class="flex gap-2" x-data @submit="if (! confirm(@js(__('Varsayılan komisyon değiştirilsin mi? Geçmiş satışlar etkilenmez.')))) $event.preventDefault()">
            @csrf @method('PUT')
            <div class="relative"><span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-subtle">%</span><input name="percent" type="number" step="0.01" min="0" max="90" value="{{ $defaultRate / 100 }}" class="input w-32 pl-7"></div>
            <button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
        </form>
    </section>
    <p class="mt-2 text-xs text-subtle">{{ __('Öncelik: kampanya > creator > kategori > varsayılan. Oran satış anında siparişe kopyalanır.') }}</p>

    <section class="card mt-6 p-5" x-data="{ scope: '{{ old('scope', 'creator') }}' }">
        <h2 class="font-bold">{{ __('Yeni kural') }}</h2>
        <form method="POST" action="{{ route('admin.commissions.rules.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <select name="scope" x-model="scope" class="input"><option value="creator">{{ __('Creator bazlı') }}</option><option value="category">{{ __('Kategori bazlı') }}</option><option value="campaign">{{ __('Kampanya') }}</option></select>
            <input name="name" value="{{ old('name') }}" class="input" placeholder="{{ __('Kural adı') }}" required>
            <input name="percent" type="number" step="0.01" min="0" max="90" value="{{ old('percent') }}" class="input" placeholder="{{ __('Komisyon %') }}" required>
            <input x-show="scope === 'creator'" name="creator" value="{{ old('creator') }}" class="input" placeholder="{{ __('Creator e-posta veya kullanıcı adı') }}">
            <select x-show="scope === 'category'" name="category_id" class="input"><option value="">{{ __('Kategori seçin') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->localizedName() }}</option>@endforeach</select>
            <label class="text-xs text-muted">{{ __('Başlangıç (isteğe bağlı)') }}<input name="starts_at" type="datetime-local" class="input mt-1"></label>
            <label class="text-xs text-muted">{{ __('Bitiş') }} <span x-show="scope === 'campaign'">({{ __('zorunlu') }})</span><input name="ends_at" type="datetime-local" class="input mt-1"></label>
            <div class="flex items-end"><button class="btn-primary w-full">{{ __('Kural ekle') }}</button></div>
        </form>
    </section>

    <section class="card mt-6 overflow-x-auto">
        <table class="w-full min-w-[40rem] text-sm">
            <thead class="border-b border-line text-left text-xs text-muted uppercase"><tr><th class="px-4 py-3">{{ __('Kural') }}</th><th class="px-4 py-3">{{ __('Kapsam') }}</th><th class="px-4 py-3 text-right">{{ __('Oran') }}</th><th class="px-4 py-3">{{ __('Geçerlilik') }}</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rules as $rule)
                    <tr @class(['opacity-50' => ! $rule->is_active])>
                        <td class="px-4 py-3 font-medium">{{ $rule->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ match ($rule->scope->value) { 'creator' => 'Creator: '.($creators[$rule->creator_id]?->creatorProfile?->store_name ?? '—'), 'category' => __('Kategori').': '.($categoryNames[$rule->category_id] ?? '—'), default => __('Kampanya') } }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">%{{ $percent($rule->rate_bps) }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $rule->starts_at?->format('d.m.Y H:i') ?? '—' }} → {{ $rule->ends_at?->format('d.m.Y H:i') ?? '∞' }}</td>
                        <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.commissions.rules.toggle', $rule) }}">@csrf<button class="btn-ghost px-2 py-1 text-xs">{{ $rule->is_active ? __('Pasifleştir') : __('Etkinleştir') }}</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-muted">{{ __('Özel kural yok; tüm satışlarda varsayılan oran uygulanır.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-6">{{ $rules->links() }}</div>
</x-layouts.admin>
