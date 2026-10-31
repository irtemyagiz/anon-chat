<div>
    <label class="flex items-start gap-2.5 text-sm text-muted">
        <input type="checkbox" name="terms" value="1" class="mt-0.5 accent-katman-600" @checked(old('terms')) required>
        <span>{!! __(':terms ve :privacy metinlerini okudum, kabul ediyorum.', [
            'terms' => '<a href="'.e(route('pages.show', 'kullanim-sartlari')).'" target="_blank" class="font-medium text-katman-700 underline dark:text-katman-300">'.e(__('Kullanım şartları')).'</a>',
            'privacy' => '<a href="'.e(route('pages.show', 'gizlilik-ve-kvkk')).'" target="_blank" class="font-medium text-katman-700 underline dark:text-katman-300">'.e(__('Gizlilik ve KVKK aydınlatma')).'</a>',
        ]) !!}</span>
    </label>
    @error('terms')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
