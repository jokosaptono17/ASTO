/* Ujian Event: ruang ujian peserta, papan pantau admin, formulir ujian & soal. */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

async function send(url, body = {}, method = 'POST') {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    let data = {};
    try { data = await res.json(); } catch {}
    if (res.status === 419) { location.reload(); throw new Error('csrf'); }
    return { status: res.status, ok: res.ok, data };
}

export const clock = (s) => {
    s = Math.max(0, Math.floor(s));
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    return h ? `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}` : `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
};
const humanLeft = (s) => {
    if (s <= 0) return 'sekarang';
    const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
    if (d) return `${d}h ${h}j`;
    if (h) return `${h}j ${m}m`;
    return s < 60 ? `${s} dtk` : `${Math.ceil(s / 60)} mnt`;
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* Hitung mundur kecil (beranda, lobi). target = epoch detik. */
    Alpine.data('countdown', (target, done = null) => ({
        left: 0, timer: null,
        init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
        destroy() { clearInterval(this.timer); },
        tick() {
            this.left = Math.max(0, Math.round(target - Date.now() / 1000));
            if (this.left === 0 && done) { clearInterval(this.timer); setTimeout(() => (done === 'reload' ? location.reload() : null), 1500); }
        },
        get human() { return humanLeft(this.left); },
        get clock() { return clock(this.left); },
    }));

    /* Kolom kode ujian: huruf besar, tanpa spasi. */
    Alpine.data('codeField', (initial = '') => ({
        code: initial,
        clean() { this.code = this.code.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 30); },
    }));

    /* ---------------- Ruang ujian ---------------- */
    Alpine.data('examRoom', (cfg) => ({
        q: cfg.paper,
        i: 0,
        left: 0,
        offset: cfg.serverNow - Date.now(),
        deadline: cfg.deadline,
        status: 'saved',          // saved | saving | offline
        pending: {},              // {qid: choice|null} belum terkirim
        pendingFlags: {},
        inflight: false,
        retry: null,
        navOpen: false,
        confirmOpen: false,
        submitting: false,
        submitError: '',
        warn: { show: false, n: cfg.violations, text: '' },
        violations: cfg.violations,
        hiddenAt: null,
        zoom: null,
        scale: 1,
        timer: null,
        beat: null,
        dir: 1,
        key: `astoone-exam-${cfg.id}`,

        init() {
            // Pulihkan jawaban yang belum sempat terkirim (HP mati / tanpa sinyal)
            try {
                const saved = JSON.parse(localStorage.getItem(this.key) || '{}');
                Object.entries(saved.pending || {}).forEach(([id, c]) => { const it = this.find(id); if (it) { it.choice = c; this.pending[id] = c; } });
                Object.entries(saved.flags || {}).forEach(([id, f]) => { const it = this.find(id); if (it) { it.flagged = f; this.pendingFlags[id] = f; } });
                if (Number.isInteger(saved.i) && saved.i < this.q.length) this.i = saved.i;
                this.scale = saved.scale || 1;
            } catch {}
            if (Object.keys(this.pending).length || Object.keys(this.pendingFlags).length) this.flush();

            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            this.beat = setInterval(() => this.flush(true), 30000);

            document.addEventListener('visibilitychange', () => this.onVisibility());
            window.addEventListener('online', () => this.flush());
            window.addEventListener('beforeunload', (e) => { if (!this.submitting && this.dirty()) { e.preventDefault(); e.returnValue = ''; } });
            history.pushState(null, '', location.href);
            window.addEventListener('popstate', () => { history.pushState(null, '', location.href); this.navOpen = true; });
            this.$watch('i', () => { this.persist(); this.$nextTick(() => document.getElementById('soal')?.scrollIntoView({ block: 'start', behavior: 'smooth' })); });
        },

        find(id) { return this.q.find((x) => String(x.id) === String(id)); },
        get cur() { return this.q[this.i]; },
        get answered() { return this.q.filter((x) => x.choice).length; },
        get flagged() { return this.q.filter((x) => x.flagged).length; },
        get empty() { return this.q.length - this.answered; },
        get progress() { return this.q.length ? Math.round((this.answered / this.q.length) * 100) : 0; },
        get clock() { return clock(this.left); },
        get urgency() { return this.left <= 60 ? 'alarm' : this.left <= 300 ? 'hivis' : 'neon'; },
        dirty() { return Object.keys(this.pending).length > 0 || Object.keys(this.pendingFlags).length > 0; },

        tick() {
            this.left = Math.max(0, Math.round((this.deadline - (Date.now() + this.offset)) / 1000));
            if (this.left === 0 && !this.submitting) this.submit(true);
        },
        persist() {
            try { localStorage.setItem(this.key, JSON.stringify({ pending: this.pending, flags: this.pendingFlags, i: this.i, scale: this.scale })); } catch {}
        },

        go(n) { if (n >= 0 && n < this.q.length) { this.dir = n > this.i ? 1 : -1; this.i = n; this.navOpen = false; } },
        next() { if (this.i < this.q.length - 1) this.go(this.i + 1); else this.confirmOpen = true; },
        prev() { this.go(this.i - 1); },
        firstEmpty() { const n = this.q.findIndex((x) => !x.choice); this.confirmOpen = false; this.go(n < 0 ? 0 : n); },

        choose(key) {
            const it = this.cur;
            it.choice = it.choice === key && cfg.allowClear ? null : key;
            this.pending[it.id] = it.choice;
            this.persist();
            this.flush();
        },
        clear() { this.cur.choice = null; this.pending[this.cur.id] = null; this.persist(); this.flush(); },
        toggleFlag() { const it = this.cur; it.flagged = !it.flagged; this.pendingFlags[it.id] = it.flagged; this.persist(); this.flush(); },
        onKey(e) {
            if (this.confirmOpen || this.warn.show || this.navOpen || e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA/.test(e.target.tagName)) return;
            if (this.zoom) { if (e.key === 'Escape') this.zoom = null; return; }
            if (e.key === 'ArrowRight') { e.preventDefault(); this.next(); return; }
            if (e.key === 'ArrowLeft') { e.preventDefault(); this.prev(); return; }
            const k = e.key.toUpperCase();
            const idx = /^[1-6]$/.test(k) ? Number(k) - 1 : 'ABCDEF'.indexOf(k);
            const opt = idx >= 0 ? this.cur.options[idx] : null;
            if (opt && k.length === 1) { e.preventDefault(); this.choose(opt.key); }
        },
        zoomIn() { this.scale = Math.min(1.4, +(this.scale + 0.1).toFixed(1)); this.persist(); },
        zoomOut() { this.scale = Math.max(0.9, +(this.scale - 0.1).toFixed(1)); this.persist(); },

        async flush(heartbeat = false) {
            if (this.inflight || this.submitting) return;
            const answers = { ...this.pending }, flags = { ...this.pendingFlags };
            const ids = Object.keys(answers), fids = Object.keys(flags);
            if (!ids.length && !fids.length && !heartbeat) return;
            this.inflight = true;
            if (ids.length || fids.length) this.status = 'saving';
            try {
                let r;
                if (ids.length === 1 && fids.length <= 1 && (!fids.length || fids[0] === ids[0])) {
                    r = await send(cfg.urls.answer, { question_id: Number(ids[0]), choice: answers[ids[0]], ...(fids.length ? { flagged: flags[fids[0]] } : {}) });
                } else if (!ids.length && fids.length === 1) {
                    const it = this.find(fids[0]);
                    r = await send(cfg.urls.answer, { question_id: Number(fids[0]), choice: it.choice, flagged: flags[fids[0]] });
                } else {
                    r = await send(cfg.urls.sync, { answers, flags });
                }
                if (r.data?.finished) return this.leave(r.data.redirect);
                if (!r.ok) throw new Error(r.data?.message || 'gagal');
                ids.forEach((id) => { if (this.pending[id] === answers[id]) delete this.pending[id]; });
                fids.forEach((id) => { if (this.pendingFlags[id] === flags[id]) delete this.pendingFlags[id]; });
                this.persist();
                this.syncClock(r.data.left);
                this.status = this.dirty() ? 'saving' : 'saved';
                clearTimeout(this.retry);
                if (this.dirty()) setTimeout(() => this.flush(), 300);
            } catch {
                this.status = 'offline';
                clearTimeout(this.retry);
                this.retry = setTimeout(() => this.flush(), 5000);
            } finally {
                this.inflight = false;
            }
        },
        syncClock(left) {
            if (typeof left !== 'number') return;
            const server = Date.now() + this.offset + left * 1000;
            if (Math.abs(server - this.deadline) > 2000) this.deadline = server; // admin menambah waktu
        },

        onVisibility() {
            if (this.submitting) return;
            if (document.hidden) { this.hiddenAt = Date.now(); return; }
            if (!this.hiddenAt || Date.now() - this.hiddenAt < 1500) { this.hiddenAt = null; return; }
            this.hiddenAt = null;
            this.violations++;
            const max = cfg.maxViolations;
            this.warn = {
                show: true, n: this.violations,
                text: max ? (this.violations >= max ? 'Batas pelanggaran tercapai. Ujian Anda dikumpulkan otomatis.' : `Peringatan ${this.violations} dari ${max}. Pada pelanggaran ke-${max} ujian dikumpulkan otomatis.`) : 'Kejadian ini tercatat dan terlihat oleh panitia.',
            };
            send(cfg.urls.violation, { type: 'keluar-halaman' }).then((r) => { if (r.data?.finished) setTimeout(() => this.leave(r.data.redirect), 2500); }).catch(() => {});
        },

        async submit(auto = false) {
            if (this.submitting) return;
            this.submitting = true;
            this.submitError = '';
            const answers = Object.fromEntries(this.q.map((x) => [x.id, x.choice]));
            const flags = Object.fromEntries(this.q.map((x) => [x.id, !!x.flagged]));
            for (let t = 0; t < (auto ? 6 : 2); t++) {
                try {
                    const r = await send(cfg.urls.submit, { answers, flags, auto });
                    if (r.ok || r.data?.finished) return this.leave(r.data.redirect || cfg.urls.result);
                } catch {}
                await new Promise((ok) => setTimeout(ok, 2500));
            }
            this.submitting = false;
            this.submitError = 'Belum terkirim — sinyal hilang. Jawaban aman tersimpan di HP ini. Tekan "Kumpulkan" lagi saat ada sinyal.';
            this.status = 'offline';
            if (auto) { this.confirmOpen = true; this.retry = setTimeout(() => this.submit(true), 8000); }
        },
        leave(url) {
            this.submitting = true;
            try { localStorage.removeItem(this.key); } catch {}
            window.location.replace(url);
        },
    }));

    /* ---------------- Papan pantau admin ---------------- */
    Alpine.data('examLive', (cfg) => ({
        rows: cfg.feed.rows, stats: cfg.feed.stats, state: cfg.feed.state, at: cfg.feed.at,
        tab: 'semua', q: '', busy: null, timer: null, flash: '', lastSync: Date.now(),
        init() { this.timer = setInterval(() => this.refresh(), cfg.interval || 8000); setInterval(() => { this.rows.forEach((r) => { if (r.left > 0) r.left--; }); }, 1000); },
        async refresh() {
            try {
                const r = await fetch(cfg.feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const d = await r.json();
                Object.assign(this, { rows: d.rows, stats: d.stats, state: d.state, at: d.at, lastSync: Date.now() });
            } catch {}
        },
        get shown() {
            const q = this.q.trim().toLowerCase();
            const map = { semua: null, mengerjakan: 'in_progress', belum: 'waiting', selesai: 'submitted', dq: 'disqualified' };
            return this.rows.filter((r) => (!map[this.tab] || r.status === map[this.tab]) && (!q || `${r.name} ${r.nrp} ${r.department || ''} ${r.label || ''}`.toLowerCase().includes(q)));
        },
        num(v) { return v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }); },
        count(t) { return t === 'semua' ? this.rows.length : this.rows.filter((r) => r.status === { mengerjakan: 'in_progress', belum: 'waiting', selesai: 'submitted', dq: 'disqualified' }[t]).length; },
        clock,
        async act(row, action, extra = {}) {
            const ask = { finish: `Kumpulkan lembar ${row.name} sekarang?`, disqualify: `Diskualifikasi ${row.name}? Nilainya tidak masuk peringkat.`, reset: `Hapus percobaan ${row.name}? Jawabannya hilang dan ia bisa mengerjakan ulang.` }[action];
            if (ask && !window.confirm(ask)) return;
            this.busy = row.user_id;
            try {
                const r = await send(cfg.actionUrl.replace('__ID__', row.attempt), { action, ...extra });
                this.flash = r.data?.message || 'Tersimpan.';
                setTimeout(() => (this.flash = ''), 4000);
                await this.refresh();
            } finally { this.busy = null; }
        },
    }));

    /* ---------------- Formulir ujian ---------------- */
    Alpine.data('examForm', (cfg) => ({
        sets: cfg.sets.map((s) => ({ ...s, take: s.take || '' })),
        people: cfg.participants.map((p) => ({ ...p, label: p.label || '' })),
        audience: cfg.audience,
        code: cfg.code,
        duration: cfg.duration,
        setQ: '', q: '', results: [], label: '', paste: false, pasteText: '', pasteMsg: '', listQ: '',
        get chosen() { return this.sets.filter((s) => s.on); },
        get total() { return this.chosen.reduce((n, s) => n + (s.take ? Math.min(Number(s.take), s.count) : s.count), 0); },
        get visibleSets() { const q = this.setQ.trim().toLowerCase(); return this.sets.filter((s) => !q || s.on || s.name.toLowerCase().includes(q)); },
        get visiblePeople() { const q = this.listQ.trim().toLowerCase(); return this.people.filter((p) => !q || `${p.name} ${p.nrp} ${p.department || ''} ${p.label}`.toLowerCase().includes(q)); },
        get perQuestion() { return this.total ? Math.round((this.duration * 60) / this.total) : 0; },
        newCode() {
            const abc = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            const prefix = (this.code.split('-')[0] || 'UJ').slice(0, 6);
            this.code = `${prefix}-${Array.from({ length: 4 }, () => abc[Math.floor(Math.random() * abc.length)]).join('')}`;
        },
        cleanCode() { this.code = this.code.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 30); this.codeTouched = true; },
        codeTouched: cfg.codeTouched,
        // Awalan kode mengikuti singkatan nama ujian selama admin belum mengetik kode sendiri
        fromTitle(title) {
            if (this.codeTouched) return;
            const skip = ['DAN', 'THE', 'UNTUK', 'UJIAN', 'TEST', 'TES'];
            const prefix = title.toUpperCase().normalize('NFD').replace(/[^A-Z0-9 \-_]/g, '').split(/[\s\-_]+/).filter((w) => /^[A-Z]/.test(w) && !skip.includes(w)).map((w) => w[0]).slice(0, 3).join('') || 'UJ';
            this.code = `${prefix}-${this.code.split('-').pop() || 'X'}`;
        },
        async fetchPeople(params) {
            const res = await fetch(`${cfg.peopleUrl}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' } });
            return res.ok ? res.json() : [];
        },
        async search() { this.results = this.q.trim().length < 2 ? [] : await this.fetchPeople({ q: this.q.trim() }); },
        add(p, label = null) {
            const found = this.people.find((r) => r.id === p.id);
            if (found) { if (label) found.label = label; }
            else this.people.unshift({ ...p, label: label ?? this.label });
            this.q = ''; this.results = [];
        },
        remove(p) { this.people = this.people.filter((r) => r.id !== p.id); },
        async addGroup(key, value) { if (!value) return; (await this.fetchPeople({ [key]: value })).forEach((p) => this.add(p)); },
        applyLabel() { if (this.label.trim()) this.people.forEach((p) => { if (!p.label) p.label = this.label.trim(); }); },
        async importPaste() {
            const rows = this.pasteText.split(/\r?\n/).map((l) => l.trim()).filter(Boolean).map((l) => (/[\t;,]/.test(l) ? l.split(/[\t;,]/) : l.split(/\s+/)).map((c) => c.trim()));
            const want = new Map(rows.filter((r) => r[0]).map((r) => [r[0].toUpperCase().replace(/\s/g, ''), r.slice(1).join(' ').trim()]));
            if (!want.size) return;
            const found = await this.fetchPeople({ nrps: [...want.keys()].join(',') });
            found.forEach((p) => { this.add(p, want.get(p.nrp) || null); want.delete(p.nrp); });
            this.pasteMsg = want.size ? `${found.length} ditambahkan. NRP tidak ditemukan: ${[...want.keys()].join(', ')}` : `${found.length} peserta ditambahkan.`;
            if (!want.size) { this.pasteText = ''; this.paste = false; }
        },
    }));

    /* ---------------- Editor soal ---------------- */
    Alpine.data('questionEditor', (cfg) => ({
        options: cfg.options, answer: cfg.answer, preview: cfg.image, removeImage: false,
        letters: ['A', 'B', 'C', 'D', 'E', 'F'],
        addOption() { if (this.options.length < 6) this.options.push(''); this.$nextTick(() => [...this.$root.querySelectorAll('textarea[name^="options"]')].pop()?.focus()); },
        removeOption(i) {
            if (this.options.length <= 2) return;
            const ans = this.letters.indexOf(this.answer);
            this.options.splice(i, 1);
            if (ans === i) this.answer = 'A'; else if (ans > i) this.answer = this.letters[ans - 1];
        },
        pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f); this.removeImage = false; } },
        // Tempel satu soal lengkap dari Word/WA: baris pertama pertanyaan, baris "A. …" opsi
        pasteAll(e) {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
            const opt = lines.filter((l) => /^[A-Fa-f][\.\)]\s+/.test(l));
            if (opt.length < 2) return;
            e.preventDefault();
            const body = lines.filter((l) => !/^[A-Fa-f][\.\)]\s+/.test(l) && !/^(kunci|jawaban)\s*[:=]/i.test(l)).join('\n');
            this.$refs.body.value = body;
            this.options = opt.map((l) => l.replace(/^[A-Fa-f][\.\)]\s+/, ''));
            const key = lines.find((l) => /^(kunci|jawaban)\s*[:=]/i.test(l))?.match(/[:=]\s*\(?([A-Fa-f])/);
            if (key) this.answer = key[1].toUpperCase();
        },
    }));
});
