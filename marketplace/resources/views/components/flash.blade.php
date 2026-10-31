@if (session('status'))
    <div class="container-page pt-4">
        <div class="flex items-start gap-3 rounded-xl border border-katman-200 bg-katman-50 px-4 py-3 text-sm text-katman-900 dark:border-katman-800 dark:bg-katman-950 dark:text-katman-100" role="status">
            <x-icon name="check" class="mt-0.5 size-4 shrink-0"/> <span>{{ session('status') }}</span>
        </div>
    </div>
@endif
