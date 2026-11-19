# Absensi AstoOne — P5M, Safety Talk, Safety Alert

Pengganti form absensi P5M lama (Absensi-Karyawan-noapi). Bedanya: berbasis **sesi**, identitas
diambil dari akun (tidak bisa absen atas nama orang lain), dan **QR/kode berganti tiap 30 detik**
sehingga foto QR yang dikirim ke teman di luar lokasi cepat kedaluwarsa.

## Peran

| Peran | Bisa |
| --- | --- |
| Karyawan | Scan QR / ketik kode layar, isi peran & jam tidur, lihat riwayat sendiri |
| **GL / Host absensi** (`gl`) | + buat & pimpin sesi sendiri, layar QR, catat manual, rekap sesi miliknya |
| Admin SHE | + semua sesi & rekap, kampanye, pengumuman |
| Administrator | + kelola jenis kegiatan & semua menu |

Jadikan GL sebagai host: Admin → Pengguna → ubah peran menjadi **GL / Host absensi**.

## Alur host

1. Panel → **Absensi → Sesi baru**: pilih jenis (P5M, Safety Talk, Safety Alert), topik, jam mulai,
   lokasi, **seksi peserta** (untuk daftar "belum hadir"), dan aturan:
   - *Wajib scan QR di lokasi* (disarankan) atau *Cukup dari aplikasi* (tombol absen di beranda).
   - Dibuka X menit sebelum mulai, terlambat setelah Y menit, ditutup Z menit setelah mulai.
2. Buka **Layar QR** di TV / laptop / HP host. Layar menampilkan QR + kode 6 angka, hitungan hadir,
   terlambat, belum hadir, dan nama yang baru absen (diperbarui tiap 4 detik).
3. Halaman sesi: daftar hadir live (filter terlambat / perlu perhatian / pemateri), **belum hadir**
   (tombol *Hadir* dan *Salin untuk WA*), **catat manual** untuk peserta tanpa HP, tutup / buka lagi,
   ekspor Excel.

## Alur karyawan

1. Scan QR dengan kamera HP (atau tombol scan di beranda AstoOne / ketik 6 angka di layar).
   Bila belum masuk, AstoOne meminta login NRP lalu langsung kembali ke form absen.
2. Pilih peran (Audience / Pemateri) dan isi jam mulai tidur & bangun.
3. Status fatigue langsung tampil: **≥ 6 jam Fit to Work · 5–6 jam Coaching Atasan · < 5 jam Fatigue Risk**
   (sama dengan aturan form lama). Fatigue Risk mendapat instruksi lapor atasan dan ditandai merah di halaman host.

## Rekap

Panel → Absensi → **Rekap**: filter periode, kegiatan, seksi, fatigue, terlambat, nama/NRP; grafik per hari,
komposisi fatigue, pemateri teraktif, tabel per seksi, detail, dan **Ekspor Excel** (kolom mengikuti lembar
absensi lama: hari, tanggal, shift, waktu mulai, perusahaan, departemen, kegiatan, judul, lokasi, nama, NRP,
jabatan (⭐ untuk pemateri), section, jam absen, terlambat, jam tidur, durasi, status fatigue).

## Mentari

- Semua: "absensi saya", "ada P5M sekarang?"
- Host: "siapa yang belum absen?" → daftar dari sesi terbaru yang punya seksi peserta.
