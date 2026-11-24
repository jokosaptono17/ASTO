<?php

namespace App\Services\Mentari;

use App\Models\AppLink;
use App\Models\AppVisit;
use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\User;
use App\Services\LmsDirectory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Bagian otak Mentari yang menjawab soal orang dan angka:
 * kartu karyawan dari NRP/nama, data saya, mekanik asuh, riwayat training
 * (dari LMS), dan statistik untuk admin.
 *
 * Aturan lihat data:
 * - Semua yang login: nama, NRP, seksi, jabatan, grade, GL wali, lokasi, program.
 * - Riwayat training/poin/sertifikat: orangnya sendiri, GL walinya, dan Administrator.
 * - No HP, status akun, login terakhir: hanya Administrator.
 */
class PeopleDesk
{
    public const NRP_PATTERN = '/\b([A-Z]{2,3}\d{5})\b/';

    private const TRAINING_WORDS = ['training', 'pelatihan', 'sertifikat', 'poin', 'point', 'nilai', 'riwayat', 'achievement', 'prestasi'];

    public function __construct(private LmsDirectory $lms) {}

    /** Pertanyaan tentang diri sendiri. */
    public function self(string $q, User $viewer): ?array
    {
        if ($this->has($q, ['anak asuh', 'mekanik asuh', 'binaan', 'asuhan saya', 'mekanik saya', 'tim saya', 'anggota saya'])) {
            return $this->mentees($viewer, $viewer);
        }
        $aboutMe = $this->has($q, ['saya', 'aku', 'ku ']) || str_ends_with($q, 'ku');
        if ($aboutMe && $this->has($q, self::TRAINING_WORDS)) {
            return $this->training($viewer, $viewer);
        }
        if ($aboutMe && $this->has($q, ['profil saya', 'data saya', 'grade saya', 'gl wali', 'wali saya', 'atasan saya', 'siapa saya', 'nrp saya', 'jabatan saya', 'lokasi saya', 'seksi saya', 'section saya'])) {
            $text = match (true) {
                $this->has($q, ['wali', 'atasan']) => $viewer->supervisor
                    ? 'GL wali Anda: '.Str::title(Str::lower($viewer->supervisor)).'.'
                    : 'GL wali Anda belum tercatat di AstoOne. Minta admin melengkapinya.',
                $this->has($q, ['grade']) => $viewer->grade ? "Grade Anda: {$viewer->grade}." : 'Grade Anda belum tercatat di AstoOne.',
                default => 'Ini data Anda di AstoOne:',
            };

            return $this->answer($text, [$this->personCard($viewer, $viewer)], $this->personActions($viewer, $viewer));
        }

        return null;
    }

    /** NRP yang disebut di pertanyaan → kartu (atau riwayat training bila diminta). */
    public function byNrp(string $raw, string $q, User $viewer): ?array
    {
        if (! preg_match_all(self::NRP_PATTERN, Str::upper($raw), $m)) {
            return null;
        }
        $nrps = array_slice(array_unique($m[1]), 0, 3);
        $people = User::whereIn('nrp', $nrps)->get()->keyBy('nrp');
        $missing = array_values(array_diff($nrps, $people->keys()->all()));

        if ($people->count() === 1 && $this->has($q, self::TRAINING_WORDS)) {
            return $this->training($people->first(), $viewer);
        }
        if ($people->count() === 1 && $this->has($q, ['anak asuh', 'mekanik asuh', 'binaan'])) {
            return $this->mentees($people->first(), $viewer);
        }
        if ($people->isEmpty()) {
            return $this->answer('NRP '.implode(', ', $missing).' tidak ditemukan di AstoOne. Periksa penulisannya, atau minta admin mendaftarkannya.');
        }

        $text = $people->count() === 1 ? ($this->fieldAnswer($people->first(), $q) ?? 'Ini data '.$people->first()->displayName().':') : 'Ini data karyawan yang Anda cari:';
        if ($missing) {
            $text .= "\n(NRP ".implode(', ', $missing).' tidak ditemukan.)';
        }

        return $this->answer(
            $text,
            $people->map(fn ($u) => $this->personCard($u, $viewer))->values()->all(),
            $people->count() === 1 ? $this->personActions($people->first(), $viewer) : [],
        );
    }

    /** "siapa ahmad", "cari budi santoso" → cari nama karyawan. */
    public function byName(string $q, User $viewer): ?array
    {
        if (! preg_match('/^(?:siapa(?:kah)?|cari(?:kan)?|profil|data|nrp(?:nya)?)\s+(?:itu\s+|nama\s+|karyawan\s+|mekanik\s+|pak\s+|bapak\s+|mas\s+|bu\s+)*(.+)$/u', $q, $m)) {
            return null;
        }
        $tokens = collect(preg_split('/[^\p{L}]+/u', Str::upper($m[1])))->filter(fn ($t) => mb_strlen($t) >= 3)->values();
        if ($tokens->isEmpty()) {
            return null;
        }
        $query = User::where('is_active', true);
        foreach ($tokens as $t) {
            $query->where('name', 'like', '%'.$t.'%');
        }
        $found = $query->orderBy('name')->limit(9)->get();
        if ($found->isEmpty()) {
            return null;
        }
        if ($found->count() === 1) {
            $u = $found->first();

            return $this->answer('Ini data '.$u->displayName().':', [$this->personCard($u, $viewer)], $this->personActions($u, $viewer));
        }

        return $this->answer(
            'Ada '.($found->count() > 8 ? 'lebih dari 8' : $found->count()).' karyawan yang cocok. Ketuk salah satu:',
            [$this->listCard(null, $found->take(8)->map(fn ($u) => [
                'title' => $u->displayName(),
                'subtitle' => $u->nrp.' · '.($u->department ?: '–'),
                'ask' => $u->nrp,
            ])->all())],
        );
    }

    /** Statistik untuk admin, dan status aplikasi untuk semua. */
    public function stats(string $q, User $viewer): ?array
    {
        if ($this->has($q, ['gangguan', 'down', 'offline', 'error', 'tidak bisa dibuka', 'mati']) && $this->has($q, ['aplikasi', 'app', 'sistem', 'web', 'mana'])) {
            $down = AppLink::active()->where('health_check', true)->where('health_status', 'down')->ordered()->get();

            return $down->isEmpty()
                ? $this->answer('Semua aplikasi yang dipantau sedang online saat terakhir dicek 👍')
                : $this->answer('Aplikasi yang tidak merespons saat terakhir dicek:', [$this->listCard(null, $down->map(fn ($a) => [
                    'title' => $a->name, 'subtitle' => 'Dicek '.($a->health_checked_at?->locale('id')->diffForHumans() ?? '–'), 'tone' => 'alarm',
                ])->all())]);
        }

        if (! $viewer->canManageContent()) {
            return null;
        }

        if ($this->has($q, ['kampanye', 'campaign', 'poster']) && $this->has($q, ['klik', 'terbanyak', 'populer', 'statistik', 'paling'])) {
            $rows = Campaign::orderByDesc('clicks')->limit(6)->get();

            return $this->answer('Kampanye dengan klik terbanyak:', [$this->listCard(null, $rows->map(fn ($c) => [
                'title' => $c->title, 'subtitle' => ucfirst($c->status()), 'meta' => number_format($c->clicks, 0, ',', '.').' klik',
            ])->all())], [['label' => 'Kelola kampanye', 'href' => route('admin.campaigns.index')]]);
        }

        if (! $viewer->isAdmin()) {
            return null;
        }

        if ($this->has($q, ['aktivasi', 'aktifasi', 'belum login', 'belum masuk', 'belum daftar'])) {
            $section = $this->mentionedDepartment($q);
            $base = User::where('is_active', true)->when($section, fn ($w) => $w->where('department', $section));
            $total = (clone $base)->count();
            $done = (clone $base)->whereNotNull('pin')->count();
            $cards = [$this->statsCard([
                ['label' => 'Karyawan aktif', 'value' => $total],
                ['label' => 'Sudah aktivasi', 'value' => $done],
                ['label' => 'Belum', 'value' => $total - $done],
            ], $section ? 'Seksi '.$section : null)];

            if ($this->has($q, ['siapa', 'daftar', 'list', 'nama'])) {
                $pending = (clone $base)->whereNull('pin')->orderBy('department')->orderBy('name')->limit(20)->get();
                $cards[] = $this->listCard('Belum aktivasi'.($total - $done > 20 ? ' (20 pertama)' : ''), $pending->map(fn ($u) => [
                    'title' => $u->displayName(), 'subtitle' => $u->nrp.' · '.($u->department ?: '–'), 'ask' => $u->nrp,
                ])->all());
            }
            $pct = $total ? round($done / $total * 100) : 0;

            return $this->answer("{$pct}% karyawan".($section ? " seksi {$section}" : '').' sudah aktivasi.', $cards, [
                ['label' => 'Buka menu Pengguna', 'href' => route('admin.users.index', ['status' => 'belum'])],
            ]);
        }

        if ($this->has($q, ['aplikasi', 'app']) && $this->has($q, ['populer', 'terbanyak', 'sering', 'paling'])) {
            $top = AppVisit::where('created_at', '>=', now()->subDays(30))
                ->selectRaw('app_id, COUNT(*) as total')->groupBy('app_id')->orderByDesc('total')->limit(6)->with('app')->get()
                ->filter(fn ($v) => $v->app);

            return $this->answer('Aplikasi paling sering dibuka 30 hari terakhir:', [$this->listCard(null, $top->map(fn ($v) => [
                'title' => $v->app->name, 'meta' => $v->total.'×',
            ])->values()->all())]);
        }

        if ($this->has($q, ['statistik', 'ringkasan', 'rekap', 'laporan portal', 'dashboard'])) {
            $since = now()->subDays(7);

            return $this->answer('Ringkasan AstoOne 7 hari terakhir:', [$this->statsCard([
                ['label' => 'Akun aktif', 'value' => User::whereNotNull('pin')->where('is_active', true)->count()],
                ['label' => 'Masuk 7 hari', 'value' => User::where('last_login_at', '>=', $since)->count()],
                ['label' => 'Aplikasi dibuka', 'value' => AppVisit::where('created_at', '>=', $since)->count()],
                ['label' => 'Klik kampanye', 'value' => CampaignClick::where('created_at', '>=', $since)->count()],
            ])], [['label' => 'Buka panel admin', 'href' => route('admin.dashboard')]]);
        }

        return null;
    }

    /* ------------------------------------------------------------ bagian */

    public function training(User $target, User $viewer): array
    {
        $isSelf = $target->is($viewer);
        if (! $this->canSeeDetail($target, $viewer)) {
            return $this->answer('Riwayat training hanya bisa dilihat oleh yang bersangkutan, GL walinya, dan admin.', [$this->personCard($target, $viewer)]);
        }
        if (! $this->lms->enabled()) {
            return $this->answer('Data training dari MekanikPintar belum tersambung ke AstoOne. Sementara ini, cek langsung di MekanikPintar.', [], $this->lmsAppAction());
        }
        $s = $this->lms->summary($target->nrp);
        if (! $s) {
            return $this->answer('MekanikPintar sedang tidak bisa dibaca. Coba lagi beberapa saat lagi.', [], $this->lmsAppAction());
        }
        if (! ($s['found'] ?? false)) {
            return $this->answer(($isSelf ? 'NRP Anda' : 'NRP '.$target->nrp).' belum terdaftar di MekanikPintar.', [], $this->lmsAppAction());
        }

        $cards = [$this->statsCard([
            ['label' => 'Poin', 'value' => $s['points']],
            ['label' => 'Training', 'value' => $s['training_count']],
            ['label' => 'Sertifikat', 'value' => $s['certificate_count']],
        ], ($isSelf ? 'Anda' : $target->displayName()).($s['grade'] ? ' · '.$s['grade'] : ''))];

        if ($s['trainings']) {
            $cards[] = $this->listCard('Training terakhir', collect($s['trainings'])->take(6)->map(fn ($t) => [
                'title' => $t['name'],
                'subtitle' => $t['date'] ? \Illuminate\Support\Carbon::parse($t['date'])->locale('id')->translatedFormat('j M Y') : null,
                'meta' => $t['passed'] === false ? 'Belum lulus' : null,
                'tone' => $t['passed'] === false ? 'warn' : null,
            ])->all());
        }
        if ($s['certificates']) {
            $cards[] = $this->listCard('Sertifikat', collect($s['certificates'])->take(5)->map(fn ($c) => [
                'title' => $c['name'],
                'subtitle' => trim(($c['number'] ?: '').($c['date'] ? ' · '.\Illuminate\Support\Carbon::parse($c['date'])->locale('id')->translatedFormat('j M Y') : ''), ' ·'),
            ])->all());
        }
        if ($s['achievements']) {
            $cards[] = $this->listCard('Achievement', collect($s['achievements'])->take(6)->map(fn ($a) => ['title' => $a])->all());
        }

        $intro = $s['training_count']
            ? ($isSelf ? 'Riwayat training Anda' : 'Riwayat training '.$target->displayName()).' dari MekanikPintar:'
            : ($isSelf ? 'Anda' : $target->displayName()).' belum punya catatan training di MekanikPintar.';

        return $this->answer($intro, $cards, $this->lmsAppAction());
    }

    public function mentees(User $gl, User $viewer): array
    {
        if (! $gl->is($viewer) && ! $viewer->isAdmin()) {
            return $this->answer('Daftar mekanik asuh hanya bisa dilihat oleh GL wali yang bersangkutan dan admin.');
        }
        $list = $gl->mentees()->where('is_active', true)->orderBy('department')->orderBy('name')->get();
        if ($list->isEmpty()) {
            return $this->answer(($gl->is($viewer) ? 'Belum ada mekanik yang tercatat dengan Anda sebagai GL wali.' : 'Belum ada mekanik yang tercatat dengan GL wali '.$gl->displayName().'.'));
        }

        return $this->answer(
            ($gl->is($viewer) ? 'Mekanik asuh Anda' : 'Mekanik asuh '.$gl->displayName()).": {$list->count()} orang. Ketuk nama untuk melihat datanya.",
            [$this->listCard(null, $list->map(fn ($u) => [
                'title' => $u->displayName(),
                'subtitle' => $u->nrp.' · '.($u->department ?: '–'),
                'meta' => $u->grade,
                'ask' => $u->nrp,
            ])->all())],
        );
    }

    public function personCard(User $u, User $viewer): array
    {
        $card = [
            'type' => 'person',
            'nrp' => $u->nrp,
            'name' => $u->displayName(),
            'initials' => $u->initials(),
            'role' => $u->role !== 'user' ? $u->roleLabel() : null,
            'fields' => array_values(array_filter([
                ['label' => 'Seksi', 'value' => $u->department],
                ['label' => 'Jabatan', 'value' => $u->position],
                ['label' => 'Grade', 'value' => $u->grade && ! str_contains((string) $u->position, $u->grade) ? $u->grade : null],
                ['label' => 'GL wali', 'value' => $u->supervisor ? Str::title(Str::lower($u->supervisor)) : null],
                ['label' => 'Lokasi', 'value' => $u->location],
                ['label' => 'Program', 'value' => $u->program],
            ], fn ($f) => filled($f['value']))),
        ];

        if ($viewer->isAdmin()) {
            $card['admin'] = array_values(array_filter([
                ['label' => 'Akun', 'value' => ! $u->is_active ? 'Nonaktif' : ($u->isActivated() ? 'Sudah aktivasi' : 'Belum aktivasi')],
                ['label' => 'Masuk terakhir', 'value' => $u->last_login_at?->locale('id')->diffForHumans() ?? 'Belum pernah'],
                ['label' => 'No HP', 'value' => $u->phone],
            ], fn ($f) => filled($f['value'])));
            $card['status'] = ! $u->is_active ? 'nonaktif' : ($u->isActivated() ? 'aktif' : 'belum');
        }

        if ($this->canSeeDetail($u, $viewer) && $this->lms->enabled() && ($s = $this->lms->summary($u->nrp)) && ($s['found'] ?? false)) {
            $card['lms'] = [
                'points' => $s['points'],
                'trainings' => $s['training_count'],
                'certificates' => $s['certificate_count'],
                'last' => $s['trainings'][0]['name'] ?? null,
            ];
        }

        return $card;
    }

    public function personActions(User $u, User $viewer): array
    {
        $actions = [];
        if ($this->canSeeDetail($u, $viewer) && $this->lms->enabled()) {
            $actions[] = ['label' => 'Riwayat training', 'ask' => 'training '.$u->nrp];
        }
        if (($u->is($viewer) || $viewer->isAdmin()) && $u->mentees()->exists()) {
            $actions[] = ['label' => 'Mekanik asuh', 'ask' => 'anak asuh '.$u->nrp];
        }
        if ($viewer->isAdmin()) {
            $actions[] = ['label' => 'Kelola akun', 'href' => route('admin.users.edit', $u)];
            if ($u->phone && ($wa = $this->waLink($u->phone))) {
                $actions[] = ['label' => 'WhatsApp', 'href' => $wa, 'external' => true];
            }
        }

        return array_slice($actions, 0, 3);
    }

    /** "GL walinya siapa?", "seksinya apa?" → jawab langsung field yang ditanya. */
    private function fieldAnswer(User $u, string $q): ?string
    {
        $name = $u->displayName();
        $field = match (true) {
            $this->has($q, ['wali', 'atasan']) => ['GL wali', $u->supervisor ? Str::title(Str::lower($u->supervisor)) : null],
            $this->has($q, ['seksi', 'section', 'departemen']) => ['Seksi', $u->department],
            $this->has($q, ['jabatan', 'posisi']) => ['Jabatan', $u->position],
            $this->has($q, ['grade']) => ['Grade', $u->grade],
            $this->has($q, ['lokasi', 'area kerja']) => ['Lokasi', $u->location],
            default => null,
        };
        if (! $field) {
            return null;
        }

        return $field[1] ? "{$field[0]} {$name}: {$field[1]}." : "{$field[0]} {$name} belum tercatat di AstoOne.";
    }

    public function canSeeDetail(User $target, User $viewer): bool
    {
        return $target->is($viewer) || $viewer->isAdmin() || ($target->supervisor_nrp && $target->supervisor_nrp === $viewer->nrp);
    }

    /* ------------------------------------------------------------- bantu */

    private function mentionedDepartment(string $q): ?string
    {
        return User::whereNotNull('department')->distinct()->pluck('department')
            ->sortByDesc(fn ($d) => mb_strlen($d))
            ->first(fn ($d) => str_contains($q, Str::lower($d)));
    }

    private function lmsAppAction(): array
    {
        $app = AppLink::active()->where('url', 'like', '%mekanikpintar.com%')
            ->where('url', 'not like', '%://%.mekanikpintar.com%')->first();

        return $app ? [['label' => 'Buka '.$app->name, 'href' => route('apps.go', $app)]] : [];
    }

    private function waLink(string $phone): ?string
    {
        $n = preg_replace('/\D+/', '', explode(' ', trim($phone))[0]);

        return $n ? 'https://wa.me/'.(str_starts_with($n, '0') ? '62'.substr($n, 1) : $n) : null;
    }

    private function statsCard(array $items, ?string $title = null): array
    {
        return ['type' => 'stats', 'title' => $title, 'items' => $items];
    }

    private function listCard(?string $title, array $items): array
    {
        return ['type' => 'list', 'title' => $title, 'items' => array_values($items)];
    }

    private function answer(string $text, array $cards = [], array $actions = []): array
    {
        return ['reply' => $text, 'actions' => array_values($actions), 'cards' => array_values($cards), 'intent' => 'people'];
    }

    private function has(string $q, array $keys): bool
    {
        foreach ($keys as $key) {
            if (str_contains($q, $key)) {
                return true;
            }
        }

        return false;
    }
}
