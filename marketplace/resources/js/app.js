import Alpine from 'alpinejs';

/**
 * Tema tercihi: "light", "dark" veya "system". Seçim çerezde tutulur ki sunucu
 * ilk HTML'i doğru temayla üretsin (sayfa açılırken renk sıçraması olmaz).
 */
Alpine.store('theme', {
    preference: document.documentElement.dataset.theme || 'system',

    apply() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = this.preference === 'dark' || (this.preference === 'system' && prefersDark);
        document.documentElement.classList.toggle('dark', dark);
    },

    set(preference) {
        this.preference = preference;
        document.cookie = `theme=${preference}; path=/; max-age=31536000; SameSite=Lax`;
        this.apply();

        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (document.body.dataset.authenticated === '1' && token) {
            fetch('/tercihler', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ theme: preference }),
            });
        }
    },

    toggle() {
        const isDark = document.documentElement.classList.contains('dark');
        this.set(isDark ? 'light' : 'dark');
    },
});

window.Alpine = Alpine;
Alpine.start();

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Alpine.store('theme').apply());
