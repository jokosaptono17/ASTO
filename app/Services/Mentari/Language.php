<?php

namespace App\Services\Mentari;

use App\Models\AppLink;
use App\Models\MentariFaq;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pemahaman bahasa sehari-hari untuk Mentari (tanpa AI):
 * - singkatan & bahasa chat → kata baku ("brp poin sy" → "berapa poin saya"),
 * - huruf berulang ("halooo" → "halo"), reduplikasi angka ("info2" → "info"),
 * - koreksi salah ketik terhadap kosakata AstoOne ("trening" → "training", "abesn" → "absen").
 *
 * Nama orang dan NRP tidak dikoreksi: pencarian nama memakai hasil `lite()` (tanpa koreksi ejaan).
 */
class Language
{
    /** Singkatan / bahasa chat → kata baku (per kata). */
    public const SLANG = [
        'sy' => 'saya', 'sya' => 'saya', 'aq' => 'saya', 'ak' => 'saya', 'gw' => 'saya', 'gue' => 'saya', 'gua' => 'saya', 'aku' => 'saya',
        'km' => 'kamu', 'kmu' => 'kamu', 'lu' => 'kamu', 'lo' => 'kamu', 'loe' => 'kamu',
        'gmn' => 'bagaimana', 'gmna' => 'bagaimana', 'gimana' => 'bagaimana', 'gmana' => 'bagaimana', 'bgmn' => 'bagaimana', 'bgmana' => 'bagaimana', 'gimna' => 'bagaimana',
        'brp' => 'berapa', 'brapa' => 'berapa', 'berpa' => 'berapa', 'brpa' => 'berapa',
        'yg' => 'yang', 'dgn' => 'dengan', 'dg' => 'dengan', 'utk' => 'untuk', 'untk' => 'untuk', 'krn' => 'karena', 'krna' => 'karena',
        'jg' => 'juga', 'sm' => 'sama', 'dr' => 'dari', 'dri' => 'dari', 'ke2' => 'kedua', 'tp' => 'tapi', 'tpi' => 'tapi',
        'tdk' => 'tidak', 'gak' => 'tidak', 'ga' => 'tidak', 'gk' => 'tidak', 'nggak' => 'tidak', 'ngga' => 'tidak', 'enggak' => 'tidak', 'engga' => 'tidak', 'kagak' => 'tidak', 'ndak' => 'tidak',
        'udh' => 'sudah', 'udah' => 'sudah', 'sdh' => 'sudah', 'dah' => 'sudah', 'blm' => 'belum', 'blum' => 'belum', 'belom' => 'belum',
        'bs' => 'bisa', 'bsa' => 'bisa', 'pgn' => 'ingin', 'pengen' => 'ingin', 'pingin' => 'ingin', 'mo' => 'mau',
        'dmn' => 'dimana', 'dmana' => 'dimana', 'kpn' => 'kapan', 'kapn' => 'kapan', 'knp' => 'kenapa', 'knapa' => 'kenapa', 'ngapa' => 'kenapa', 'apaan' => 'apa',
        'skrg' => 'sekarang', 'skrng' => 'sekarang', 'skr' => 'sekarang', 'skarang' => 'sekarang', 'td' => 'tadi', 'bsk' => 'besok', 'besuk' => 'besok',
        'hr' => 'hari', 'tgl' => 'tanggal', 'bln' => 'bulan', 'thn' => 'tahun', 'mgg' => 'minggu',
        'tq' => 'terima kasih', 'thx' => 'terima kasih', 'thanks' => 'terima kasih', 'makasih' => 'terima kasih', 'makasi' => 'terima kasih', 'mksh' => 'terima kasih', 'trims' => 'terima kasih', 'tengkyu' => 'terima kasih', 'trimakasih' => 'terima kasih', 'terimakasih' => 'terima kasih',
        'trening' => 'training', 'traning' => 'training', 'trainning' => 'training', 'trainig' => 'training', 'trainin' => 'training',
        'absent' => 'absen', 'absenn' => 'absen', 'point' => 'poin', 'poen' => 'poin', 'pw' => 'password', 'pass' => 'password', 'paswod' => 'password', 'pasword' => 'password',
        'apk' => 'aplikasi', 'app' => 'aplikasi', 'apps' => 'aplikasi', 'aplikasih' => 'aplikasi', 'aplkasi' => 'aplikasi',
        'info2' => 'info', 'pengumumam' => 'pengumuman', 'pengumumn' => 'pengumuman', 'ujan' => 'ujian', 'ujain' => 'ujian', 'kuis' => 'ujian', 'quiz' => 'ujian',
        'hai' => 'halo', 'hallo' => 'halo', 'helo' => 'halo', 'hi' => 'halo', 'hey' => 'halo', 'assalamualaikum' => 'halo', 'asalamualaikum' => 'halo', 'ass' => 'halo', 'p' => 'halo',
        'min' => 'admin', 'mimin' => 'admin', 'bro' => '', 'kak' => '', 'kk' => '', 'gan' => '', 'dong' => '', 'donk' => '', 'sih' => '', 'nih' => '', 'deh' => '', 'yah' => '', 'ya' => '', 'yaa' => '', 'kah' => '', 'tolong' => '',
    ];

    /** Kosakata AstoOne untuk koreksi salah ketik. */
    private const VOCAB = [
        'absen', 'absensi', 'kehadiran', 'hadir', 'training', 'pelatihan', 'sertifikat', 'poin', 'hadiah', 'kupon', 'tukar', 'katalog', 'saldo', 'level',
        'peringkat', 'ujian', 'nilai', 'kode', 'soal', 'hasil', 'lulus', 'pengumuman', 'info', 'kampanye', 'aplikasi', 'statistik', 'aktivasi', 'gangguan',
        'pasang', 'install', 'lupa', 'password', 'login', 'masuk', 'jadwal', 'sekarang', 'terbaru', 'riwayat', 'profil', 'jabatan', 'seksi', 'lokasi',
        'grade', 'wali', 'atasan', 'mekanik', 'binaan', 'safety', 'talk', 'fatigue', 'tidur', 'pemateri', 'terlambat', 'kompetisi', 'berapa', 'bagaimana',
        'kapan', 'dimana', 'siapa', 'belum', 'sudah', 'saya', 'terima', 'kasih', 'halo', 'pagi', 'siang', 'sore', 'malam', 'cara', 'pakai', 'admin',
        'manual', 'panduan', 'favorit', 'pemindai', 'scan', 'kegiatan', 'event', 'briefing', 'mentari', 'bantuan', 'whatsapp', 'daftar', 'terdaftar',
        'ganti', 'ubah', 'reset', 'kunci', 'jawaban', 'pembahasan', 'peserta', 'nonaktif', 'karyawan', 'departemen', 'program', 'achievement', 'prestasi',
        'data', 'nrp', 'link', 'fungsi', 'kegunaan', 'alamat', 'penjelasan', 'orang', 'pin',
    ];

    /** Kata umum yang benar ejaannya — tidak boleh "dikoreksi" ke kosakata lain (tukang ≠ tukar). */
    private const COMMON = [
        'tukang', 'masih', 'bantu', 'kerja', 'shift', 'unit', 'mesin', 'engine', 'rusak', 'harga', 'tanya', 'jawab', 'minggu', 'bulan', 'tahun', 'hari',
        'besok', 'kemarin', 'kantor', 'workshop', 'servis', 'service', 'perbaikan', 'baru', 'lama', 'banyak', 'sedikit', 'semua', 'setiap', 'tiap', 'kami',
        'kita', 'mereka', 'teman', 'rekan', 'orang', 'nama', 'nomor', 'alamat', 'tempat', 'waktu', 'menit', 'detik', 'besar', 'kecil', 'tinggi', 'rendah',
        'buka', 'tutup', 'mulai', 'selesai', 'kirim', 'terima', 'lihat', 'tampil', 'muncul', 'hilang', 'salah', 'benar', 'bagus', 'jelek', 'cepat', 'lambat',
        'sakit', 'sehat', 'pulang', 'datang', 'pergi', 'makan', 'minum', 'istirahat', 'cuti', 'izin', 'gaji', 'lembur', 'roster', 'mess', 'bus', 'jemputan',
        'hydraulic', 'hidrolik', 'transmisi', 'differential', 'steering', 'brake', 'filter', 'oli', 'grease', 'tyre', 'ban', 'scania', 'komatsu', 'grader',
        'dozer', 'excavator', 'dump', 'truck', 'crusher', 'quarry', 'genset', 'torsi', 'torque', 'baut', 'mur', 'kabel', 'aki', 'baterai', 'sensor', 'error',
        'tolong', 'terus', 'lalu', 'kemudian', 'setelah', 'sebelum', 'kenapa', 'mengapa', 'apakah', 'bisakah', 'gimana', 'seperti', 'contoh', 'kalau',
        'kebaca', 'terbaca', 'baca', 'muncul', 'keluar', 'kerja', 'bekerja', 'boleh', 'harus', 'wajib', 'pakai', 'dipakai', 'beda', 'bedanya',
    ];

    private const STOPWORDS = [
        'yang', 'untuk', 'dengan', 'dan', 'atau', 'di', 'ke', 'dari', 'ini', 'itu', 'ada', 'apa', 'adalah', 'saya', 'kamu', 'bisa', 'mau', 'ingin', 'akan',
        'sudah', 'belum', 'tidak', 'juga', 'sama', 'jadi', 'kalau', 'kalo', 'bila', 'jika', 'agar', 'supaya', 'pada', 'dalam', 'oleh', 'nya', 'lah', 'pun',
        'bagaimana', 'berapa', 'kapan', 'dimana', 'mana', 'siapa', 'kenapa', 'mengapa', 'apakah', 'tolong', 'minta', 'cara', 'mohon', 'para', 'bapak', 'ibu',
        'mentari', 'astoone', 'halo', 'terima', 'kasih', 'aja', 'saja', 'lagi', 'gitu', 'begitu', 'seperti', 'lebih', 'harus', 'perlu', 'boleh', 'tentang',
        'pas', 'sih', 'kok', 'nih', 'tuh', 'deh', 'dong', 'tadi', 'udah',
    ];

    private ?array $vocab = null;

    private array $corrected = [];

    /** Normalisasi lengkap: singkatan + koreksi salah ketik. */
    public function normalize(string $text): string
    {
        return $this->run($text, true);
    }

    /** Normalisasi ringan: singkatan saja, tanpa koreksi ejaan (untuk nama orang). */
    public function lite(string $text): string
    {
        return $this->run($text, false);
    }

    /** Kata bermakna (tanpa kata umum) + bentuk dasar sederhana, untuk pencarian pengetahuan. */
    public function terms(string $text, bool $correct = true): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $correct ? $this->normalize($text) : $this->lite($text), -1, PREG_SPLIT_NO_EMPTY);

        $words = array_filter($words, fn ($w) => ! in_array($w, self::STOPWORDS, true));

        return array_values(array_filter(array_map(fn ($w) => $this->stem($w), $words),
            fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::STOPWORDS, true)));
    }

    /** Pemotong imbuhan sederhana bahasa Indonesia ("pengumumannya" → "pengumuman", "dipakai" → "pakai"). */
    public function stem(string $w): string
    {
        if (mb_strlen($w) <= 4 || preg_match('/\d/', $w)) {
            return $w;
        }
        $w = preg_replace('/(nya|lah|kah|pun)$/u', '', $w);
        if (mb_strlen($w) > 6) {
            $w = preg_replace('/(kan|an)$/u', '', $w);
        }
        if (mb_strlen($w) > 5) {
            $w = preg_replace('/^(mem|men|meng|me|di|ber|ter|ke|se)(?=[a-z]{4,})/u', '', $w);
        }

        return $w;
    }

    private function run(string $text, bool $correct): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/([a-z])\1{2,}/u', '$1', $text);            // halooo → halo
        $text = preg_replace('/\b([a-z]{3,})2\b/u', '$1', $text);          // info2 → info
        $text = preg_replace('/[?!.,;:]+(\s|$)/u', ' ', $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $out = [];
        foreach ($words as $w) {
            if (array_key_exists($w, self::SLANG)) {
                $w = self::SLANG[$w];
            } elseif ($correct) {
                $w = $this->correct($w);
            }
            // "ujiannya" → "ujian nya", "walinya" → "wali nya": kata dasar dikenali bagian lain otak Mentari
            if (strlen($w) > 5 && str_ends_with($w, 'nya') && isset($this->vocab()[$base = substr($w, 0, -3)])) {
                $w = $base.' nya';
            }
            if ($w !== '') {
                $out[] = $w;
            }
        }

        return trim(implode(' ', $out));
    }

    /** Koreksi kata ke kosakata terdekat (jarak ≤1 untuk 4–5 huruf, ≤2 untuk ≥6 huruf, huruf awal sama). */
    private function correct(string $w): string
    {
        if (mb_strlen($w) < 4 || ! preg_match('/^[a-z]+$/', $w)) {
            return $w;
        }

        return $this->corrected[$w] ??= $this->nearest($w);
    }

    private function nearest(string $w): string
    {
        $vocab = $this->vocab();
        if (isset($vocab[$w])) {
            return $w;
        }
        // Bentuk "-nya" dari kata yang dikenal dibiarkan (walinya, caranya)
        if (str_ends_with($w, 'nya') && isset($vocab[substr($w, 0, -3)])) {
            return $w;
        }
        $max = strlen($w) >= 7 ? 2 : 1;
        $best = null;
        $bestDistance = $max + 1;
        foreach ($vocab as $candidate => $_) {
            if ($candidate[0] !== $w[0] || abs(strlen($candidate) - strlen($w)) > $max) {
                continue;
            }
            $d = self::distance($w, $candidate, $max);
            if ($d < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $d];
            }
        }

        return $best ?? $w;
    }

    /** Jarak ejaan Damerau (huruf tertukar dihitung 1: "abesn" → "absen"), berhenti dini bila melebihi batas. */
    public static function distance(string $a, string $b, int $max = 2): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        if (abs($la - $lb) > $max) {
            return $max + 1;
        }
        $d = [];
        for ($i = 0; $i <= $la; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lb; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $la; $i++) {
            $rowMin = PHP_INT_MAX;
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
                $rowMin = min($rowMin, $d[$i][$j]);
            }
            if ($rowMin > $max) {
                return $max + 1;
            }
        }

        return $d[$la][$lb];
    }

    /** Kosakata tetap + kata dari nama aplikasi dan kata kunci FAQ (di-cache 10 menit). */
    private function vocab(): array
    {
        return $this->vocab ??= array_fill_keys(array_merge(self::VOCAB, self::COMMON, Cache::remember('mentari:vocab', 600, function () {
            $words = [];
            foreach (AppLink::query()->pluck('name') as $name) {
                $words = array_merge($words, preg_split('/[^\p{L}]+/u', Str::lower($name), -1, PREG_SPLIT_NO_EMPTY));
            }
            foreach (MentariFaq::where('is_active', true)->pluck('keywords') as $k) {
                $words = array_merge($words, preg_split('/[^\p{L}]+/u', Str::lower($k), -1, PREG_SPLIT_NO_EMPTY));
            }

            return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 4 && preg_match('/^[a-z]+$/', $w))));
        })), true);
    }
}
