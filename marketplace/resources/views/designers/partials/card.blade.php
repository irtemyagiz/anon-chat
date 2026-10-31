<a href="{{ route('designers.show', $profile) }}" class="card flex items-center gap-4 p-4 transition hover:border-katman-300 dark:hover:border-katman-700">
    @if ($profile->logo_path)
        <img src="{{ Storage::disk(config('marketplace.storage.public_media_disk'))->url($profile->logo_path) }}" alt="" class="size-14 rounded-2xl object-cover" loading="lazy">
    @else
        <span class="flex size-14 items-center justify-center rounded-2xl bg-katman-100 font-display text-xl font-extrabold text-katman-800 dark:bg-katman-900 dark:text-katman-200">{{ mb_strtoupper(mb_substr($profile->store_name, 0, 1)) }}</span>
    @endif
    <span class="min-w-0">
        <span class="block truncate font-bold text-ink">{{ $profile->store_name }}</span>
        <span class="block truncate text-sm text-muted">{{ $profile->designer_name }}</span>
        <span class="mt-1 block text-xs text-subtle">{{ trans_choice(':count model|:count model', $profile->products_count ?? 0, ['count' => $profile->products_count ?? 0]) }}</span>
    </span>
</a>
