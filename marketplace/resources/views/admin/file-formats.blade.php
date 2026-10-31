<x-layouts.admin :title="__('Dosya formatları')">
    <p class="mb-4 text-sm text-muted">{{ __('Pasif formatlar yeni yüklemelerde kabul edilmez. Daha önce yüklenmiş dosyalar etkilenmez.') }}</p>
    <div class="card divide-y divide-line">
        @foreach ($formats as $format)
            <div class="flex items-center justify-between gap-4 p-4">
                <div><span class="font-semibold">{{ $format->label }}</span> <span class="text-xs text-muted">.{{ $format->extension }}@if ($format->is_archive) · {{ __('arşiv (içerik denetlenir)') }}@endif @if ($format->is_previewable) · {{ __('3D önizlenebilir') }}@endif</span>
                    <p class="mt-1 font-mono text-[0.7rem] text-subtle">{{ implode(', ', $format->mime_types) }}</p></div>
                <form method="POST" action="{{ route('admin.file-formats.toggle', $format) }}" class="flex items-center gap-3">@csrf
                    <x-status-badge :status="$format->is_active ? 'completed' : 'unpublished'"/><button class="btn-secondary px-3 py-1.5 text-xs">{{ $format->is_active ? __('Pasifleştir') : __('Etkinleştir') }}</button>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
