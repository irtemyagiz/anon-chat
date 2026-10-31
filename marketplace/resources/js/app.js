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

/**
 * Başlıktaki arama kutusu: yazarken ürün, tasarımcı, kategori ve etiket önerir.
 * İstekler 200 ms geciktirilir ve yeni yazımda önceki istek iptal edilir.
 */
Alpine.data('searchSuggest', (url, initial = '') => ({
    q: initial,
    open: false,
    loading: false,
    active: -1,
    groups: { products: [], designers: [], categories: [], tags: [] },
    timer: null,
    controller: null,
    get items() {
        return [...this.groups.products, ...this.groups.designers, ...this.groups.categories, ...this.groups.tags];
    },
    indexOf(item) {
        return this.items.indexOf(item);
    },
    input() {
        clearTimeout(this.timer);
        this.active = -1;
        if (this.q.trim().length < 2) {
            this.open = false;
            return;
        }
        this.timer = setTimeout(() => this.fetch(), 200);
    },
    async fetch() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        try {
            const response = await fetch(`${url}?q=${encodeURIComponent(this.q.trim())}`, {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
            });
            if (!response.ok) return;
            this.groups = await response.json();
            this.open = true;
        } catch (error) {
            if (error.name !== 'AbortError') this.open = false;
        } finally {
            this.loading = false;
        }
    },
    move(step) {
        if (!this.open || this.items.length === 0) return;
        this.active = (this.active + step + this.items.length) % this.items.length;
    },
    submit(event) {
        const item = this.items[this.active];
        if (this.open && item) {
            event.preventDefault();
            window.location.href = item.url;
        }
    },
    close() {
        this.open = false;
        this.active = -1;
    },
}));

/**
 * Satın alma formu: onay adımı ve kupon önizlemesi. Kupon kodu formla birlikte
 * gönderilir; önizleme yalnızca indirimli tutarı gösterir.
 */
Alpine.data('purchaseForm', (previewUrl, initialCoupon = '') => ({
    confirming: false,
    couponOpen: initialCoupon !== '',
    coupon: initialCoupon,
    checking: false,
    couponValid: null,
    couponMessage: '',
    priceHtml: '',
    license() {
        return this.$root.querySelector('input[name="license"]:checked')?.value ?? null;
    },
    async applyCoupon() {
        if (this.coupon.trim() === '' || this.checking) return;
        this.checking = true;
        try {
            const data = await postJson(previewUrl, { coupon: this.coupon.trim(), license: this.license() });
            this.couponValid = data.valid === true;
            this.couponMessage = data.message ?? (data.errors ? Object.values(data.errors)[0][0] : '');
            this.priceHtml = this.couponValid ? data.price_html : '';
        } finally {
            this.checking = false;
        }
    },
    licenseChanged() {
        if (this.couponValid) this.applyCoupon();
    },
}));

/**
 * Büyük ürün dosyaları için parçalı yükleyici. Her dosya sırayla gönderilir; ağ hatasında
 * parça birkaç kez yeniden denenir. Sayfa kapanırsa aynı dosya yeniden seçildiğinde
 * sunucunun aldığı bayttan devam edilir (oturum kimliği localStorage'da tutulur).
 */
Alpine.data('chunkedUploader', (initUrl, resumePrefix, texts) => ({
    items: [],
    busy: false,
    select(event) {
        for (const file of event.target.files) {
            this.items.push({ file, name: file.name, size: file.size, progress: 0, status: 'waiting', message: '' });
        }
        event.target.value = '';
        this.run();
    },
    async run() {
        if (this.busy) return;
        this.busy = true;
        try {
            for (const item of this.items) {
                if (item.status === 'waiting') await this.upload(item);
            }
        } finally {
            this.busy = false;
        }
    },
    retry(item) {
        item.status = 'waiting';
        item.message = '';
        this.run();
    },
    resumeKey(file) {
        return `${resumePrefix}:${file.name}:${file.size}:${file.lastModified}`;
    },
    async request(url, options = {}) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers ?? {}) };
        const response = await fetch(url, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        return { status: response.status, data };
    },
    async session(item) {
        const key = this.resumeKey(item.file);
        let saved = null;
        try { saved = localStorage.getItem(key); } catch (e) { saved = null; }
        if (saved) {
            const { status, data } = await this.request(saved);
            if (status === 200) return data;
        }
        const { status, data } = await this.request(initUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: item.file.name, size: item.file.size }),
        });
        if (status !== 201) throw new Error(data.message || texts.failed);
        try { localStorage.setItem(key, data.status_url); } catch (e) { /* gizli pencere */ }
        return data;
    },
    async upload(item) {
        item.status = 'uploading';
        try {
            let state = await this.session(item);
            let failures = 0;
            while (state.received_bytes < state.size_bytes) {
                const end = Math.min(state.received_bytes + state.chunk_size, state.size_bytes);
                let result;
                try {
                    result = await this.request(`${state.chunk_url}?offset=${state.received_bytes}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/octet-stream' },
                        body: item.file.slice(state.received_bytes, end),
                    });
                } catch (networkError) {
                    result = { status: 0, data: {} };
                }
                if (result.status === 200 || result.status === 409) {
                    state = { ...state, ...result.data };
                    failures = 0;
                } else if (result.status === 422 || result.status === 404 || result.status === 410 || ++failures > 5) {
                    throw new Error(result.data.message || texts.failed);
                } else {
                    await new Promise((resolve) => setTimeout(resolve, 1000 * 2 ** failures));
                }
                item.progress = Math.floor((state.received_bytes / state.size_bytes) * 100);
            }
            item.status = 'processing';
            const { status, data } = await this.request(state.complete_url, { method: 'POST' });
            try { localStorage.removeItem(this.resumeKey(item.file)); } catch (e) { /* yok say */ }
            if (status !== 200) throw new Error(data.message || texts.failed);
            item.status = 'done';
            item.message = data.message;
        } catch (error) {
            item.status = 'error';
            item.message = error.message || texts.failed;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => Alpine.store('theme').apply());
