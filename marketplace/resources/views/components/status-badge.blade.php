@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    [$label, $classes] = match ($value) {
        'published', 'clean', 'available', 'paid', 'completed' => [match ($value) { 'published' => __('Yayında'), 'clean' => __('Temiz'), 'available' => __('Kullanılabilir'), 'paid' => __('Ödendi'), default => __('Tamamlandı') }, 'bg-katman-100 text-katman-800 dark:bg-katman-900 dark:text-katman-200'],
        'pending_review', 'pending' => [$value === 'pending_review' ? __('İncelemede') : __('Bekliyor'), 'bg-filament-400/20 text-amber-800 dark:text-filament-300'],
        'rejected', 'infected', 'reversed', 'refunded', 'failed' => [match ($value) { 'rejected' => __('Reddedildi'), 'infected' => __('Zararlı'), 'reversed' => __('Geri alındı'), 'refunded' => __('İade edildi'), default => __('Başarısız') }, 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'],
        'unpublished' => [__('Yayında değil'), 'bg-surface-2 text-muted'],
        default => [__('Taslak'), 'bg-surface-2 text-muted'],
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', $classes]) }}>{{ $label }}</span>
