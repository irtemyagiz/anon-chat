@props(['eyebrow' => null, 'title', 'href' => null, 'linkLabel' => null])
<div {{ $attributes->class(['mb-6 flex items-end justify-between gap-4']) }}>
    <div>
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h2 class="mt-1 text-2xl font-extrabold text-ink sm:text-3xl">{{ $title }}</h2>
    </div>
    @if ($href)
        <a href="{{ $href }}" class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-katman-700 hover:text-katman-800 sm:inline-flex dark:text-katman-300">
            {{ $linkLabel ?? __('Tümünü gör') }} <x-icon name="arrow-right" class="size-4"/>
        </a>
    @endif
</div>
