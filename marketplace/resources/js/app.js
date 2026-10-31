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

/**
 * Ürün sayfası 3D önizleme bileşeni. Three.js yalnızca sekme açıldığında yüklenir.
 */
Alpine.data('modelViewer', (url, extension) => ({
    state: 'idle',
    progress: 0,
    viewer: null,

    async open() {
        if (this.state !== 'idle') return;
        this.state = 'loading';

        try {
            const { mountViewer } = await import('./viewer.js');
            this.viewer = mountViewer(this.$refs.canvas, url, extension, {
                onProgress: (value) => {
                    this.progress = value;
                    if (value >= 100) this.state = 'ready';
                },
                onError: () => (this.state = 'error'),
            });
        } catch (error) {
            this.state = 'error';
        }
    },

    destroy() {
        this.viewer?.destroy();
    },
}));

/**
 * 2FA kurulumunda otpauth adresini QR koda çevirir (yalnızca o sayfada yüklenir).
 */
Alpine.data('qrCode', (text) => ({
    async init() {
        const { toCanvas } = await import('qrcode');
        toCanvas(this.$refs.canvas, text, { width: 200, margin: 1 });
    },
}));

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    if (response.status === 401) window.location.href = '/giris';
    return response.json();
}

/**
 * Beğeni / favori / takip düğmeleri. Form JS olmadan da normal gönderimle çalışır.
 */
Alpine.data('toggleAction', (initial, count = null) => ({
    on: initial,
    count,
    busy: false,
    async send(url, key) {
        if (this.busy) return;
        this.busy = true;
        try {
            const data = await postJson(url);
            if (key in data) this.on = data[key];
            if ('count' in data) this.count = data.count;
        } finally {
            this.busy = false;
        }
    },
}));

/**
 * Ürün sayfasında "Koleksiyona ekle" menüsü.
 */
Alpine.data('collectionPicker', (optionsUrl, collectionsUrl, productId) => ({
    open: false,
    loaded: false,
    items: [],
    name: '',
    async toggle() {
        this.open = !this.open;
        if (this.open && !this.loaded) {
            this.items = await (await fetch(optionsUrl, { headers: { Accept: 'application/json' } })).json();
            this.loaded = true;
        }
    },
    async toggleItem(item) {
        const data = await postJson(`${collectionsUrl}/${item.id}/model/${productId}`);
        if ('in' in data) item.in = data.in;
    },
    async create() {
        const data = await postJson(collectionsUrl, { name: this.name, product: productId });
        if (data.id) {
            this.items.unshift({ id: data.id, name: data.name, in: true });
            this.name = '';
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Alpine.store('theme').apply());
