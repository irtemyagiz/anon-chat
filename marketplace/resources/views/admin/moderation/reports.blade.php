<x-layouts.admin :title="__('İçerik şikâyetleri')">
    @include('admin.partials.status-tabs', ['route' => 'admin.moderation.reports', 'current' => $status, 'tabs' => ['open' => __('Açık'), 'resolved' => __('Çözüldü'), 'dismissed' => __('Reddedildi'), 'tumu' => __('Tümü')]])
    @if ($reports->isEmpty())
        <x-empty-state icon="shield" :title="__('Bu durumda şikâyet yok')"/>
    @else
        <div class="space-y-3">
            @foreach ($reports as $report)
                <div class="card p-5 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p><span class="chip">{{ __(\App\Models\ProductReport::REASONS[$report->reason] ?? $report->reason) }}</span> <a href="{{ route('admin.products.show', $report->product) }}" class="font-semibold hover:underline">{{ $report->product->title }}</a> <span class="text-muted">· {{ $report->product->creator->creatorProfile?->store_name }}</span></p>
                            <p class="mt-2 whitespace-pre-line">{{ $report->details }}</p>
                            <p class="mt-2 text-xs text-subtle">{{ $report->reporter?->username ? '@'.$report->reporter->username : __('Ziyaretçi') }} · {{ $report->reporter_email }} · {{ $report->created_at->format('d.m.Y H:i') }}</p>
                            @if ($report->resolution_note)<p class="mt-1 text-xs text-muted">{{ __('Sonuç') }}: {{ $report->resolution_note }}</p>@endif
                        </div>
                        <x-status-badge :status="match ($report->status) { 'resolved' => 'completed', 'dismissed' => 'unpublished', default => 'pending' }"/>
                    </div>
                    @if ($report->status === 'open')
                        <form method="POST" action="{{ route('admin.moderation.reports.resolve', $report) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">@csrf @method('PUT')
                            <input name="resolution_note" class="input py-2" placeholder="{{ __('Sonuç notu (ör. ürün yayından kaldırıldı)') }}" required>
                            <select name="status" class="input py-2 sm:w-40"><option value="resolved">{{ __('Çözüldü') }}</option><option value="dismissed">{{ __('Reddet') }}</option></select>
                            <button class="btn-primary shrink-0 py-2">{{ __('Kapat') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $reports->links() }}</div>
    @endif
</x-layouts.admin>
