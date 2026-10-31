@if ($price['points'] === 0)
    <span {{ $attributes->class(['font-semibold text-katman-700 dark:text-katman-300', 'text-2xl' => $size === 'lg']) }}>{{ __('Ücretsiz') }}</span>
@else
    <span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-1.5']) }}>
        <span @class(['font-semibold text-ink tabular-nums', 'text-2xl' => $size === 'lg', 'text-sm' => $size === 'sm'])>{{ number_format($price['points'], 0, ',', '.') }} <span class="text-[0.7em] font-bold tracking-wide text-katman-700 dark:text-katman-300">POINT</span></span>
        <span @class(['text-muted tabular-nums', 'text-sm' => $size !== 'sm', 'text-xs' => $size === 'sm'])>≈ {{ $price['formatted'] }}</span>
    </span>
@endif
