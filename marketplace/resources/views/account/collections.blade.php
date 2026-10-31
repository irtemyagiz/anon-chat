<x-layouts.account :title="__('Koleksiyonlarım')">
    <form method="POST" action="{{ route('collections.store') }}" class="card mb-6 grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto_auto]">
        @csrf
        <input name="name" class="input" placeholder="{{ __('Yeni koleksiyon adı') }}" required maxlength="100">
        <input name="description" class="input" placeholder="{{ __('Açıklama (isteğe bağlı)') }}" maxlength="500">
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" checked class="accent-katman-600">{{ __('Herkese açık') }}</label>
        <button class="btn-primary">{{ __('Oluştur') }}</button>
    </form>
    @if ($collections->isEmpty())
        <x-empty-state icon="layers" :title="__('Henüz koleksiyonun yok')" :description="__('Modelleri temaya göre gruplamak için koleksiyon oluştur, istersen herkesle paylaş.')"/>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($collections as $collection)
                <div class="card p-5" x-data="{ edit: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('collections.show', [auth()->user()->username, $collection->slug]) }}" class="font-semibold hover:underline">{{ $collection->name }}</a>
                            <p class="text-xs text-muted">{{ trans_choice(':count model|:count model', $collection->products_count, ['count' => $collection->products_count]) }} · {{ $collection->is_public ? __('Herkese açık') : __('Gizli') }}</p>
                        </div>
                        <button type="button" @click="edit = ! edit" class="btn-ghost px-2 py-1 text-xs">{{ __('Düzenle') }}</button>
                    </div>
                    @if ($collection->description)<p class="mt-2 text-sm text-muted">{{ $collection->description }}</p>@endif
                    <form x-show="edit" x-cloak method="POST" action="{{ route('collections.update', $collection) }}" class="mt-3 space-y-2">@csrf @method('PUT')
                        <input name="name" value="{{ $collection->name }}" class="input py-2" required>
                        <input name="description" value="{{ $collection->description }}" class="input py-2">
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" @checked($collection->is_public) class="accent-katman-600">{{ __('Herkese açık') }}</label>
                        <div class="flex gap-2"><button class="btn-primary px-3 py-1.5">{{ __('Kaydet') }}</button><button type="submit" form="delete-collection-{{ $collection->id }}" class="btn-ghost px-3 py-1.5 text-red-600">{{ __('Sil') }}</button></div>
                    </form>
                    <form id="delete-collection-{{ $collection->id }}" method="POST" action="{{ route('collections.destroy', $collection) }}" class="hidden" x-data @submit="if (! confirm(@js(__('Koleksiyon silinsin mi? Modeller silinmez.')))) $event.preventDefault()">@csrf @method('DELETE')</form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.account>
