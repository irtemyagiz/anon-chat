<div>
    <label class="flex items-start gap-2.5 text-sm text-muted">
        <input type="checkbox" name="terms" value="1" class="mt-0.5 accent-katman-600" @checked(old('terms')) required>
        <span>{{ __('Kullanım şartlarını ve gizlilik politikasını okudum, kabul ediyorum.') }}</span>
    </label>
    @error('terms')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
