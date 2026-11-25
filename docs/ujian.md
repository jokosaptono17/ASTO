# Ujian Event

Pengganti `ppdasto.github.io/soal_ujian` + Apps Script `SYSTEM_SOAL`. Dipakai untuk ujian
kompetisi dan event yang dinamis (Best Mekanik, Best GL, pre/post test training), terpisah dari LMS.

## Alur

1. **Bank soal** (`/admin/ujian/bank`): soal dikelompokkan per **paket**. Impor dari Excel/CSV;
   sheet lama `INTEGRATED_KUMPULAN_SOAL` bisa langsung diunggah (File → Download → .xlsx), sheet
   bernama `Soal` dibaca otomatis dan kolom `KodeUjian` menjadi nama paket. Impor ulang tidak
   menggandakan soal (sidik = teks + gambar + opsi). Tautan gambar GitHub `blob/…?raw=true` diubah ke
   `raw.githubusercontent.com`, tautan Google Drive ke `drive.google.com/thumbnail`.
2. **Event ujian** (`/admin/ujian/baru`): pilih paket (semua atau ambil N acak per paket), jadwal,
   durasi, nilai lulus, kesempatan, batas keluar halaman, mode hasil, lalu **tunjuk peserta**
   (cari nama/NRP, satu seksi, satu jabatan, atau tempel daftar NRP dari Excel) dengan label kelompok.
   - `audience = assigned`: hanya peserta ditunjuk. `open`: siapa pun yang login dan punya kode.
3. **Peserta**: saat membuka beranda, peserta ditunjuk melihat kartu "Ujian untuk Anda" dan pop-up
   pemberitahuan (sekali per sesi login, didahulukan dari pop-up kampanye). Masuk cukup dengan **satu
   kode event** dari pengawas; identitas dari akun login (tidak mengetik nama/NRP).
4. **Pantau** (`/admin/ujian/{id}`): progres langsung (polling 8 dtk), tambah waktu, kumpulkan paksa,
   diskualifikasi, hapus & izinkan ulang, peringkat (seri → waktu tercepat), analisis butir soal
   (soal yang pengecohnya lebih dipilih dari kunci ditandai), hitung ulang nilai, ekspor Excel
   (peringkat + matriks benar/salah per soal), layar proyektor berisi kode besar + QR.

## Aturan keamanan (jangan dilanggar)

- Kunci jawaban **tidak pernah** dikirim ke HP. `ExamService::paper()` hanya mengirim teks soal,
  gambar, dan opsi (kunci opsi asli sudah diacak per peserta di `exam_answers.option_order`).
- Nilai dihitung di server (`ExamService::grade()`); angka nilai dari HP diabaikan.
- Batas waktu dipegang server: `deadline_at = min(mulai + durasi, closes_at)`. Jawaban diterima sampai
  `deadline_at + 20 dtk` (toleransi jaringan). Percobaan lewat waktu ditutup saat halaman mana pun
  yang terkait dibuka (`closeOverdue`), tanpa cron.
- Jawaban tersimpan per soal (autosave) dan antrean offline di `localStorage`; kumpul akhir juga
  membawa semua jawaban. Ujian bisa dilanjutkan setelah HP mati/refresh.
- Keluar halaman (visibilitychange > 1,5 dtk) dicatat; pada pelanggaran ke-`max_violations` ujian
  dikumpulkan otomatis (`finish_reason = violations`). 0 = hanya dicatat.
- Pembahasan (mode `review`) baru terbuka setelah `closes_at` lewat agar tidak bocor ke peserta lain.
- Soal yang sudah pernah dijawab tidak dihapus, hanya dinonaktifkan, agar riwayat nilai utuh.

## Tabel

`question_sets`, `questions` (options JSON `{"A": …}`, `answer`), `exams` (+ `category`, `archived_at`,
`deleted_at`/`deleted_by` sejak PR #16), `exam_question_set`
(pivot `take`), `exam_participants` (`label`, `notified_at`), `exam_attempts`, `exam_answers`.

## Hak akses

Gate `manage-exams` = Administrator & Admin SHE.

## Ruang ujian: lanjut otomatis

Setelah peserta memilih jawaban, soal berikutnya terbuka otomatis setelah jeda 450 ms (garis neon di
pilihan menandai jedanya). Di soal terakhir: pindah ke soal kosong pertama, atau membuka konfirmasi
kumpul bila semua sudah terjawab. Menekan "Ragu-ragu" selama jeda membatalkan perpindahan. Peserta
bisa mematikannya lewat sakelar "Lanjut otomatis" (tersimpan di HP).

## Admin → Event ujian (PR #16): dashboard & pengelolaan jangka panjang

Halaman `/admin/ujian` dirancang agar tetap ringan walau event sudah ratusan.

**Bagian atas**
- *Sedang berlangsung*: event yang dibuka saat ini, jumlah yang mengerjakan/selesai, tombol Pantau & Layar.
- *Ringkasan* (periode 30 hari / 90 hari / tahun ini / semua; bisa disembunyikan, tersimpan di browser):
  jumlah event per status, lembar selesai & karyawan berbeda, rata-rata nilai & tingkat lulus, kehadiran peserta
  ditunjuk (event selesai, peserta yang tidak mengerjakan), grafik lembar selesai 12 bulan, tabel per kategori.
- *Perlu perhatian* — tiap butir membuka daftar yang sudah tersaring (`?cek=`):
  `tanpa-soal` (terbit tanpa paket soal → peserta tidak bisa mulai), `tanpa-peserta`, `draf-lama`
  (draf tanpa hasil, tidak diubah 14 hari), `siap-arsip` (selesai > 30 hari); plus isi Tempat sampah.
- *Berikutnya*: 4 event terjadwal terdekat.

**Daftar**
- Tab: Semua · Berlangsung · Terjadwal · Draf · Selesai · Arsip · Tempat sampah (angka mengikuti saringan).
- Status disaring di database lewat `Exam::scopeInState()` (setara `state()`; ada tes yang menjaga keduanya sama).
- Cari judul/kode/kategori, saring kategori (`-` = tanpa kategori) & tahun (`coalesce(opens_at, created_at)`),
  urutkan (terbaru, jadwal, judul, peserta, nilai), 20/50/100 per halaman.
- **Rekap** (`GET admin/ujian/rekap`): Excel daftar event sesuai saringan aktif.
- Pilih banyak (centang) → bilah aksi: Arsipkan / Keluarkan / Hapus / Pulihkan / Hapus permanen.
  Semua lewat `POST admin/ujian/massal` (`action`, `ids[]` maks 200, `confirm`).

**Siklus hidup event**
| Aksi | Syarat | Efek |
|---|---|---|
| Arsipkan | status draf atau selesai | hilang dari daftar utama; hasil tetap utuh, bisa dibuka & diekspor dari tab Arsip |
| Hapus (Tempat sampah) | tidak ada peserta yang sedang mengerjakan | soft delete + `deleted_by`; hilang dari portal peserta (riwayat, hasil, kode, Mentari); bisa dipulihkan |
| Pulihkan | di Tempat sampah | kembali seperti semula |
| Hapus permanen | di Tempat sampah + ketik `HAPUS` | event, peserta, lembar jawaban hilang selamanya (FK cascade) |
| Otomatis | 30 hari di Tempat sampah (`Exam::TRASH_DAYS`) | `ExamService::purgeTrash()` — dipanggil saat halaman dibuka + jadwal harian `purge-exam-trash` |

Catatan: kode ujian di Tempat sampah tetap terkunci (indeks unik); pulihkan atau hapus permanen dulu untuk memakainya lagi.
Bank soal tidak ikut terhapus — soal milik paket, bukan milik event. Semua aksi tercatat di `activity_logs` (`exam.*`).
