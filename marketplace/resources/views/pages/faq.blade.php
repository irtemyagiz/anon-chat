<x-layouts.app :title="__('Sık sorulan sorular')">
    <div class="container-page max-w-3xl py-12">
        <x-section-heading :eyebrow="__('Yardım')" :title="__('Sık sorulan sorular')"/>
        @if ($faqs->isEmpty())
            <x-empty-state icon="mail" :title="__('Henüz soru eklenmedi')"/>
        @else
            <div class="card divide-y divide-line">
                @foreach ($faqs as $faq)
                    <details class="group p-5">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 font-semibold">{{ $faq->question }}<x-icon name="chevron-down" class="size-4 shrink-0 transition group-open:rotate-180"/></summary>
                        <p class="mt-3 text-sm whitespace-pre-line text-muted">{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </div>
        @endif
        <p class="mt-8 text-center text-sm text-muted">{{ __('Aradığın cevabı bulamadın mı?') }} <a href="{{ auth()->check() ? route('support.create') : route('login') }}" class="font-semibold text-katman-700 hover:underline dark:text-katman-300">{{ __('Destek talebi oluştur') }}</a></p>
    </div>
</x-layouts.app>
