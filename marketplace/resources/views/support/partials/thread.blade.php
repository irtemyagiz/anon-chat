<div class="space-y-4">
    @foreach ($ticket->messages as $message)
        <div @class(['card p-5', 'border-katman-300 dark:border-katman-800' => $message->is_staff_reply])>
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold">{{ $message->is_staff_reply ? __('Destek ekibi') : $message->user->first_name }}</span>
                @if ($message->is_staff_reply && ($showStaffName ?? false))<span class="text-xs text-muted">({{ $message->user->username }})</span>@endif
                <span class="text-xs text-subtle">{{ $message->created_at->format('d.m.Y H:i') }}</span>
            </p>
            <p class="mt-2 text-sm whitespace-pre-line">{{ $message->body }}</p>
            @if ($message->attachment_path)
                <a href="{{ route('support.attachment', $message) }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-katman-700 hover:underline dark:text-katman-300"><x-icon name="download" class="size-3.5"/>{{ __('Ek dosyayı indir') }}</a>
            @endif
        </div>
    @endforeach
</div>
