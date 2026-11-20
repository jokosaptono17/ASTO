import Alpine from 'alpinejs';
import './mentari';
import './attendance';
import './geo';

window.Alpine = Alpine;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}
window.postJson = postJson;

/* ---------- Pasang aplikasi (PWA) ---------- */
Alpine.store('install', {
    prompt: null,
    installed: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
    ios: /iphone|ipad|ipod/i.test(navigator.userAgent),
    async run() {
        if (!this.prompt) return false;
        this.prompt.prompt();
        const { outcome } = await this.prompt.userChoice;
        this.prompt = null;
        if (outcome === 'accepted') this.installed = true;
        return true;
    },
});
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    Alpine.store('install').prompt = event;
});
window.addEventListener('appinstalled', () => { Alpine.store('install').installed = true; });

/* ---------- Toast ---------- */
Alpine.store('toast', {
    text: '',
    timer: null,
    show(text, ms = 3200) {
        this.text = text;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.text = ''; }, ms);
    },
});

/* ---------- Pencarian aplikasi ---------- */
const normalize = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

function searchCore(index) {
    return {
    open: false,
    q: '',
    get results() {
        const terms = normalize(this.q).split(/\s+/).filter(Boolean);
        if (!terms.length) return index;
        return index
            .map((app) => {
                const name = normalize(app.name);
                const hay = `${name} ${normalize(app.tagline)} ${normalize(app.category)}`;
                let score = 0;
                for (const t of terms) {
                    if (name.startsWith(t)) score += 5;
                    else if (name.includes(t)) score += 3;
                    else if (hay.includes(t)) score += 1;
                    else return null;
                }
                return { app, score };
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score)
            .map((r) => r.app);
    },
    show() {
        this.open = true;
        document.documentElement.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.q?.focus());
    },
    hide() {
        this.open = false;
        this.q = '';
        document.documentElement.style.overflow = '';
    },
    askMentari() {
        const q = this.q;
        this.hide();
        window.dispatchEvent(new CustomEvent('mentari-open', { detail: { ask: q } }));
    },
    };
}
Alpine.data('appSearch', (index = []) => searchCore(index));

/* ---------- Beranda desktop: tab kategori + pencarian langsung ---------- */
Alpine.data('homeHero', (index = []) => mix(searchCore(index), {
    tab: 'semua',
    clock: new Date(),
    init() { setInterval(() => { this.clock = new Date(); }, 15000); },
    get shown() {
        const list = this.q ? this.results : index.filter((a) => this.tab === 'semua' || a.category === this.tab);
        return list.slice(0, 10);
    },
    get more() {
        const total = this.q ? this.results.length : index.filter((a) => this.tab === 'semua' || a.category === this.tab).length;
        return Math.max(0, total - 10);
    },
    pick(tab) { this.tab = tab; this.q = ''; },
    submit() {
        if (!this.q.trim()) return;
        const first = this.results[0];
        if (!first) return this.askMentari();
        if (first.new_tab) window.open(first.href, '_blank', 'noopener'); else window.location = first.href;
    },
    askMentari() {
        window.dispatchEvent(new CustomEvent('mentari-open', { detail: { ask: this.q } }));
        this.q = '';
    },
    get greeting() {
        const h = this.clock.getHours();
        return h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam';
    },
    get time() { return this.clock.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); },
}));

/* ---------- Halaman aplikasi: cari + favorit ---------- */
// Gabungkan objek tanpa mengeksekusi getter (spread akan membekukan `results`)
const mix = (...parts) => parts.reduce((target, part) => Object.defineProperties(target, Object.getOwnPropertyDescriptors(part)), {});

Alpine.data('appsPage', (index = [], favIds = [], tab = 'semua') => mix(searchCore(index), {
    favs: favIds,
    tab,
    visible(id) {
        if (this.tab === 'favorit' && !this.favs.includes(id)) return false;
        return this.results.some((r) => r.id === id);
    },
    anyVisible(ids) { return ids.some((id) => this.visible(id)); },
    async toggleFav(id, url) {
        const had = this.favs.includes(id);
        this.favs = had ? this.favs.filter((f) => f !== id) : [...this.favs, id];
        try {
            const data = await postJson(url);
            if (data.favorite === had) this.favs = had ? [...this.favs, id] : this.favs.filter((f) => f !== id);
            Alpine.store('toast').show(data.favorite ? 'Ditambahkan ke favorit' : 'Dihapus dari favorit', 1800);
        } catch {
            this.favs = had ? [...this.favs, id] : this.favs.filter((f) => f !== id);
            Alpine.store('toast').show('Gagal menyimpan favorit. Periksa koneksi.');
        }
    },
}));

/* ---------- Isian PIN 6 angka ---------- */
Alpine.data('pinPad', (length = 6, autoSubmit = true) => ({
    value: '',
    length,
    shake: false,
    press(digit) {
        if (this.value.length >= this.length) return;
        this.value += digit;
        if (navigator.vibrate) navigator.vibrate(8);
        if (this.value.length === this.length && autoSubmit) {
            this.$nextTick(() => setTimeout(() => this.$root.closest('form')?.requestSubmit(), 120));
        }
    },
    back() { this.value = this.value.slice(0, -1); },
    clear() { this.value = ''; },
    keydown(e) {
        if (/^\d$/.test(e.key)) { this.press(e.key); e.preventDefault(); }
        else if (e.key === 'Backspace') { this.back(); e.preventDefault(); }
    },
    sync(e) {
        this.value = e.target.value.replace(/\D/g, '').slice(0, this.length);
        e.target.value = this.value;
        if (this.value.length === this.length && autoSubmit) this.$root.closest('form')?.requestSubmit();
    },
}));

/* ---------- Carousel dengan titik ---------- */
Alpine.data('carousel', (count = 0, auto = 6000) => ({
    active: 0,
    count,
    timer: null,
    init() {
        if (count > 1 && auto && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.play();
    },
    play() {
        clearInterval(this.timer);
        this.timer = setInterval(() => this.go((this.active + 1) % this.count), auto);
    },
    onScroll() {
        const el = this.$refs.track;
        const width = el.firstElementChild?.getBoundingClientRect().width || el.clientWidth;
        this.active = Math.round(el.scrollLeft / (width + 12));
    },
    go(i) {
        const el = this.$refs.track;
        const child = el.children[i];
        if (child) el.scrollTo({ left: child.offsetLeft - el.offsetLeft - 16, behavior: 'smooth' });
    },
}));

/* ---------- Pop-up kampanye saat aplikasi dibuka ---------- */
Alpine.data('campaignPopup', (cfg) => ({
    cfg,
    open: false,
    left: cfg.seconds,
    timer: null,
    init() {
        const key = `astoone-popup-${cfg.id}`;
        // "Setiap buka aplikasi" = sekali per sesi browser, bukan setiap pindah halaman
        if (!cfg.preview && !cfg.ack && cfg.frequency === 'session' && sessionStorage.getItem(key)) return;
        setTimeout(() => {
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
            try { sessionStorage.setItem(key, '1'); } catch {}
            if (!cfg.preview) this.post(cfg.seenUrl);
            this.timer = setInterval(() => { if (this.left > 0) this.left--; else clearInterval(this.timer); }, 1000);
        }, 700);
    },
    post(url, body = {}) {
        const token = document.querySelector('meta[name=csrf-token]')?.content;
        return fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) }).catch(() => {});
    },
    tryClose() {
        if (this.left > 0 || cfg.ack) return;
        this.close('close');
    },
    close(action = 'close', hide = true) {
        if (this.left > 0 && action !== 'close') return;
        if (!cfg.preview) this.post(cfg.closeUrl, { action });
        if (hide) { this.open = false; document.documentElement.style.overflow = ''; }
    },
}));

/* ---------- Admin: palet perintah (Ctrl+K) ---------- */
Alpine.data('adminPalette', (entries = [], searches = []) => ({
    open: false,
    q: '',
    cursor: 0,
    show() { this.open = true; this.q = ''; this.cursor = 0; this.$nextTick(() => this.$refs.q?.focus()); },
    get items() {
        const q = normalize(this.q).trim();
        const list = q ? entries.filter((e) => normalize(`${e.label} ${e.group}`).includes(q)) : entries;
        const extra = q ? searches.map((s) => ({ ...s, group: 'Cari', href: `${s.href}?q=${encodeURIComponent(this.q.trim())}`, html: `${s.label} <b class="text-ink">“${this.q.trim().replace(/[<>&"]/g, '')}”</b>` })) : [];
        return [...list, ...extra];
    },
    move(d) { const n = this.items.length; if (n) this.cursor = (this.cursor + d + n) % n; },
    go() { const it = this.items[this.cursor]; if (it) window.location = it.href; },
}));

Alpine.start();

/* ---------- Service worker ---------- */
if ('serviceWorker' in navigator && location.protocol === 'https:' || location.hostname === 'localhost') {
    window.addEventListener('load', () => navigator.serviceWorker?.register('/sw.js').catch(() => {}));
}

