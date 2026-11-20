/*
 * Absensi AstoOne: pemindai QR (kamera), hitung durasi tidur, gambar QR,
 * dan halaman live host (polling).
 */
// Pustaka QR dimuat hanya di halaman yang membutuhkannya (agar beranda tetap ringan)
const loadQRCode = () => import('qrcode').then((m) => m.default ?? m);
const loadJsQR = () => import('jsqr').then((m) => m.default ?? m);

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ---------- Pemindai QR karyawan ---------- */
    Alpine.data('qrScanner', (auto = true, prefix = '/absen/', okText = 'QR terbaca, membuka absen…', badText = 'QR ini bukan QR absen AstoOne.') => ({
        state: 'idle', // idle | starting | scanning | error
        message: 'Arahkan kamera ke QR yang tampil di layar P5M / safety talk.',
        stream: null,
        detector: null,
        jsQR: null,
        raf: null,
        init() {
            if (!navigator.mediaDevices?.getUserMedia) {
                this.state = 'error';
                this.message = 'Browser ini tidak bisa membuka kamera. Pakai aplikasi kamera HP, atau ketik kode 6 angka di bawah.';
                return;
            }
            // Langsung coba buka kamera; bila izin belum diberikan, tombol tetap tersedia
            if (auto) this.start();
            window.addEventListener('pagehide', () => this.stop());
        },
        async start() {
            this.state = 'starting';
            this.message = 'Membuka kamera…';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                if ('BarcodeDetector' in window) {
                    try { this.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch { this.detector = null; }
                }
                if (!this.detector) this.jsQR = await loadJsQR();
                this.state = 'scanning';
                this.loop();
            } catch {
                this.state = 'error';
                this.message = 'Izin kamera ditolak atau kamera tidak tersedia. Izinkan kamera di pengaturan browser, atau ketik kode 6 angka di bawah.';
            }
        },
        stop() {
            cancelAnimationFrame(this.raf);
            this.stream?.getTracks().forEach((t) => t.stop());
        },
        async loop() {
            if (this.state !== 'scanning') return;
            const video = this.$refs.video;
            let text = null;
            if (video.readyState >= 2) {
                try {
                    if (this.detector) {
                        const codes = await this.detector.detect(video);
                        text = codes[0]?.rawValue ?? null;
                    } else {
                        const canvas = this.$refs.canvas;
                        const w = 480;
                        const h = Math.round((video.videoHeight / video.videoWidth) * w) || w;
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(video, 0, 0, w, h);
                        text = this.jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: 'dontInvert' })?.data ?? null;
                    }
                } catch { /* bingkai berikutnya */ }
            }
            if (text && this.handle(text)) return;
            this.raf = requestAnimationFrame(() => setTimeout(() => this.loop(), 120));
        },
        handle(text) {
            try {
                const url = new URL(text, location.origin);
                if (url.origin === location.origin && url.pathname.startsWith(prefix)) {
                    if (navigator.vibrate) navigator.vibrate(60);
                    this.state = 'starting';
                    this.message = okText;
                    this.stop();
                    location.href = url.href;
                    return true;
                }
            } catch { /* bukan URL */ }
            this.message = badText;
            return false;
        },
    }));

    /* ---------- Durasi tidur & status fatigue ---------- */
    Alpine.data('sleepForm', (sleep = '', wake = '') => ({
        sleep,
        wake,
        get minutes() {
            if (!/^\d{2}:\d{2}$/.test(this.sleep) || !/^\d{2}:\d{2}$/.test(this.wake)) return null;
            const [sh, sm] = this.sleep.split(':').map(Number);
            const [wh, wm] = this.wake.split(':').map(Number);
            let m = (wh * 60 + wm) - (sh * 60 + sm);
            if (m <= 0) m += 1440;
            return m;
        },
        get tone() { return this.minutes >= 360 ? 'fit' : this.minutes >= 300 ? 'coaching' : 'risk'; },
        get status() { return { fit: 'Fit to Work', coaching: 'Coaching Atasan', risk: 'Fatigue Risk' }[this.tone]; },
        get label() { const h = Math.floor(this.minutes / 60), m = this.minutes % 60; return `${h} jam${m ? ` ${m} mnt` : ''}`; },
        get toneText() { return { fit: 'text-neon', coaching: 'text-hivis', risk: 'text-alarm' }[this.tone]; },
        get toneBar() { return { fit: 'bg-neon', coaching: 'bg-hivis', risk: 'bg-alarm' }[this.tone]; },
        get toneChip() { return { fit: 'bg-neon/15 text-neon', coaching: 'bg-hivis/15 text-hivis', risk: 'bg-alarm/15 text-red-300' }[this.tone]; },
    }));

    /* ---------- Live host & layar presentasi ---------- */
    Alpine.data('liveSession', (feedUrl, initial = {}) => ({
        feed: initial,
        tick: 0,
        remaining: initial.next ?? 30,
        filter: 'semua',
        fresh: new Set(),
        timer: null,
        init() {
            this.draw();
            this.timer = setInterval(() => {
                this.remaining = Math.max(0, this.remaining - 1);
                if (++this.tick % 4 === 0 || this.remaining === 0) this.refresh();
            }, 1000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
        },
        async refresh() {
            try {
                const res = await fetch(feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                const known = new Set((this.feed.records || []).map((r) => r.id));
                data.records.forEach((r) => { if (!known.has(r.id) && this.feed.records) this.fresh.add(r.id); });
                const urlChanged = data.url !== this.feed.url;
                this.feed = data;
                this.remaining = data.next;
                if (urlChanged) this.draw();
                setTimeout(() => { this.fresh.clear(); }, 4000);
            } catch { /* koneksi putus sesaat */ }
        },
        draw() {
            this.$nextTick(async () => {
                const QRCode = await loadQRCode();
                this.$root.querySelectorAll('canvas[data-qr]').forEach((canvas) => {
                    if (!this.feed.url) return;
                    QRCode.toCanvas(canvas, this.feed.url, {
                        width: Number(canvas.dataset.size || 280), margin: 1, errorCorrectionLevel: 'M',
                        color: { dark: '#04170d', light: '#ffffff' },
                    });
                });
            });
        },
        get records() {
            const all = this.feed.records || [];
            return {
                semua: all,
                terlambat: all.filter((r) => r.late),
                fatigue: all.filter((r) => r.fatigue && r.fatigue !== 'fit'),
                pemateri: all.filter((r) => r.role === 'pemateri'),
                lokasi: all.filter((r) => r.geo === 'unverified'),
            }[this.filter] ?? all;
        },
        get ring() { return `${(this.remaining / 30) * 100}`; },
    }));

    /* ---------- QR statis (mis. tautan sesi mode terbuka) ---------- */
    Alpine.data('qrImage', (text, size = 220) => ({
        async init() {
            const QRCode = await loadQRCode();
            QRCode.toCanvas(this.$refs.canvas, text, { width: size, margin: 1, color: { dark: '#04170d', light: '#ffffff' } });
        },
    }));
});
