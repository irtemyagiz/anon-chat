@php
    $states = [
        'pending' => ['clock', __('Başvurun inceleniyor'), __('Mağaza başvurun ekibimize ulaştı. İnceleme tamamlandığında e-posta ile bilgilendirileceksin.')],
        'approved' => ['check', __('Mağazan onaylandı'), __('Mağazan yayında. Creator panelinden ürün ekleyebilir, satışlarını ve gelirlerini takip edebilirsin.')],
        'rejected' => ['x', __('Başvurun onaylanmadı'), $profile->rejection_reason ?: __('Ayrıntılar için destek ekibiyle iletişime geçebilirsin.')],
        'suspended' => ['shield', __('Mağazan askıya alındı'), __('Ayrıntılar için destek ekibiyle iletişime geçebilirsin.')],
    ];
    [$icon, $title, $description] = $states[$profile->status->value];
@endphp
<x-layouts.app :title="__('Mağazam')" :noindex="true">
    <div class="container-page max-w-3xl py-12">
        <x-empty-state :icon="$icon" :title="$title" :description="$description">
            @if ($profile->status->value === 'approved')
                <a href="{{ route('creator.dashboard') }}" class="btn-primary"><x-icon name="layers" class="size-4"/>{{ __('Creator paneline git') }}</a>
                <a href="{{ route('designers.show', $profile) }}" class="btn-secondary"><x-icon name="store" class="size-4"/>{{ __('Mağazamı görüntüle') }}</a>
            @endif
            <a href="{{ route('home') }}" class="btn-secondary">{{ __('Ana sayfaya dön') }}</a>
        </x-empty-state>
        <dl class="card mt-6 divide-y divide-line px-6 text-sm">
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Mağaza adı') }}</dt><dd class="font-medium">{{ $profile->store_name }}</dd></div>
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Tasarımcı adı') }}</dt><dd class="font-medium">{{ $profile->designer_name }}</dd></div>
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Mağaza adresi') }}</dt><dd class="font-medium">/magaza/{{ $profile->store_slug }}</dd></div>
            <div class="flex justify-between py-3"><dt class="text-muted">{{ __('Başvuru tarihi') }}</dt><dd class="font-medium">{{ $profile->created_at->translatedFormat('d F Y') }}</dd></div>
        </dl>
    </div>
</x-layouts.app>
