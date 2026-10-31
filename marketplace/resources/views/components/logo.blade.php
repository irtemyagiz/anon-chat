@props(['compact' => false])
{{-- Katman logosu: kaydırılmış üç katman, 3D baskının katman katman oluşumunu simgeler. --}}
<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 32 32" class="size-8 shrink-0" aria-hidden="true">
        <rect x="4" y="20" width="22" height="6" rx="3" class="fill-katman-700 dark:fill-katman-500"/>
        <rect x="7" y="13" width="20" height="6" rx="3" class="fill-katman-500 dark:fill-katman-400"/>
        <rect x="10" y="6" width="18" height="6" rx="3" class="fill-filament-400"/>
    </svg>
    @unless ($compact)
        <span class="font-display text-xl font-extrabold tracking-tight text-ink">katman</span>
    @endunless
</span>
