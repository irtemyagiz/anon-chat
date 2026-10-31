@props(['icon' => 'cube', 'title', 'description' => null])
<div {{ $attributes->class(['card relative overflow-hidden px-6 py-14 text-center']) }}>
    <div class="layer-lines pointer-events-none absolute inset-x-0 bottom-0 h-24 opacity-70 [mask-image:linear-gradient(to_top,black,transparent)]"></div>
    <div class="relative mx-auto flex size-14 items-center justify-center rounded-2xl bg-katman-50 text-katman-700 ring-1 ring-katman-100 dark:bg-katman-950 dark:text-katman-300 dark:ring-katman-900">
        <x-icon :name="$icon" class="size-7"/>
    </div>
    <h3 class="relative mt-5 text-lg font-bold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="relative mx-auto mt-2 max-w-md text-sm text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="relative mt-6 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
