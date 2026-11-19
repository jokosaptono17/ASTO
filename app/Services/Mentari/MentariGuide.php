<?php

namespace App\Services\Mentari;

use App\Models\AppLink;
use App\Models\MentariFaq;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Otak bawaan Mentari untuk AstoOne. Mengenali maksud dengan kata kunci,
 * mencari aplikasi yang cocok, dan memakai FAQ yang diisi admin.
 * Dipakai bila layanan AI belum tersambung, lambat, atau gagal.
 *
 * Jawaban: ['reply' => string, 'actions' => [['label', 'href'|'ask', 'external'?]],
 *          'cards' => [...], 'intent' => string]
 * Kartu (orang, daftar, angka) dibuat oleh PeopleDesk.
 */
class MentariGuide
{
    public const CHIPS = [
        'guest' => ['Lupa PIN', 'NRP saya belum terdaftar', 'Cara pasang aplikasi', 'Apa itu AstoOne?'],
        'user' => ['Data saya', 'Absensi saya', 'Training saya', 'Info terbaru', 'Apa itu Monata?'],
        'gl' => ['Siapa yang belum absen?', 'Mekanik asuh saya', 'Absensi saya', 'Training saya', 'Info terbaru'],
        'admin' => ['Statistik portal', 'Siapa yang belum absen?', 'Siapa yang belum aktivasi?', 'Aplikasi yang gangguan', 'Kampanye paling banyak diklik'],
    ];

    private const STOPWORDS = ['yang', 'untuk', 'dengan', 'dimana', 'mana', 'buka', 'bukakan', 'tolong', 'mau', 'ingin', 'saya', 'aku', 'aplikasi', 'app', 'apps', 'cari', 'carikan', 'link', 'tautan', 'web', 'website', 'halaman', 'gimana', 'bagaimana', 'cara', 'apa', 'ada', 'tidak', 'bisa', 'minta', 'dong', 'nya', 'itu', 'ini', 'kak', 'min', 'mentari', 'portal', 'astoone', 'sistem', 'system'];

    /** Intent yang boleh diserahkan ke AI bila MENTARI_ENDPOINT diatur. */
    public const AI_INTENTS = ['fallback', 'greeting', 'identity', 'thanks'];

    public function __construct(private PeopleDesk $people, private AttendanceDesk $attendance) {}

    public static function chipsFor(?User $user): array
    {
        return match (true) {
            ! $user => self::CHIPS['guest'],
            $user->isAdmin() => self::CHIPS['admin'],
            $user->canHostAttendance() || $user->mentees()->exists() => self::CHIPS['gl'],
            default => self::CHIPS['user'],
        };
    }

    public function answer(string $message, array $context, ?User $viewer = null): array
    {
        $q = Str::lower(trim(preg_replace('/\s+/', ' ', $message)));
        $q = rtrim($q, " ?!.");

        if (! $viewer) {
            foreach (['account', 'install', 'faq', 'identity', 'greeting'] as $step) {
                if ($answer = $this->{$step}($q, $context)) {
                    return $this->finish($answer, $step);
                }
            }

            return $this->finish($this->fallback($message, true), 'fallback');
        }

        $steps = [
            'appKnowledge' => fn () => $this->appKnowledge($q),
            'self' => fn () => $this->people->self($q, $viewer),
            'attendance' => fn () => $this->attendance->answer($q, $viewer),
            'nrp' => fn () => $this->people->byNrp($message, $q, $viewer),
            'account' => fn () => $this->account($q, $context),
            'install' => fn () => $this->install($q),
            'stats' => fn () => $this->people->stats($q, $viewer),
            'announcements' => fn () => $this->announcements($q, $context),
            'campaigns' => fn () => $this->campaigns($q, $context),
            'faq' => fn () => $this->faq($q),
            'findApp' => fn () => $this->findApp($q, $context),
            'name' => fn () => $this->people->byName($q, $viewer),
            'identity' => fn () => $this->identity($q),
            'greeting' => fn () => $this->greeting($q, $context),
            'thanks' => fn () => $this->thanks($q),
        ];
        foreach ($steps as $intent => $step) {
            if ($answer = $step()) {
                return $this->finish($answer, $answer['intent'] ?? $intent);
            }
        }

        return $this->finish($this->fallback($message, false), 'fallback');
    }

    private function finish(array $answer, string $intent): array
    {
        return ['reply' => $answer['reply'], 'actions' => $answer['actions'] ?? [], 'cards' => $answer['cards'] ?? [], 'intent' => $intent];
    }

    /* ------------------------------------------------ pengetahuan aplikasi */

    /**
     * "apa itu monata", "cara login monata", "siapa admin monata".
     * Hanya menjawab bila nama aplikasinya jelas disebut.
     */
    private function appKnowledge(string $q): ?array
    {
        $kind = match (true) {
            (bool) preg_match('/\b(siapa|kontak|hubungi|nomor)\b.*\b(admin|pic|pengelola|penanggung ?jawab|pj|pembuat)\b|\b(admin|pic|pengelola)\b/u', $q) => 'pic',
            (bool) preg_match('/\b(cara|bagaimana|gimana|langkah|tutorial|panduan)\b/u', $q) => 'howto',
            (bool) preg_match('/\b(apa itu|apa sih|apakah itu|fungsi|kegunaan|guna|untuk apa|buat apa|jelaskan|penjelasan|itu apa|maksud)\b/u', $q) => 'what',
            default => null,
        };
        if (! $kind) {
            return null;
        }
        $app = $this->matchApp($q);
        if (! $app) {
            return null;
        }

        $open = ['label' => 'Buka '.$app->name, 'href' => route('apps.go', $app)];
        $pic = $app->picWhatsapp("Halo {$app->pic_name}, saya mau tanya tentang {$app->name}.");
        $picAction = $pic ? ['label' => 'Chat '.($app->pic_name ?: 'PIC '.$app->name), 'href' => $pic, 'external' => true] : null;

        return match ($kind) {
            'what' => $this->reply(
                ($app->description ?: ($app->tagline ? "{$app->name}: {$app->tagline}." : "{$app->name} adalah salah satu aplikasi Plant ASTO."))
                    .($app->category ? "\nKategori: {$app->category->name}." : ''),
                array_filter([$open, $app->howto ? ['label' => 'Cara pakai', 'ask' => 'cara pakai '.$app->name] : null]),
            ),
            'howto' => $this->reply(
                $app->howto ? "Cara pakai {$app->name}:\n{$app->howto}" : "Panduan cara pakai {$app->name} belum diisi admin."
                    .($app->pic_name ? " Tanyakan ke PIC-nya, {$app->pic_name}." : ' Tanyakan ke admin AstoOne.'),
                array_filter([$open, $app->howto ? null : ($picAction ?? $this->wa("Halo Admin AstoOne, bagaimana cara pakai {$app->name}?"))]),
            ),
            'pic' => $this->reply(
                $app->pic_name ? "PIC / admin {$app->name}: {$app->pic_name}." : "PIC {$app->name} belum dicatat. Untuk sementara hubungi admin AstoOne.",
                array_filter([$picAction ?? $this->wa("Halo Admin AstoOne, siapa PIC {$app->name}?"), $open]),
            ),
        };
    }

    /** Aplikasi yang namanya disebut jelas di pertanyaan. */
    private function matchApp(string $q): ?AppLink
    {
        $best = null;
        $bestScore = 0;
        foreach (AppLink::active()->with('category')->get() as $app) {
            $name = Str::lower($app->name);
            $score = 0;
            if (str_contains($q, $name)) {
                $score = 100 + mb_strlen($name);
            } else {
                foreach (preg_split('/\s+/', $name) as $word) {
                    if (mb_strlen($word) >= 4 && preg_match('/(^|\s)'.preg_quote($word, '/').'/u', $q)) {
                        $score += 10 + mb_strlen($word);
                    }
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$app, $score];
            }
        }

        return $best;
    }

    /* ---------------------------------------------------------------- akun */

    private function account(string $q, array $c): ?array
    {
        if ($this->has($q, ['belum terdaftar', 'tidak terdaftar', 'tdk terdaftar', 'nrp tidak ditemukan', 'nrp salah', 'belum punya akun', 'daftar akun'])) {
            return $this->reply('Data NRP AstoOne dikelola admin. Kalau NRP Anda belum terdaftar, kirim nama lengkap, NRP, dan departemen ke admin lewat WhatsApp — biasanya langsung didaftarkan.', [
                $this->wa('Halo Admin AstoOne, NRP saya belum terdaftar. Nama: … NRP: … Departemen: …'),
            ]);
        }

        if (! $this->has($q, ['lupa pin', 'reset pin', 'ganti pin', 'ubah pin', 'pin salah', 'pin', 'tidak bisa masuk', 'gagal masuk', 'lupa password', 'password', 'login', 'terkunci'])) {
            return null;
        }

        if (($c['role'] ?? 'guest') !== 'guest' && $this->has($q, ['ganti', 'ubah'])) {
            return $this->reply('PIN bisa Anda ganti sendiri di menu Profil → Ganti PIN. Masukkan PIN lama, lalu PIN baru 6 angka dua kali.', [
                ['label' => 'Buka Profil', 'href' => route('profile')],
            ]);
        }

        return $this->reply('Kalau lupa PIN, admin bisa meresetnya. Setelah direset, masuk lagi dengan NRP lalu buat PIN baru (cukup sekali). Ketuk tombol di bawah untuk chat admin — sebutkan nama dan NRP Anda.', [
            $this->wa('Halo Admin AstoOne, saya lupa PIN / tidak bisa masuk. Nama: … NRP: …'),
        ]);
    }

    private function install(string $q): ?array
    {
        if (! $this->has($q, ['pasang', 'install', 'instal', 'apk', 'layar utama', 'download', 'unduh', 'homescreen'])) {
            return null;
        }

        return $this->reply("AstoOne bisa dipasang tanpa Play Store:\n• Android: buka di Chrome → menu ⋮ → \"Instal aplikasi\" (atau tombol Pasang di Profil).\n• iPhone: Safari → Bagikan → \"Tambah ke Layar Utama\".\nSetelah terpasang, AstoOne terbuka layar penuh seperti aplikasi biasa.");
    }

    /* ---------------------------------------------------------- isi portal */

    private function announcements(string $q, array $c): ?array
    {
        if (! $this->has($q, ['info', 'pengumuman', 'berita', 'terbaru', 'kabar', 'update', 'notifikasi'])) {
            return null;
        }
        $items = $c['announcements'] ?? [];
        if (! $items) {
            return $this->reply('Belum ada pengumuman aktif saat ini. Saya kabari lewat lonceng di beranda kalau ada yang baru.');
        }
        $lines = collect($items)->take(3)->map(fn ($a) => '• '.$a['title'].($a['level'] !== 'info' ? ' ('.Str::upper($a['level']).')' : ''))->join("\n");

        return $this->reply("Pengumuman terbaru:\n".$lines, [
            ['label' => 'Buka semua info', 'href' => route('info.index')],
        ]);
    }

    private function campaigns(string $q, array $c): ?array
    {
        if (! $this->has($q, ['kampanye', 'campaign', 'promo', 'event', 'acara', 'program'])) {
            return null;
        }
        $items = $c['campaigns'] ?? [];
        if (! $items) {
            return $this->reply('Belum ada kampanye yang sedang tayang.');
        }

        return $this->reply('Kampanye yang sedang tayang: '.collect($items)->pluck('title')->join(', ').'.', collect($items)->take(2)->map(fn ($k) => ['label' => Str::limit($k['title'], 40), 'href' => $k['url']])->all());
    }

    private function faq(string $q): ?array
    {
        $best = null;
        $bestScore = 0;
        foreach (MentariFaq::where('is_active', true)->get() as $faq) {
            $score = 0;
            foreach ($faq->keywordList() as $keyword) {
                if ($keyword !== '' && $this->has($q, [$keyword])) {
                    $score += mb_strlen($keyword);
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$faq, $score];
            }
        }
        if (! $best) {
            return null;
        }
        $best->increment('hits');
        $actions = $best->link_url ? [['label' => $best->link_label ?: 'Buka', 'href' => $best->link_url, 'external' => str_starts_with($best->link_url, 'http')]] : [];

        return $this->reply($best->answer, $actions);
    }

    private function findApp(string $q, array $c): ?array
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $q))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOPWORDS, true))
            ->values();
        if ($tokens->isEmpty()) {
            return null;
        }

        $scored = collect($c['apps'] ?? [])->map(function ($app) use ($tokens) {
            $name = Str::lower($app['name']);
            $haystack = Str::lower($app['name'].' '.$app['tagline'].' '.$app['category']);
            $score = 0;
            foreach ($tokens as $t) {
                if (preg_match('/(^|\s)'.preg_quote($t, '/').'/u', $name)) {
                    $score += 4; // awal kata pada nama: "shop" → Shop Manual
                } elseif (str_contains($name, $t)) {
                    $score += 2; // di tengah kata: "shop" → Workshop
                } elseif (str_contains($haystack, $t)) {
                    $score += 1;
                }
            }

            return $app + ['score' => $score];
        })->filter(fn ($a) => $a['score'] > 0)->sortByDesc('score')->values();

        if ($scored->isEmpty()) {
            return null;
        }
        // Hanya tampilkan yang skornya mendekati kecocokan terbaik
        $best = $scored->first()['score'];
        $scored = $scored->filter(fn ($a) => $a['score'] >= $best * 0.6)->values();

        $top = $scored->take(3);
        $first = $top->first();
        $text = $top->count() === 1
            ? "Ini dia: {$first['name']}".($first['tagline'] ? " — {$first['tagline']}" : '').'.'
            : 'Saya menemukan beberapa aplikasi yang cocok:';
        if ($top->count() === 1 && ($desc = AppLink::find($first['id'])?->description)) {
            $text = "Ini dia {$first['name']}. {$desc}";
        }
        if (($first['status'] ?? null) === 'down') {
            $text .= "\nCatatan: {$first['name']} sedang tidak merespons saat terakhir dicek.";
        }

        return $this->reply($text, $top->map(fn ($a) => ['label' => 'Buka '.$a['name'], 'href' => $a['url']])->all());
    }

    /* --------------------------------------------------------------- umum */

    private function identity(string $q): ?array
    {
        if (! $this->has($q, ['siapa kamu', 'kamu siapa', 'apa itu mentari', 'robot', 'astoone', 'apa itu', 'fungsi portal'])) {
            return null;
        }

        return $this->reply("Saya Mentari, robot mekanik asisten Plant ASTO. AstoOne adalah pintu masuk ke semua aplikasi Plant ASTO — cukup sekali masuk dengan NRP.\nCoba tanya: \"apa itu Monata?\", \"training saya\", atau ketik NRP rekan kerja untuk melihat datanya.");
    }

    private function greeting(string $q, array $c): ?array
    {
        if (! $this->has($q, ['halo', 'hai', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'hello', 'permisi'])) {
            return null;
        }
        $name = $c['first_name'] ?? null;

        return $this->reply(($c['greeting'] ?? 'Halo').($name ? ", $name" : '').'! Saya Mentari. '.(($c['role'] ?? 'guest') === 'guest'
            ? 'Saya bisa bantu soal masuk dengan NRP, PIN, atau cara memasang AstoOne.'
            : 'Sebutkan aplikasi atau keperluan Anda — misalnya "shop manual", "training", atau "info terbaru".'));
    }

    private function thanks(string $q): ?array
    {
        if (! $this->has($q, ['terima kasih', 'makasih', 'thanks', 'thank you', 'tengkyu', 'mantap', 'siap'])) {
            return null;
        }

        return $this->reply('Sama-sama! Tetap safety dan semangat berkarya 💚');
    }

    private function fallback(string $message, bool $guest): array
    {
        return $this->reply($guest
            ? 'Maaf, saya belum bisa menjawab itu. Untuk kendala masuk akun, admin siap membantu lewat WhatsApp.'
            : 'Maaf, saya belum menemukan jawabannya. Coba sebut nama aplikasinya, atau tanyakan langsung ke admin.', [
                $this->wa('Halo Admin AstoOne, saya butuh bantuan: '.Str::limit($message, 300)),
            ]);
    }

    /* ------------------------------------------------------------- bantu */

    private function has(string $q, array $keys): bool
    {
        foreach ($keys as $key) {
            $key = Str::lower($key);
            if (mb_strlen($key) <= 3) {
                if (preg_match('/(^|[^\p{L}\p{N}])'.preg_quote($key, '/').'($|[^\p{L}\p{N}])/u', $q)) {
                    return true;
                }
            } elseif (str_contains($q, $key)) {
                return true;
            }
        }

        return false;
    }

    private function reply(string $text, array $actions = []): array
    {
        return ['reply' => $text, 'actions' => array_values($actions)];
    }

    private function wa(string $text): array
    {
        return ['label' => 'Chat admin di WhatsApp', 'href' => Setting::whatsappLink($text), 'external' => true];
    }
}
