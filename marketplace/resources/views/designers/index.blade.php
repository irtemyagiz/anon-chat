<x-layouts.app :title="__('Tasarımcılar')">
    <div class="container-page py-12">
        <x-section-heading :eyebrow="__('Topluluk')" :title="__('Tasarımcılar')"/>
        @if ($designers->isEmpty())
            <x-empty-state icon="store" :title="__('Henüz onaylı tasarımcı yok')" :description="__('İlk creator mağazaları onaylandığında burada listelenecek.')">
                <a href="{{ route('creator.apply') }}" class="btn-primary">{{ __('Creator başvurusu yap') }}</a>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($designers as $designer)
                    @include('designers.partials.card', ['profile' => $designer])
                @endforeach
            </div>
            <div class="mt-8">{{ $designers->links() }}</div>
        @endif
    </div>
</x-layouts.app>
