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

`question_sets`, `questions` (options JSON `{"A": …}`, `answer`), `exams`, `exam_question_set`
(pivot `take`), `exam_participants` (`label`, `notified_at`), `exam_attempts`, `exam_answers`.

## Hak akses

Gate `manage-exams` = Administrator & Admin SHE.

## Ruang ujian: lanjut otomatis

Setelah peserta memilih jawaban, soal berikutnya terbuka otomatis setelah jeda 450 ms (garis neon di
pilihan menandai jedanya). Di soal terakhir: pindah ke soal kosong pertama, atau membuka konfirmasi
kumpul bila semua sudah terjawab. Menekan "Ragu-ragu" selama jeda membatalkan perpindahan. Peserta
bisa mematikannya lewat sakelar "Lanjut otomatis" (tersimpan di HP).
