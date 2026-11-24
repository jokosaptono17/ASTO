<?php

namespace App\Services\Mentari\Ai;

use App\Models\User;
use App\Services\Mentari\KnowledgeBase;
use App\Services\Mentari\MentariGuide;
use App\Services\Mentari\PeopleDesk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Otak berpikir" Mentari: model AI gratis yang memahami pertanyaan bebas lalu MENGAMBIL DATA lewat alat
 * (tool calling) ke otak bawaan — jadi angka, nama, jadwal selalu dari database AstoOne, bukan karangan AI.
 * Aturan lihat data tetap ditegakkan oleh MentariGuide/PeopleDesk. Penyedia yang tidak "personal"
 * (data bisa dipakai melatih model) tidak menerima data karyawan sama sekali.
 */
class Agent
{
    private const MAX_ROUNDS = 3;

    private const PUBLIC_TOOLS = ['cari_aplikasi', 'info_aplikasi', 'pengumuman_terbaru', 'katalog_hadiah', 'cari_pengetahuan'];

    private array $cards = [];

    private array $actions = [];

    private array $used = [];

    public function __construct(
        private Providers $providers,
        private LlmClient $client,
        private MentariGuide $guide,
        private KnowledgeBase $kb,
    ) {}

    public function available(): bool
    {
        return $this->providers->ready() !== [];
    }

    /**
     * @return array{reply:string, actions:array, cards:array, intent:string, provider:string}|null
     */
    public function think(string $message, array $history, User $viewer, array $context): ?array
    {
        $cacheKey = $history ? null : 'mentari:ai:ans:'.md5(Str::lower(trim($message)).'|'.$viewer->role);
        if ($cacheKey && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        foreach ($this->providers->ready() as $provider) {
            $this->cards = $this->actions = $this->used = [];
            try {
                $reply = $this->run($provider, $message, $history, $viewer, $context);
            } catch (LlmError $e) {
                $this->handle($provider['name'], $e);

                continue;
            }
            $this->providers->markOk($provider['name']);
            $answer = [
                'reply' => $reply,
                'actions' => array_slice($this->unique($this->actions), 0, 3),
                'cards' => array_slice($this->cards, 0, 2),
                'intent' => 'ai',
                'provider' => $provider['name'],
            ];
            // Jawaban umum (tanpa data pribadi) disimpan 6 jam agar hemat kuota
            if ($cacheKey && array_diff($this->used, self::PUBLIC_TOOLS) === []) {
                Cache::put($cacheKey, $answer, now()->addHours(6));
            }

            return $answer;
        }

        return null;
    }

    private function run(array $provider, string $message, array $history, User $viewer, array $context): string
    {
        $personal = (bool) $provider['personal'];
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($viewer, $context, $personal, $message)]],
            $this->history($history, $personal),
            [['role' => 'user', 'content' => $personal ? $message : $this->redact($message)]],
        );
        $tools = $this->tools($viewer, $personal);
        $reasoning = true;
        $fallbackTried = false;

        for ($round = 0; $round <= self::MAX_ROUNDS; $round++) {
            try {
                $this->providers->consume($provider['name']);
                $res = $this->client->chat($provider, $messages, $round < self::MAX_ROUNDS ? $tools : [], $reasoning);
            } catch (LlmError $e) {
                // Model menolak format alat/parameter → coba sekali lagi tanpa alat (rujukan pengetahuan tetap ada)
                if (in_array($e->status, [400, 422], true) && ! $fallbackTried) {
                    $fallbackTried = true;
                    $tools = [];
                    $reasoning = false;
                    $round--;

                    continue;
                }
                throw $e;
            }

            $calls = array_slice($res['tool_calls'] ?? [], 0, 4);
            if ($calls && $round < self::MAX_ROUNDS) {
                $messages[] = ['role' => 'assistant', 'content' => $res['content'], 'tool_calls' => $calls];
                foreach ($calls as $call) {
                    $name = (string) ($call['function']['name'] ?? '');
                    $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true) ?: [];
                    $messages[] = [
                        'role' => 'tool', 'tool_call_id' => (string) ($call['id'] ?? $name), 'name' => $name,
                        'content' => $this->runTool($name, is_array($args) ? $args : [], $viewer, $context, $personal),
                    ];
                }

                continue;
            }

            $text = $this->clean((string) $res['content']);
            if ($text === '') {
                throw new LlmError(0, 'Jawaban kosong');
            }

            return $text;
        }

        throw new LlmError(0, 'Terlalu banyak langkah alat');
    }

    private function runTool(string $name, array $args, User $viewer, array $context, bool $personal): string
    {
        if (! $personal && ! in_array($name, self::PUBLIC_TOOLS, true)) {
            return 'Alat ini tidak tersedia.';
        }
        $this->used[] = $name;
        if ($name === 'cari_pengetahuan') {
            $found = $this->kb->context((string) ($args['pertanyaan'] ?? ''), 3);

            return $found !== '' ? $found : 'Tidak ada pengetahuan yang cocok.';
        }
        $answer = $this->guide->tool($name, $args, $viewer, $context);
        if (! $answer) {
            return 'Tidak ditemukan.';
        }
        foreach ($answer['cards'] ?? [] as $card) {
            if ($personal) {
                $this->cards[] = $card;
            }
        }
        foreach ($answer['actions'] ?? [] as $action) {
            $this->actions[] = $action;
        }

        $text = $this->describe($answer);

        return $personal ? $text : $this->redact($text);
    }

    /** Jawaban otak bawaan → teks ringkas untuk AI. No HP tidak pernah dikirim. */
    private function describe(array $answer): string
    {
        $lines = [trim((string) $answer['reply'])];
        foreach ($answer['cards'] ?? [] as $card) {
            if (($card['type'] ?? '') === 'person') {
                $fields = collect(array_merge($card['fields'] ?? [], $card['admin'] ?? []))
                    ->reject(fn ($f) => $f['label'] === 'No HP')
                    ->map(fn ($f) => $f['label'].': '.$f['value'])->implode('; ');
                $lms = isset($card['lms']) ? "; poin LMS {$card['lms']['points']}, training {$card['lms']['trainings']}, sertifikat {$card['lms']['certificates']}".($card['lms']['last'] ? ", terakhir: {$card['lms']['last']}" : '') : '';
                $lines[] = "Karyawan {$card['name']} (NRP {$card['nrp']})".($fields ? ': '.$fields : '').$lms;
            } elseif (($card['type'] ?? '') === 'stats') {
                $lines[] = ($card['title'] ? $card['title'].': ' : '').collect($card['items'])->map(fn ($i) => $i['label'].' '.$i['value'])->implode(', ');
            } elseif (($card['type'] ?? '') === 'list') {
                $lines[] = ($card['title'] ? $card['title'].":\n" : '').collect($card['items'])->take(12)
                    ->map(fn ($i) => '- '.$i['title'].(! empty($i['subtitle']) ? ' — '.$i['subtitle'] : '').(! empty($i['meta']) ? ' ('.$i['meta'].')' : ''))->implode("\n");
            }
        }

        return mb_substr(implode("\n", array_filter($lines)), 0, 2500);
    }

    private function systemPrompt(User $viewer, array $context, bool $personal, string $message): string
    {
        $who = $personal
            ? "Pengguna: {$viewer->firstName()} — peran {$viewer->roleLabel()}".($viewer->position ? ", {$viewer->position}" : '').($viewer->department ? ", seksi {$viewer->department}" : '').'.'
            : "Pengguna: karyawan Plant ASTO dengan peran {$viewer->roleLabel()}.";
        $knowledge = $this->kb->context($message, 4);

        return implode("\n", array_filter([
            'Kamu Mentari, robot mekanik asisten di AstoOne — portal aplikasi Dept. Plant jobsite ASTO, PT Kalimantan Prima Persada (tambang). Penggunanya mekanik, Group Leader (GL), tim SHE, dan admin.',
            "Waktu sekarang: {$context['today_label']} pukul ".Str::after($context['now'] ?? '', ' ').' WIB/WITA setempat.',
            $who,
            'Aturan:',
            '- Jawab dalam Bahasa Indonesia yang ramah, jelas, dan singkat (umumnya ≤ 120 kata). Teks polos; boleh daftar dengan "•". Tanpa tabel, tanpa judul markdown.',
            '- Untuk fakta tentang AstoOne, karyawan, poin, absensi, ujian, training, aplikasi, pengumuman: AMBIL lewat alat atau rujukan di bawah. Jangan pernah mengarang angka, nama, NRP, jadwal, nilai, atau tautan.',
            '- Bila data tidak ada atau alat bilang tidak ditemukan/tidak boleh, katakan terus terang dan sarankan menghubungi admin AstoOne lewat WhatsApp.',
            '- Pertanyaan teknis alat berat atau umum boleh dijawab dari pengetahuan umum dengan hati-hati: sebut itu panduan umum, rujuk shop manual/OEM dan instruktur, dan jangan memberi angka spesifikasi/torsi yang tidak pasti.',
            '- Keselamatan kerja nomor satu: ingatkan LOTO, APD, dan prosedur K3 bila relevan; tolak permintaan yang berbahaya atau melanggar aturan.',
            '- Jangan pernah memberikan kode ujian, kunci jawaban, PIN, password, atau nomor HP.',
            '- Kamu tidak bisa melakukan tindakan (absen, tukar poin, mengubah data). Arahkan ke menu yang tepat; tombol tautan akan ditampilkan otomatis dari hasil alat.',
            '- Jangan menyebut nama alat/fungsi internal kepada pengguna.',
            $knowledge !== '' ? "Rujukan dari pengetahuan AstoOne (pakai bila relevan):\n".$knowledge : null,
        ]));
    }

    /** Definisi alat (format OpenAI). Penyedia non-personal hanya mendapat alat umum. */
    private function tools(User $viewer, bool $personal): array
    {
        $fn = fn (string $name, string $desc, array $props = [], array $required = []) => ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $desc,
            'parameters' => ['type' => 'object', 'properties' => (object) $props] + ($required ? ['required' => $required] : []),
        ]];
        $text = fn (string $desc) => ['type' => 'string', 'description' => $desc];

        $tools = [
            $fn('cari_aplikasi', 'Cari aplikasi di portal AstoOne berdasarkan keperluan atau nama (mis. "shop manual", "tyre", "monata").', ['kata_kunci' => $text('kata kunci keperluan atau nama aplikasi')], ['kata_kunci']),
            $fn('info_aplikasi', 'Penjelasan, cara pakai, PIC, dan status satu aplikasi yang namanya diketahui.', ['nama' => $text('nama aplikasi')], ['nama']),
            $fn('pengumuman_terbaru', 'Daftar pengumuman yang sedang berlaku di AstoOne.'),
            $fn('katalog_hadiah', 'Daftar hadiah Poin HEBAT beserta harga poin dan stok.'),
            $fn('cari_pengetahuan', 'Cari di panduan AstoOne dan FAQ admin (absensi, fatigue, poin, ujian, PIN, pasang aplikasi, dll.).', ['pertanyaan' => $text('pertanyaan atau topik')], ['pertanyaan']),
        ];
        if ($personal) {
            array_push($tools,
                $fn('poin_saya', 'Saldo, level, dan transaksi terakhir Poin HEBAT milik pengguna.'),
                $fn('absensi_saya', 'Ringkasan dan riwayat absensi pengguna bulan ini.'),
                $fn('absensi_dibuka', 'Sesi absensi (P5M, safety talk, event) yang sedang dibuka untuk pengguna saat ini.'),
                $fn('ujian_saya', 'Ujian event yang ditunjuk untuk pengguna dan statusnya.'),
                $fn('nilai_ujian_saya', 'Hasil/nilai ujian pengguna yang sudah dikumpulkan.'),
                $fn('data_karyawan', 'Data dasar karyawan dari NRP (format seperti KT16042) atau nama: seksi, jabatan, grade, GL wali, lokasi.', ['nrp_atau_nama' => $text('NRP atau nama karyawan')], ['nrp_atau_nama']),
                $fn('riwayat_training', 'Riwayat training/sertifikat dari MekanikPintar. Tanpa argumen = milik pengguna sendiri. Hanya boleh untuk diri sendiri, mekanik asuh, atau oleh admin.', ['nrp' => $text('NRP karyawan (opsional)')]),
            );
            if ($viewer->isAdmin()) {
                $tools[] = $fn('statistik_portal', 'Statistik AstoOne untuk admin: pengguna, aktivasi, kunjungan, kampanye.');
            }
        }

        return $tools;
    }

    /** Riwayat percakapan. Untuk penyedia non-personal hanya pertanyaan pengguna yang dikirim, NRP disamarkan. */
    private function history(array $history, bool $personal): array
    {
        return collect($history)->take(-6)
            ->filter(fn ($h) => in_array($h['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($h['content'] ?? '')) !== '')
            ->when(! $personal, fn ($c) => $c->where('role', 'user'))
            ->map(fn ($h) => ['role' => $h['role'], 'content' => mb_substr($personal ? $this->stripPhones($h['content']) : $this->redact($h['content']), 0, 600)])
            ->values()->all();
    }

    private function redact(string $text): string
    {
        return $this->stripPhones(preg_replace(PeopleDesk::NRP_PATTERN.'i', '[NRP]', $text));
    }

    private function stripPhones(string $text): string
    {
        return preg_replace('/(\+?62|0)8\d[\d\s\-]{6,12}/', '[nomor]', $text);
    }

    /** Rapikan markdown agar tampil baik di gelembung chat. */
    private function clean(string $text): string
    {
        $text = preg_replace('/<think>.*?<\/think>/s', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\((\/[^)\s]*|https?:\/\/[^)\s]+)\)/', '$1', $text);
        $text = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $text);
        $text = preg_replace('/^\s*[-*]\s+/m', '• ', $text);
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

        return mb_substr($text, 0, 1800);
    }

    private function unique(array $actions): array
    {
        return collect($actions)->unique(fn ($a) => ($a['href'] ?? $a['ask'] ?? '').'|'.$a['label'])->values()->all();
    }

    private function handle(string $provider, LlmError $e): void
    {
        [$seconds, $reason] = match (true) {
            $e->status === 429 => [max(600, $e->retryAfter), 'Kuota/batas permintaan tercapai'],
            in_array($e->status, [401, 403], true) => [3600, 'Kunci API ditolak — periksa .env'],
            $e->status === 404 => [3600, 'Model tidak ditemukan — periksa nama model di .env'],
            $e->status >= 500 => [180, 'Server penyedia sedang gangguan'],
            $e->status === 0 => [120, $e->getMessage()],
            default => [300, 'Permintaan ditolak ('.$e->status.')'],
        };
        $this->providers->coolDown($provider, $seconds, $reason.': '.mb_substr($e->getMessage(), 0, 120));
        Log::warning("Mentari AI {$provider} gagal ({$e->status}): ".mb_substr($e->getMessage(), 0, 200));
    }
}
