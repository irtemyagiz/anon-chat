<x-layouts.admin :title="__('Creator başvuruları')">
    @include('admin.partials.status-tabs', ['route' => 'admin.creators.index', 'current' => $status, 'tabs' => ['pending' => __('Bekleyen'), 'approved' => __('Onaylı'), 'rejected' => __('Reddedilen'), 'suspended' => __('Askıda'), 'tumu' => __('Tümü')]])
    @if ($profiles->isEmpty())
        <x-empty-state icon="store" :title="__('Bu durumda başvuru yok')"/>
    @else
        <div class="space-y-3">
            @foreach ($profiles as $profile)
                <div class="card p-5" x-data="{ action: null }">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold">{{ $profile->store_name }}</span>
                                <x-status-badge :status="match ($profile->status->value) { 'approved' => 'published', 'suspended' => 'unpublished', default => $profile->status->value }"/>
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $profile->designer_name }} · <a href="{{ route('admin.users.show', $profile->user) }}" class="hover:underline">{{ $profile->user->email }}</a> · {{ config('countries.'.$profile->user->country_code.'.0', $profile->user->country_code) }}</p>
                            @if ($profile->description)<p class="mt-2 text-sm">{{ $profile->description }}</p>@endif
                            <p class="mt-2 text-xs text-subtle">{{ __('Başvuru: :date', ['date' => $profile->created_at->format('d.m.Y H:i')]) }} · {{ trans_choice(':count ürün|:count ürün', $profile->products_count, ['count' => $profile->products_count]) }}</p>
                            @if ($profile->rejection_reason)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $profile->rejection_reason }}</p>@endif
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if ($profile->status->value !== 'approved')
                                <form method="POST" action="{{ route('admin.creators.update', $profile) }}">@csrf @method('PUT')
                                    <input type="hidden" name="status" value="approved"><button class="btn-primary px-3 py-1.5">{{ __('Onayla') }}</button>
                                </form>
                            @endif
                            @if ($profile->status->value === 'pending')
                                <button type="button" @click="action = 'rejected'" class="btn-secondary px-3 py-1.5">{{ __('Reddet') }}</button>
                            @endif
                            @if ($profile->status->value === 'approved')
                                <button type="button" @click="action = 'suspended'" class="btn-secondary px-3 py-1.5">{{ __('Askıya al') }}</button>
                            @endif
                        </div>
                    </div>
                    <form x-show="action" x-cloak method="POST" action="{{ route('admin.creators.update', $profile) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" :value="action">
                        <input name="reason" class="input" :placeholder="action === 'rejected' ? @js(__('Ret gerekçesi (creator görür)')) : @js(__('Askıya alma gerekçesi'))" required maxlength="255">
                        <button class="btn-primary shrink-0">{{ __('Kaydet') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $profiles->links() }}</div>
    @endif
</x-layouts.admin>
