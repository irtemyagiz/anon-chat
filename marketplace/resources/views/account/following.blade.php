<x-layouts.account :title="__('Takip ettiklerim')">
    @if ($creators->isEmpty())
        <x-empty-state icon="store" :title="__('Henüz kimseyi takip etmiyorsun')" :description="__('Sevdiğin tasarımcıların mağaza sayfasından “Takip et” diyerek yeni modellerinden haberdar olabilirsin.')">
            <a href="{{ route('designers.index') }}" class="btn-primary">{{ __('Tasarımcıları keşfet') }}</a>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($creators as $creator)
                @if ($creator->creatorProfile)@include('designers.partials.card', ['profile' => $creator->creatorProfile])@endif
            @endforeach
        </div>
        <div class="mt-6">{{ $creators->links() }}</div>
    @endif
</x-layouts.account>
