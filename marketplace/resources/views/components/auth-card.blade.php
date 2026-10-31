@props(['title', 'subtitle' => null, 'wide' => false])
<x-layouts.app :title="$title" :noindex="true">
    <div class="container-page flex justify-center py-12 sm:py-16">
        <div @class(['w-full', 'max-w-md' => ! $wide, 'max-w-3xl' => $wide])>
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-extrabold">{{ $title }}</h1>
                @if ($subtitle)<p class="mt-2 text-muted">{{ $subtitle }}</p>@endif
            </div>
            <div class="card p-6 sm:p-8">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="mt-6 text-center text-sm text-muted">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</x-layouts.app>
