# Absensi AstoOne — P5M, Safety Talk, Safety Alert & event

Pengganti form absensi P5M lama (Absensi-Karyawan-noapi).

## Prinsip

- **Satu QR untuk semua absensi**: `plantasto.my.id/absen`. Cetak dari Panel → Absensi → *Cetak QR absensi*
  (bisa diberi label lokasi), tempel di WS 25, Quarry, Crusher. QR ini tidak pernah berubah.
- **Jadwal rutin otomatis**: P5M shift 1 (05:30–07:00) & shift 2 (17:30–19:00) setiap hari, Safety Talk
  Sabtu 06:00–08:00. Sesi dibuat otomatis saat pertama kali dibutuhkan — tidak perlu cron, GL tidak perlu
  membuat sesi setiap hari. Ubah di Panel → Absensi → **Jadwal rutin**.
- **Event khusus** (sarasehan, recall insiden, dll.) dibuat GL/admin lewat *Event baru* dan otomatis muncul
  di pilihan kegiatan karyawan.

## Pengaman titip absen

| Lapisan | Perilaku |
| --- | --- |
| Identitas dari akun | Nama/NRP/seksi diambil dari login NRP + PIN |
| GPS area (poligon) | Di dalam area (+ toleransi) → **terverifikasi**. Pasti di luar (jarak > toleransi + akurasi) → **ditolak** |
| GPS gagal / lemah | Izin ditolak, sinyal lemah, akurasi > 50 m → tetap bisa absen, ditandai **lokasi belum terverifikasi** untuk diperiksa GL |
| Satu HP satu NRP | Cookie perangkat; HP yang sudah dipakai NRP lain di sesi yang sama → **ditolak** |
| QR layar berganti (opsional) | Mode *QR layar + GPS* untuk event: token 6 angka di TV berganti tiap 30 detik |

Keterbatasan: aplikasi web tidak bisa mendeteksi aplikasi *Fake GPS* di Android atau mode penyamaran browser
(cookie baru). Karena itu GL tetap melihat area, jarak, dan akurasi GPS setiap peserta di halaman live.

## Area GPS (Panel → Absensi → Area GPS)

Dipetakan **di lokasi** dari HP admin, bukan dari citra peta (sering tidak mutakhir di area tambang):

1. Buka area (WS 25 / Quarry / Crusher), berdiri di sudut pertama → **Tandai titik di sini** (posisi dirata-rata ±5 detik).
2. Jalan ke sudut berikutnya, ulangi. Atau nyalakan **Rekam sambil berjalan** lalu susuri batas area (titik tiap 8 m).
3. Atur **toleransi** (disarankan 20–30 m), cek "Posisi Anda di dalam area", lalu **Simpan area**.

Titik bisa digeser di peta untuk dirapikan. Area yang belum dipetakan tidak dicek; bila belum ada satu pun area
yang dipetakan, absen berjalan tanpa cek lokasi.

## Peran

| Peran | Bisa |
| --- | --- |
| Karyawan | Scan QR / buka beranda, pilih kegiatan, isi peran & jam tidur, riwayat sendiri |
| **GL / Host absensi** (`gl`) | + memantau sesi rutin & event miliknya, catat manual, layar TV, rekap, buat event |
| Admin SHE | + semua sesi, **jadwal rutin**, **area GPS**, kampanye, pengumuman |
| Administrator | + jenis kegiatan & semua menu |

## Alur karyawan

Scan QR → (login NRP bila perlu, langsung kembali) → AstoOne mengecek posisi → pilih kegiatan yang sedang dibuka
→ pilih Audience/Pemateri + jam tidur → status fatigue: **≥ 6 jam Fit to Work · 5–6 jam Coaching Atasan · < 5 jam Fatigue Risk**.

## Host

Halaman sesi: daftar hadir live (area GPS tiap peserta, filter *Lokasi ?*), belum hadir + *Salin untuk WA*,
catat manual, tutup/buka lagi, layar TV, ekspor Excel (termasuk area, status lokasi, akurasi, koordinat).
Rekap: filter periode, kegiatan, seksi, fatigue, terlambat, lokasi belum pasti.

## Catatan teknis

- Jam mengikuti `APP_TIMEZONE` (bawaan `Asia/Jakarta`/WIB). Bila jobsite memakai WITA, set `APP_TIMEZONE=Asia/Makassar`.
- Kamera & GPS butuh HTTPS dan header `Permissions-Policy: camera=(self), geolocation=(self)`.
- Peta editor memakai Leaflet + citra Esri / OpenStreetMap (hanya untuk konteks, data area dari GPS).
