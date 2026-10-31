<x-layouts.creator :title="__('Takipçiler')">
    @if ($followers->isEmpty())
        <x-empty-state icon="user" :title="__('Henüz takipçin yok')" :description="__('Mağaza sayfanı paylaşarak takipçi kazanabilirsin.')"/>
    @else
        <p class="mb-4 text-sm text-muted">{{ trans_choice(':count takipçi|:count takipçi', $followers->total(), ['count' => $followers->total()]) }}</p>
        <div class="card divide-y divide-line">
            @foreach ($followers as $follower)
                <div class="flex items-center justify-between p-4 text-sm"><span><span class="font-semibold">{{ $follower->first_name }}</span> <span class="text-muted">{{ '@'.$follower->username }}</span></span><span class="text-xs text-subtle">{{ \Illuminate\Support\Carbon::parse($follower->created_at)->format('d.m.Y') }}</span></div>
            @endforeach
        </div>
        <div class="mt-6">{{ $followers->links() }}</div>
    @endif
</x-layouts.creator>
