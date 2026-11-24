/*
 * Mentari: mata maskot mengikuti pointer + panel chat.
 * Chat memakai /mentari/chat (AI di server bila MENTARI_ENDPOINT diatur,
 * atau otak bawaan). Bila jaringan putus, panduan kecil di browser menjawab.
 */

/* ---------- Mata mengikuti pointer ---------- */
const eyes = () => [...document.querySelectorAll('[data-mentari] [data-eyes]')];
let frame = null;
let idleTimer = null;

function look(x, y) {
    eyes().forEach((group) => {
        const box = group.ownerSVGElement.getBoundingClientRect();
        const cx = box.left + box.width / 2;
        const cy = box.top + box.height * 0.36;
        const angle = Math.atan2(y - cy, x - cx);
        const reach = Math.min(1, Math.hypot(x - cx, y - cy) / 260);
        group.style.transform = `translate(${(Math.cos(angle) * 6 * reach).toFixed(2)}px, ${(Math.sin(angle) * 4 * reach).toFixed(2)}px)`;
    });
}

function wander() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        const dx = (Math.random() * 2 - 1) * 6;
        const dy = (Math.random() * 2 - 1) * 3;
        eyes().forEach((group) => { group.style.transform = `translate(${dx}px, ${dy}px)`; });
        wander();
    }, 2200 + Math.random() * 2500);
}

window.addEventListener('pointermove', (event) => {
    if (frame) return;
    frame = requestAnimationFrame(() => {
        frame = null;
        look(event.clientX, event.clientY);
        wander();
    });
}, { passive: true });
document.addEventListener('DOMContentLoaded', wander);

/* ---------- Cadangan saat offline ---------- */
const waLink = (number, text) => `https://wa.me/${number}?text=${encodeURIComponent(text)}`;

function offlineAnswer(text, number) {
    const q = text.toLowerCase();
    if (/pin|lupa|login|masuk|terdaftar/.test(q)) {
        return { text: 'Sepertinya koneksi sedang putus. Untuk urusan PIN atau NRP, admin bisa dihubungi lewat WhatsApp.', actions: [{ label: 'Chat admin di WhatsApp', href: waLink(number, `Halo Admin AstoOne, saya butuh bantuan: ${text}`), external: true }] };
    }
    return { text: 'Koneksi ke server sedang putus, jadi saya belum bisa mencari jawabannya. Coba lagi saat sinyal membaik.', actions: [] };
}

/* ---------- Komponen Alpine ---------- */
const store = {
    load(key) { try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; } },
    save(key, value) { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* privat */ } },
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('mentariChat', (config) => ({
        open: false,
        input: '',
        typing: false,
        thinking: false,
        bubble: '',
        tip: 0,
        messages: [],
        chips: config.chips || [],
        placeholder: config.placeholder || 'Tanya Mentari…',

        init() {
            const saved = config.storeKey ? store.load(config.storeKey) : null;
            this.messages = Array.isArray(saved) && saved.length ? saved : [{ role: 'assistant', text: config.greeting }];
            const tips = config.tips || [];
            if (tips.length && !sessionStorage.getItem('mentari:tips-shown')) {
                setTimeout(() => this.rotate(tips), 2200);
            }
            window.addEventListener('mentari-open', (event) => this.show(event.detail?.ask));
        },
        persist() {
            if (config.storeKey) store.save(config.storeKey, this.messages.slice(-30));
        },
        rotate(tips) {
            if (this.open || this.tip >= tips.length) { this.bubble = ''; sessionStorage.setItem('mentari:tips-shown', '1'); return; }
            this.bubble = tips[this.tip++];
            this.talk(1800);
            setTimeout(() => { this.bubble = ''; }, 6000);
            setTimeout(() => this.rotate(tips), 11000);
        },
        talk(ms = 1200) {
            document.querySelectorAll('[data-mentari]').forEach((svg) => svg.classList.add('m-talking'));
            clearTimeout(this.talkTimer);
            this.talkTimer = setTimeout(() => document.querySelectorAll('[data-mentari]').forEach((svg) => svg.classList.remove('m-talking')), ms);
        },
        show(ask = null) {
            this.open = true;
            this.bubble = '';
            document.documentElement.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.scroll();
                if (ask) this.send(ask);
                else if (window.matchMedia('(min-width: 640px)').matches) this.$refs.input?.focus();
            });
        },
        hide() {
            this.open = false;
            document.documentElement.style.overflow = '';
        },
        clear() {
            this.messages = [{ role: 'assistant', text: config.greeting }];
            this.persist();
        },
        scroll() {
            this.$nextTick(() => { const log = this.$refs.log; if (log) log.scrollTop = log.scrollHeight; });
        },
        async send(text = null) {
            const message = (text ?? this.input).trim();
            if (!message || this.typing) return;
            this.input = '';
            this.messages.push({ role: 'user', text: message });
            this.typing = true;
            this.scroll();

            let answer = null;
            const started = performance.now();
            // Pertanyaan yang butuh AI bisa beberapa detik — tampilkan "sedang berpikir"
            const thinkTimer = setTimeout(() => { this.thinking = true; this.scroll(); }, 1200);
            try {
                const history = this.messages.slice(-9, -1).map((m) => ({ role: m.role, content: m.text }));
                const data = await window.postJson(config.url, { message, history, page: config.page });
                if (data.reply) {
                    answer = {
                        text: data.reply, actions: data.actions || [], cards: data.cards || [],
                        source: data.source || null, provider: data.provider_label || null, log_id: data.log_id || null, rated: null,
                    };
                }
            } catch { /* jaringan putus */ }
            clearTimeout(thinkTimer);

            answer ??= offlineAnswer(message, config.whatsapp);
            const wait = 450 - (performance.now() - started);
            if (wait > 0) await new Promise((resolve) => setTimeout(resolve, wait));

            this.typing = false;
            this.thinking = false;
            this.messages.push({ role: 'assistant', ...answer });
            this.persist();
            this.talk(Math.min(4000, 400 + answer.text.length * 18));
            this.scroll();
        },
        /* 👍 / 👎 untuk satu jawaban — membantu admin memperbaiki Mentari */
        async rate(m, helpful) {
            if (!m.log_id || m.rated !== null && m.rated !== undefined) return;
            m.rated = helpful;
            this.persist();
            try { await window.postJson(config.feedbackUrl, { log_id: m.log_id, helpful }); } catch { /* abaikan */ }
        },
    }));
});
