<?php

namespace App\Services\Mentari;

use App\Models\Announcement;
use App\Models\AppLink;
use App\Models\Campaign;
use App\Models\MentariFaq;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pengetahuan Mentari: panduan bawaan AstoOne + FAQ admin + penjelasan aplikasi + pengumuman + kampanye.
 * Pencarian BM25 (seperti mesin pencari) di atas kata yang sudah dinormalisasi `Language`,
 * dipakai untuk menjawab langsung bila cocok kuat, dan sebagai bahan rujukan AI.
 */
class KnowledgeBase
{
    /** Panduan bawaan: selalu ada walau admin belum mengisi FAQ. */
    public const GUIDES = [
        ['Cara absen P5M, safety talk, dan event', 'Scan QR absensi di lokasi (atau buka menu Absen) → AstoOne mengecek posisi GPS → pilih kegiatan yang sedang dibuka → pilih Audience atau Pemateri → isi jam tidur dan jam bangun. Satu QR berlaku untuk semua kegiatan. Jadwal rutin: P5M shift 1 pukul 05:30–07:00, shift 2 pukul 17:30–19:00, safety talk Sabtu 06:00–08:00.', 'attendance.scan', 'Buka pemindai', 'absen absensi hadir p5m safety talk scan qr kehadiran kegiatan'],
        ['Status fatigue dari jam tidur', 'Setelah absen, AstoOne menghitung lama tidur: 6 jam atau lebih = Fit to Work; 5 sampai 6 jam = Coaching atasan; kurang dari 5 jam = Fatigue risk (segera lapor atasan). Isi jam tidur dengan jujur — jam tidur tidak memengaruhi poin.', 'attendance.history', 'Riwayat absensi', 'fatigue lelah ngantuk tidur jam tidur fit to work coaching risk istirahat'],
        ['Lokasi tidak terverifikasi atau absen ditolak karena di luar area', 'Absen hanya diterima di area kerja yang dipetakan (WS 25, Quarry, Crusher). Bila GPS lemah atau izin lokasi ditolak, absen tetap tercatat tetapi ditandai "lokasi belum terverifikasi" untuk diperiksa GL. Bila pasti di luar area, absen ditolak. Nyalakan GPS (mode akurasi tinggi), izinkan lokasi untuk browser, lalu coba lagi di area terbuka.', 'attendance.scan', 'Coba absen lagi', 'gps lokasi area luar ditolak tidak terverifikasi sinyal izin lokasi geofence kebaca terbaca terdeteksi akurasi'],
        ['Kenapa absen ditolak karena HP sudah dipakai', 'Satu HP hanya bisa dipakai satu NRP di sesi yang sama, untuk mencegah titip absen. Gunakan HP Anda sendiri. Bila HP Anda rusak, minta GL/host mencatat kehadiran secara manual.', null, null, 'hp sudah dipakai perangkat titip absen ditolak handphone satu hp'],
        ['Poin HEBAT: cara mendapat poin', 'Poin HEBAT adalah poin reward AstoOne (tidak bisa dibeli, dicairkan, atau ditransfer). Cara dapat poin (aturan bawaan, bisa diubah admin): hadir P5M/safety talk/event +5, menjadi pemateri +20, hadir 7 hari beruntun +25, serta poin event dari admin. Level naik dari total poin: Rookie → Skilled (250) → Expert (1.000) → Master (3.000) → Legend (7.500).', 'points.index', 'Dompet poin', 'poin hebat dapat cara mengumpulkan level rookie skilled expert master legend reward'],
        ['Menukar poin dengan hadiah', 'Buka Poin → Katalog hadiah → pilih hadiah → konfirmasi dengan PIN. Anda mendapat kupon QR; tunjukkan ke admin bersama ID card untuk mengambil hadiah. Kupon yang dibatalkan admin mengembalikan poin.', 'points.rewards', 'Katalog hadiah', 'tukar hadiah reward kupon qr katalog redeem ambil hadiah'],
        ['Ujian event (Best Mekanik, Best GL, pre/post test)', 'Bila Anda ditunjuk sebagai peserta, ujian muncul di beranda. Saat ujian dimulai, masukkan kode dari pengawas — nama dan NRP otomatis dari akun. Jawaban tersimpan otomatis; bila HP mati atau sinyal hilang, buka lagi dan lanjutkan. Waktu tetap berjalan. Jangan keluar dari halaman ujian: setiap keluar halaman tercatat, dan pada batas tertentu ujian dikumpulkan otomatis. Setelah memilih jawaban, soal berikutnya terbuka otomatis (bisa dimatikan).', 'exams.index', 'Ujian saya', 'ujian event kompetisi best mekanik gl pre test post test soal kode pengawas nilai lulus waktu habis'],
        ['Lupa PIN atau tidak bisa masuk', 'Admin bisa mereset PIN. Setelah direset, masuk lagi dengan NRP lalu buat PIN baru. Bila salah PIN 5 kali, akun terkunci 5 menit. Hubungi admin lewat tombol WhatsApp dan sebutkan nama serta NRP.', null, null, 'lupa pin tidak bisa masuk login gagal terkunci reset pin password'],
        ['Mengganti PIN', 'Buka Profil → Ganti PIN, masukkan PIN lama lalu PIN baru 6 angka dua kali. Hindari angka berurutan atau angka yang sama semua.', 'profile', 'Buka Profil', 'ganti pin ubah pin pin baru keamanan akun'],
        ['Memasang AstoOne di HP', 'Android: buka plantasto.my.id di Chrome → menu ⋮ → Instal aplikasi. iPhone: Safari → Bagikan → Tambah ke Layar Utama. Setelah terpasang, AstoOne terbuka layar penuh seperti aplikasi biasa.', null, null, 'pasang install aplikasi hp android iphone layar utama download unduh pwa'],
        ['Masuk MekanikPintar dengan akun AstoOne', 'Di halaman masuk MekanikPintar pilih "Masuk dengan AstoOne", lalu setujui di layar AstoOne. AstoOne hanya mengirim NRP dan nama; PIN tidak pernah dibagikan.', null, null, 'mekanikpintar lms masuk login sso akun astoone training online'],
        ['Mencari aplikasi dan favorit', 'Ketik nama atau keperluan di kolom cari beranda (di komputer tekan /). Ketuk ikon bintang untuk menjadikan aplikasi favorit agar muncul paling atas.', 'apps.index', 'Semua aplikasi', 'cari aplikasi favorit bintang semua aplikasi daftar aplikasi'],
        ['Data saya salah (grade, GL wali, seksi, jabatan)', 'Data karyawan di AstoOne diimpor admin dari data manpower. Bila ada yang salah, kirim koreksi ke admin lewat WhatsApp.', 'profile', 'Lihat profil', 'data salah koreksi grade gl wali seksi jabatan lokasi program profil ubah data'],
        ['Pengumuman dan lonceng', 'Pengumuman penting tampil di pita beranda dan menu Info. Titik pada lonceng menandakan ada pengumuman baru yang belum Anda buka.', 'info.index', 'Buka Info', 'pengumuman info berita lonceng notifikasi terbaru'],
        ['Mentari bisa apa saja', 'Mentari bisa: mencari aplikasi, menjelaskan aplikasi, menampilkan data karyawan dari NRP atau nama, poin dan hadiah, absensi, ujian, riwayat training (bila tersambung LMS), pengumuman, dan menjawab pertanyaan umum. Pertanyaan lanjutan seperti "training dia?" juga dipahami.', null, null, 'mentari bisa apa fitur bantuan asisten robot'],
    ];

    private const K1 = 1.2;

    private const B = 0.75;

    public function __construct(private Language $lang) {}

    /**
     * @return list<array{doc:array,score:float,coverage:float}>
     */
    public function search(string $query, int $limit = 4): array
    {
        $terms = array_values(array_unique($this->lang->terms($query)));
        if (! $terms) {
            return [];
        }
        $index = $this->index();
        $n = max(1, count($index['docs']));
        $hits = [];
        foreach ($index['docs'] as $i => $doc) {
            $score = 0.0;
            $matched = 0;
            foreach ($terms as $t) {
                $tf = $doc['tf'][$t] ?? 0;
                if (! $tf) {
                    continue;
                }
                $matched++;
                $df = $index['df'][$t] ?? 1;
                $idf = log(1 + ($n - $df + 0.5) / ($df + 0.5));
                $score += $idf * ($tf * (self::K1 + 1)) / ($tf + self::K1 * (1 - self::B + self::B * $doc['len'] / $index['avg']));
            }
            if ($matched) {
                $hits[] = ['doc' => $doc, 'score' => round($score, 3), 'coverage' => $matched / count($terms)];
            }
        }
        usort($hits, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($hits, 0, $limit);
    }

    /** Jawaban langsung bila kecocokan kuat (≥ 2 kata penting cocok, mayoritas kata tertangkap). */
    public function answer(string $query): ?array
    {
        $hits = $this->search($query, 2);
        $best = $hits[0] ?? null;
        $terms = count(array_unique($this->lang->terms($query)));
        $strong = $best && ($best['coverage'] >= 0.6 || ($terms >= 4 && $best['coverage'] >= 0.5 && $best['score'] >= 4));
        if (! $best || $terms < 2 || ! $strong || $best['score'] < 2.5) {
            return null;
        }
        $doc = $best['doc'];
        $actions = $doc['href'] ? [['label' => $doc['label'] ?: 'Buka', 'href' => $doc['href'], 'external' => str_starts_with($doc['href'], 'http')]] : [];
        if ($doc['kind'] === 'faq') {
            MentariFaq::whereKey((int) Str::after($doc['id'], ':'))->increment('hits');
        }
        $text = match ($doc['kind']) {
            'info' => "Pengumuman \"{$doc['title']}\":\n".Str::limit($doc['body'], 500),
            'kampanye' => "Kampanye \"{$doc['title']}\": ".Str::limit($doc['body'], 400),
            'aplikasi' => Str::limit($doc['body'], 700),
            default => Str::limit($doc['body'], 700),
        };

        return ['reply' => $text, 'actions' => $actions, 'cards' => [], 'intent' => 'pengetahuan', 'title' => $doc['title']];
    }

    /** Ringkasan rujukan untuk AI (tanpa data pribadi). */
    public function context(string $query, int $limit = 4): string
    {
        return collect($this->search($query, $limit))
            ->filter(fn ($h) => $h['score'] >= 0.8)
            ->values()
            ->map(fn ($h, $i) => '['.($i + 1).'] '.$h['doc']['title'].': '.Str::limit($h['doc']['body'], 600).($h['doc']['href'] ? ' (tautan: '.$h['doc']['href'].')' : ''))
            ->implode("\n");
    }

    public function documentCount(): int
    {
        return count($this->index()['docs']);
    }

    /** Indeks di-cache 5 menit; dibangun ulang otomatis setelah FAQ/aplikasi diubah (cache:forget mentari:kb). */
    private function index(): array
    {
        return Cache::remember('mentari:kb', 300, function () {
            $docs = [];
            foreach (self::GUIDES as $i => [$title, $body, $route, $label, $keywords]) {
                $docs[] = $this->doc('panduan:'.$i, 'panduan', $title, $body, $route ? route($route, [], false) : null, $label, $keywords);
            }
            foreach (MentariFaq::where('is_active', true)->get() as $faq) {
                $docs[] = $this->doc('faq:'.$faq->id, 'faq', $faq->question, $faq->answer, $faq->link_url, $faq->link_label, $faq->keywords);
            }
            foreach (AppLink::active()->with('category')->get() as $app) {
                $body = trim(($app->description ?: ($app->tagline ? "{$app->name}: {$app->tagline}." : "{$app->name} adalah aplikasi Plant ASTO."))
                    .($app->howto ? "\nCara pakai: {$app->howto}" : '')
                    .($app->pic_name ? "\nPIC: {$app->pic_name}." : ''));
                $docs[] = $this->doc('aplikasi:'.$app->id, 'aplikasi', $app->name, $body, route('apps.go', $app, false), 'Buka '.$app->name, $app->name.' '.$app->tagline.' '.$app->category?->name);
            }
            foreach (Announcement::live()->take(15)->get() as $a) {
                $docs[] = $this->doc('info:'.$a->id, 'info', $a->title, trim(strip_tags((string) $a->body)), route('info.show', $a, false), 'Baca pengumuman', $a->level);
            }
            foreach (Campaign::live()->take(10)->get() as $c) {
                $docs[] = $this->doc('kampanye:'.$c->id, 'kampanye', $c->title, trim($c->subtitle ?: $c->title), route('campaigns.open', $c, false), 'Lihat kampanye', '');
            }
            $df = [];
            foreach ($docs as $d) {
                foreach (array_keys($d['tf']) as $t) {
                    $df[$t] = ($df[$t] ?? 0) + 1;
                }
            }

            return ['docs' => $docs, 'df' => $df, 'avg' => max(1, array_sum(array_column($docs, 'len')) / max(1, count($docs)))];
        });
    }

    private function doc(string $id, string $kind, string $title, string $body, ?string $href, ?string $label, ?string $keywords): array
    {
        // Judul & kata kunci diberi bobot lebih (diulang)
        $t = fn ($text) => $this->lang->terms((string) $text, false);
        $terms = array_merge($t($title), $t($title), $t($keywords), $t($keywords), $t($body));

        return [
            'id' => $id, 'kind' => $kind, 'title' => $title, 'body' => $body, 'href' => $href, 'label' => $label,
            'tf' => array_count_values($terms), 'len' => count($terms),
        ];
    }
}
