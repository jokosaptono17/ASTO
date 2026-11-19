/*
 * Geofence absensi: ambil posisi GPS terbaik, cek terhadap area (poligon),
 * pemilih kegiatan karyawan, dan editor area untuk admin (rekam titik sambil
 * berjalan di lokasi).
 */
const R = 6371008.8;
const rad = (d) => (d * Math.PI) / 180;

/* ---------------------------------------------------------- hitungan */
export function project(points, lat0, lng0) {
    const k = Math.cos(rad(lat0));
    return points.map(([la, ln]) => [rad(ln - lng0) * R * k, rad(la - lat0) * R]);
}
export function contains(lat, lng, poly) {
    const p = project(poly, lat, lng);
    let inside = false;
    for (let i = 0, j = p.length - 1; i < p.length; j = i++) {
        const [xi, yi] = p[i], [xj, yj] = p[j];
        if ((yi > 0) !== (yj > 0) && 0 < ((xj - xi) * (0 - yi)) / (yj - yi || 1e-12) + xi) inside = !inside;
    }
    return inside;
}
export function distanceToPolygon(lat, lng, poly) {
    if (poly.length < 3) return Infinity;
    if (contains(lat, lng, poly)) return 0;
    const p = project(poly, lat, lng);
    let best = Infinity;
    for (let i = 0, j = p.length - 1; i < p.length; j = i++) {
        const [ax, ay] = p[j], [bx, by] = p[i];
        const dx = bx - ax, dy = by - ay, len = dx * dx + dy * dy;
        const t = len ? Math.max(0, Math.min(1, (-ax * dx - ay * dy) / len)) : 0;
        best = Math.min(best, Math.hypot(ax + t * dx, ay + t * dy));
    }
    return best;
}
export function distance(a, b) {
    const dLat = rad(b[0] - a[0]), dLng = rad(b[1] - a[1]);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}
export function area(poly) {
    if (poly.length < 3) return 0;
    const c = [poly.reduce((s, p) => s + p[0], 0) / poly.length, poly.reduce((s, p) => s + p[1], 0) / poly.length];
    const p = project(poly, c[0], c[1]);
    let sum = 0;
    for (let i = 0, j = p.length - 1; i < p.length; j = i++) sum += p[j][0] * p[i][1] - p[i][0] * p[j][1];
    return Math.abs(sum) / 2;
}
export function perimeter(poly) {
    let sum = 0;
    for (let i = 0; i < poly.length; i++) sum += distance(poly[i], poly[(i + 1) % poly.length]);
    return poly.length > 1 ? sum : 0;
}
/** Sederhanakan garis hasil rekam berjalan (Douglas–Peucker, toleransi meter). */
export function simplify(points, tol = 2) {
    if (points.length <= 4) return points;
    const c = points[0];
    const p = project(points, c[0], c[1]);
    const keep = new Array(points.length).fill(false);
    keep[0] = keep[points.length - 1] = true;
    const stack = [[0, points.length - 1]];
    while (stack.length) {
        const [s, e] = stack.pop();
        let max = 0, idx = -1;
        const [ax, ay] = p[s], [bx, by] = p[e];
        const dx = bx - ax, dy = by - ay, len = Math.hypot(dx, dy) || 1e-9;
        for (let i = s + 1; i < e; i++) {
            const d = Math.abs(dy * p[i][0] - dx * p[i][1] + bx * ay - by * ax) / len;
            if (d > max) { max = d; idx = i; }
        }
        if (max > tol && idx > 0) { keep[idx] = true; stack.push([s, idx], [idx, e]); }
    }
    return points.filter((_, i) => keep[i]);
}
export const fmtMeters = (m) => (m == null || !isFinite(m) ? '–' : m >= 1000 ? `${(m / 1000).toFixed(1).replace('.', ',')} km` : `${Math.round(m)} m`);
export const fmtArea = (a) => (a >= 10000 ? `${(a / 10000).toFixed(2).replace('.', ',')} ha` : `${Math.round(a).toLocaleString('id-ID')} m²`);

/* ------------------------------------------------------------ GPS */
/**
 * Ambil posisi terbaik dalam `wait` ms (berhenti lebih awal bila akurasi ≤ `good` m).
 * @returns {Promise<{lat:number,lng:number,acc:number}>}
 */
export function bestPosition({ wait = 9000, good = 15 } = {}) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(Object.assign(new Error('unsupported'), { code: 'unsupported' }));
        let best = null;
        const finish = () => {
            navigator.geolocation.clearWatch(id);
            clearTimeout(timer);
            best ? resolve(best) : reject(Object.assign(new Error('timeout'), { code: 'timeout' }));
        };
        const id = navigator.geolocation.watchPosition(
            (p) => {
                const cur = { lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy) };
                if (!best || cur.acc <= best.acc) best = cur;
                if (cur.acc <= good) finish();
            },
            (e) => {
                if (e.code === 1) {
                    navigator.geolocation.clearWatch(id);
                    clearTimeout(timer);
                    reject(Object.assign(new Error('denied'), { code: 'denied' }));
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: wait },
        );
        const timer = setTimeout(finish, wait);
    });
}

const geoFailMessage = (code) => ({
    denied: 'Izin lokasi ditolak. Aktifkan izin Lokasi untuk plantasto.my.id di pengaturan browser, lalu coba lagi.',
    unsupported: 'Browser ini tidak mendukung GPS.',
    timeout: 'Sinyal GPS belum didapat. Pastikan Lokasi HP aktif dan coba di tempat terbuka.',
}[code] ?? 'GPS tidak tersedia.');

async function askServer(url, params) {
    const q = new URLSearchParams(Object.entries(params).filter(([, v]) => v != null && v !== ''));
    const res = await fetch(`${url}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error('server');
    return res.json();
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ---------- Pemilih kegiatan (halaman /absen) ---------- */
    Alpine.data('attendancePicker', (locateUrl, mapped = true) => ({
        phase: 'locating', // locating | done | failed
        pos: null,
        place: { status: mapped ? 'locating' : 'none', message: '' },
        verdicts: {},
        failCode: null,
        init() { this.locate(); },
        async locate() {
            this.phase = 'locating';
            this.failCode = null;
            try {
                this.pos = await bestPosition();
            } catch (e) {
                this.pos = null;
                this.failCode = e.code;
            }
            try {
                const data = await askServer(locateUrl, this.pos ?? {});
                this.place = data;
                this.verdicts = Object.fromEntries((data.sessions ?? []).map((s) => [s.code, s]));
            } catch {
                this.place = { status: 'unverified', message: 'Gagal menghubungi server. Periksa koneksi internet.' };
            }
            this.phase = this.pos ? 'done' : 'failed';
        },
        get headline() {
            if (this.phase === 'locating') return 'Mencari posisi Anda…';
            if (!this.pos) return 'GPS belum aktif';
            return { inside: `Anda di ${this.place.location}`, outside: 'Di luar area absensi', unverified: 'Posisi belum pasti', none: 'Lokasi didapat' }[this.place.status] ?? 'Lokasi didapat';
        },
        get detail() {
            if (this.phase === 'locating') return 'Tetap di tempat sebentar agar GPS akurat.';
            if (!this.pos) return geoFailMessage(this.failCode) + ' Absen tetap bisa, tetapi ditandai untuk diperiksa GL.';
            return this.place.message || 'Pilih kegiatan di bawah.';
        },
        get tone() {
            if (this.phase === 'locating') return 'neon';
            return { inside: 'neon', outside: 'alarm', unverified: 'hivis', none: 'neon' }[this.place.status] ?? 'hivis';
        },
        verdict(code) { return this.verdicts[code]?.status ?? (this.phase === 'locating' ? 'locating' : 'unverified'); },
    }));

    /* ---------- Cek lokasi di form absen ---------- */
    Alpine.data('geoCheck', (locateUrl, code, enabled = true) => ({
        status: enabled ? 'locating' : 'none',
        message: '',
        lat: '', lng: '', acc: '',
        location: null,
        init() { if (enabled) this.check(); },
        async check() {
            this.status = 'locating';
            this.message = 'Tetap di tempat sebentar agar GPS akurat.';
            let pos = null, fail = null;
            try { pos = await bestPosition({ wait: 8000 }); } catch (e) { fail = e.code; }
            [this.lat, this.lng, this.acc] = pos ? [pos.lat, pos.lng, pos.acc] : ['', '', ''];
            try {
                const data = await askServer(locateUrl, { code, ...(pos ?? {}) });
                this.status = data.status;
                this.location = data.location;
                this.message = pos ? data.message : geoFailMessage(fail) + ' Absen tetap bisa, tetapi ditandai untuk diperiksa GL.';
            } catch {
                this.status = 'unverified';
                this.message = 'Gagal mengecek lokasi ke server. Absen tetap bisa, tetapi ditandai untuk diperiksa GL.';
            }
        },
        get blocked() { return this.status === 'locating' || this.status === 'outside'; },
    }));

    /* ---------- Editor area untuk admin ---------- */
    Alpine.data('areaEditor', (initial = [], others = [], buffer = 25, start = null) => ({
        points: (initial ?? []).map((p) => [Number(p[0]), Number(p[1])]),
        buffer,
        me: null,          // posisi live {lat,lng,acc}
        follow: true,
        walking: false,
        marking: false,
        markProgress: 0,
        markNote: '',
        layer: 'satelit',
        mapReady: false,
        mapError: false,
        _L: null, _map: null, _poly: null, _vertices: null, _meDot: null, _meRing: null, _tiles: {}, _watch: null,
        async init() {
            this.startGps();
            try {
                const L = (await import('leaflet')).default;
                await import('leaflet/dist/leaflet.css');
                this._L = L;
                this.buildMap(others);
                this.mapReady = true;
            } catch {
                this.mapError = true;
            }
            this.$watch('points', () => this.redraw());
            window.addEventListener('pagehide', () => navigator.geolocation?.clearWatch(this._watch));
        },
        buildMap(others) {
            const L = this._L;
            const center = this.points[0] ?? start ?? (this.me && [this.me.lat, this.me.lng]) ?? others[0]?.polygon?.[0] ?? [-3.75, 114.8];
            const map = L.map(this.$refs.map, { zoomControl: false, attributionControl: true }).setView(center, this.points.length || start || this.me ? 18 : 15);
            this._tiles.satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 18, attribution: 'Citra © Esri' });
            this._tiles.jalan = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, maxNativeZoom: 19, attribution: '© OpenStreetMap' });
            this._tiles.satelit.addTo(map);
            L.control.zoom({ position: 'topright' }).addTo(map);
            others.forEach((o) => o.polygon?.length > 2 && L.polygon(o.polygon, { color: '#9fb5aa', weight: 1.5, dashArray: '4 4', fillOpacity: 0.06, interactive: false })
                .bindTooltip(o.name, { permanent: true, direction: 'center', className: 'area-label' }).addTo(map));
            this._poly = L.polygon([], { color: '#3dff8e', weight: 3, fillColor: '#3dff8e', fillOpacity: 0.16 }).addTo(map);
            this._vertices = L.layerGroup().addTo(map);
            this._meRing = L.circle([0, 0], { radius: 1, color: '#ffc23d', weight: 1, fillOpacity: 0.12, interactive: false });
            this._meDot = L.circleMarker([0, 0], { radius: 7, color: '#06120d', weight: 3, fillColor: '#ffc23d', fillOpacity: 1, interactive: false });
            map.on('click', (e) => this.points.push([+e.latlng.lat.toFixed(7), +e.latlng.lng.toFixed(7)]));
            map.on('dragstart', () => { this.follow = false; });
            this._map = map;
            this.redraw();
            if (this.me) {
                this._meRing.setLatLng([this.me.lat, this.me.lng]).setRadius(this.me.acc).addTo(map);
                this._meDot.setLatLng([this.me.lat, this.me.lng]).addTo(map);
            }
            if (this.points.length > 2) map.fitBounds(this._poly.getBounds(), { padding: [40, 40] });
            setTimeout(() => map.invalidateSize(), 200);
        },
        setLayer(name) {
            if (!this._map || this.layer === name) return;
            this._tiles[this.layer].remove();
            this._tiles[name].addTo(this._map);
            this.layer = name;
        },
        redraw() {
            if (!this._map) return;
            const L = this._L;
            this._poly.setLatLngs(this.points);
            this._vertices.clearLayers();
            this.points.forEach((p, i) => {
                const m = L.marker(p, {
                    draggable: true,
                    icon: L.divIcon({ className: '', html: `<span class="area-vertex">${i + 1}</span>`, iconSize: [26, 26], iconAnchor: [13, 13] }),
                });
                m.on('dragend', (e) => { const ll = e.target.getLatLng(); this.points[i] = [+ll.lat.toFixed(7), +ll.lng.toFixed(7)]; this.points = [...this.points]; });
                m.on('click', (e) => { e.originalEvent?.stopPropagation(); });
                m.addTo(this._vertices);
            });
        },
        startGps() {
            if (!navigator.geolocation) { this.markNote = 'Perangkat ini tidak punya GPS. Ketuk peta untuk menambah titik.'; return; }
            this._watch = navigator.geolocation.watchPosition((p) => {
                this.me = { lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy) };
                if (this._map) {
                    const ll = [this.me.lat, this.me.lng];
                    this._meRing.setLatLng(ll).setRadius(this.me.acc).addTo(this._map);
                    this._meDot.setLatLng(ll).addTo(this._map);
                    if (this.follow) this._map.setView(ll, Math.max(this._map.getZoom(), 18), { animate: true });
                }
                if (this.walking && this.me.acc <= 20) {
                    const last = this.points[this.points.length - 1];
                    if (!last || distance(last, [this.me.lat, this.me.lng]) >= 8) this.points.push([+this.me.lat.toFixed(7), +this.me.lng.toFixed(7)]);
                }
            }, (e) => {
                this.markNote = e.code === 1 ? 'Izin lokasi ditolak — aktifkan Lokasi di browser agar bisa merekam titik.' : 'Menunggu sinyal GPS…';
            }, { enableHighAccuracy: true, maximumAge: 0 });
        },
        get quality() {
            if (!this.me) return { label: 'Menunggu GPS', tone: 'mist' };
            if (this.me.acc <= 8) return { label: `GPS ±${this.me.acc} m · sangat baik`, tone: 'neon' };
            if (this.me.acc <= 20) return { label: `GPS ±${this.me.acc} m · baik`, tone: 'neon' };
            if (this.me.acc <= 40) return { label: `GPS ±${this.me.acc} m · sedang`, tone: 'hivis' };
            return { label: `GPS ±${this.me.acc} m · lemah`, tone: 'alarm' };
        },
        /** Tandai titik: rata-rata posisi selama ±5 detik agar lebih presisi. */
        markHere() {
            if (!this.me || this.marking) return;
            this.marking = true;
            this.markProgress = 0;
            const samples = [];
            const started = Date.now();
            const tick = setInterval(() => {
                if (this.me) samples.push({ ...this.me });
                this.markProgress = Math.min(1, (Date.now() - started) / 5000);
                if (this.markProgress >= 1) {
                    clearInterval(tick);
                    const good = samples.filter((s) => s.acc <= Math.max(20, Math.min(...samples.map((x) => x.acc)) * 1.5));
                    const w = good.reduce((s, x) => s + 1 / (x.acc * x.acc), 0);
                    const lat = good.reduce((s, x) => s + x.lat / (x.acc * x.acc), 0) / w;
                    const lng = good.reduce((s, x) => s + x.lng / (x.acc * x.acc), 0) / w;
                    const acc = Math.min(...good.map((x) => x.acc));
                    this.points.push([+lat.toFixed(7), +lng.toFixed(7)]);
                    this.markNote = acc > 20 ? `Titik ${this.points.length} ditandai, tapi GPS ±${acc} m — sebaiknya ulangi di tempat lebih terbuka.` : `Titik ${this.points.length} ditandai (±${acc} m). Jalan ke sudut berikutnya.`;
                    this.marking = false;
                    if (navigator.vibrate) navigator.vibrate(60);
                }
            }, 250);
        },
        toggleWalk() {
            this.walking = !this.walking;
            this.markNote = this.walking ? 'Merekam otomatis tiap 8 m. Jalan menyusuri batas area, lalu tekan Selesai.' : 'Rekaman berjalan berhenti.';
            if (!this.walking && this.points.length > 30) this.points = simplify(this.points, 1.5);
        },
        undo() { this.points.pop(); this.points = [...this.points]; },
        clearAll() { if (this.points.length && confirm('Hapus semua titik area ini?')) this.points = []; },
        locateMe() { this.follow = true; if (this.me && this._map) this._map.setView([this.me.lat, this.me.lng], 18); },
        get stats() {
            return { n: this.points.length, area: fmtArea(area(this.points)), perimeter: fmtMeters(perimeter(this.points)) };
        },
        get myVerdict() {
            if (!this.me || this.points.length < 3) return null;
            const d = distanceToPolygon(this.me.lat, this.me.lng, this.points);
            if (d === 0) return { tone: 'neon', text: 'Posisi Anda di dalam area' };
            if (d <= this.buffer) return { tone: 'neon', text: `Posisi Anda ${fmtMeters(d)} dari garis — masih dalam toleransi` };
            return { tone: 'alarm', text: `Posisi Anda ${fmtMeters(d)} di luar area` };
        },
        get json() { return JSON.stringify(this.points); },
        get coords() { return this.me ? `${this.me.lat.toFixed(6)}, ${this.me.lng.toFixed(6)}` : '–'; },
        copyCoords() { if (this.me) { navigator.clipboard?.writeText(this.coords); this.$store.toast?.show('Koordinat disalin'); } },
    }));

    /* ---------- Panel "Lokasi saya sekarang" (halaman Area GPS) ---------- */
    Alpine.data('myLocation', (areas = []) => ({
        me: null,
        error: '',
        _watch: null,
        init() {
            if (!navigator.geolocation) { this.error = 'Perangkat ini tidak punya GPS.'; return; }
            this._watch = navigator.geolocation.watchPosition(
                (p) => { this.me = { lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy) }; this.error = ''; },
                (e) => { this.error = e.code === 1 ? 'Izin lokasi ditolak. Aktifkan Lokasi untuk situs ini di pengaturan browser.' : 'Menunggu sinyal GPS… coba di tempat terbuka.'; },
                { enableHighAccuracy: true, maximumAge: 0 },
            );
            window.addEventListener('pagehide', () => navigator.geolocation.clearWatch(this._watch));
        },
        get coords() { return this.me ? `${this.me.lat.toFixed(6)}, ${this.me.lng.toFixed(6)}` : ''; },
        get quality() {
            if (!this.me) return { label: 'Mencari sinyal GPS…', tone: 'mist' };
            const a = this.me.acc;
            return a <= 8 ? { label: `±${a} m · sangat baik`, tone: 'neon' } : a <= 20 ? { label: `±${a} m · baik`, tone: 'neon' } : a <= 40 ? { label: `±${a} m · sedang`, tone: 'hivis' } : { label: `±${a} m · lemah`, tone: 'alarm' };
        },
        distTo(id) {
            const a = areas.find((x) => x.id === id);
            if (!this.me || !a?.polygon?.length) return null;
            return distanceToPolygon(this.me.lat, this.me.lng, a.polygon);
        },
        badge(id) {
            const d = this.distTo(id);
            if (d === null) return '';
            const a = areas.find((x) => x.id === id);
            return d === 0 ? 'Anda di sini' : d <= a.buffer ? `${fmtMeters(d)} · dalam toleransi` : `${fmtMeters(d)} dari Anda`;
        },
        get verdict() {
            if (!this.me) return null;
            const mapped = areas.filter((a) => a.polygon?.length > 2);
            if (!mapped.length) return { tone: 'mist', text: 'Belum ada area yang dipetakan.' };
            const near = mapped.map((a) => ({ a, d: distanceToPolygon(this.me.lat, this.me.lng, a.polygon) })).sort((x, y) => x.d - y.d)[0];
            if (near.d === 0) return { tone: 'neon', text: `Anda di dalam area ${near.a.name}` };
            if (near.d <= near.a.buffer) return { tone: 'neon', text: `Anda ${fmtMeters(near.d)} dari ${near.a.name} — masih dalam toleransi` };
            return { tone: 'hivis', text: `Di luar area. Terdekat: ${near.a.name} (${fmtMeters(near.d)})` };
        },
        copy() { if (this.me) { navigator.clipboard?.writeText(this.coords); this.$store.toast?.show('Koordinat disalin'); } },
    }));
});

/* ---------- Kartu "Absensi hari ini" di beranda ---------- */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('homeAttendance', (locateUrl, times = null) => ({
        now: Date.now() / 1000,
        place: null,
        timer: null,
        async init() {
            this.timer = setInterval(() => {
                this.now = Date.now() / 1000;
                // Saat absen dibuka/ditutup, muat ulang agar kartu berganti keadaan
                if (times && times[1] && this.now >= times[1] + 1 && !this._reloaded) { this._reloaded = true; setTimeout(() => location.reload(), 800); }
            }, 1000);
            // Posisi hanya dicek bila izin lokasi sudah diberikan (beranda tidak meminta izin)
            try {
                const perm = await navigator.permissions?.query({ name: 'geolocation' });
                if (perm?.state !== 'granted') return;
                const pos = await bestPosition({ wait: 5000, good: 30 });
                const res = await fetch(`${locateUrl}?lat=${pos.lat}&lng=${pos.lng}&acc=${pos.acc}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                if (data.status === 'inside' || (data.status !== 'none' && data.location)) this.place = data;
            } catch { /* abaikan: kartu tetap tampil tanpa lokasi */ }
        },
        get left() { return times?.[1] ? Math.max(0, Math.round(times[1] - this.now)) : 0; },
        get countdown() {
            const s = this.left;
            if (s < 60) return `${s} dtk`;
            const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
            return h ? `${h} j ${m} mnt` : `${m} mnt`;
        },
        get countdownShort() {
            const s = this.left, h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
            return h ? `${h}:${String(m).padStart(2, '0')}` : `${m}:${String(sec).padStart(2, '0')}`;
        },
        get progress() {
            if (!times?.[0]) return 0;
            return Math.min(100, Math.max(0, ((this.now - times[0]) / (times[1] - times[0])) * 100));
        },
        get placeText() {
            if (!this.place) return '';
            return this.place.status === 'inside' ? this.place.location : `${fmtMeters(this.place.distance)} dari ${this.place.location}`;
        },
    }));
});
