# AstoOne — portal aplikasi Plant ASTO

Satu pintu untuk semua aplikasi Plant ASTO (PT Kalimantan Prima Persada, jobsite ASTO).
Bisa dipasang di HP lewat Chrome (PWA), masuk dengan **NRP + PIN 6 angka**, dan isinya
(aplikasi, kampanye, pengumuman) dikelola dari **panel admin** tanpa mengubah kode.

| Bagian | Isi |
| --- | --- |
| Beranda | Ikon aplikasi utama & pintasan, pencarian, pita pengumuman penting/darurat, terakhir dibuka, banner & kartu kampanye, favorit |
| Aplikasi | Semua aplikasi per kategori, pencarian, favorit (bintang), lampu status online/gangguan |
| Mentari | Robot mekanik asisten (sama dengan Mentari di MekanikPintar): cari aplikasi, info terbaru, bantuan PIN/NRP, siap disambung ke AI |
| Info | Pengumuman (info / penting / darurat) + semua kampanye |
| Profil | Identitas, ganti PIN, pasang aplikasi, bantuan WhatsApp, panel admin |
| Panel admin `/admin` | Ringkasan statistik, Kampanye, Pengumuman (Admin SHE & Administrator); Aplikasi, Kategori, Pengguna + impor Excel, Mentari, Pengaturan (Administrator) |

## Teknologi

Laravel 12 · PHP 8.2+ (VPS: 8.3) · MariaDB/MySQL · Blade + Alpine.js + Tailwind CSS 4 (aset sudah
di-build di `public/build`, VPS tidak perlu Node.js) · GD untuk kompres gambar ke WebP.

## Peran

| Peran | Bisa |
| --- | --- |
| Karyawan | Memakai portal |
| Admin SHE | + kelola kampanye & pengumuman |
| Administrator | + aplikasi, kategori, pengguna, Mentari, pengaturan |

## Menjalankan di komputer sendiri

```bash
composer install
cp .env.example .env   # ubah DB_CONNECTION=sqlite, APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8000
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan astoone:admin KT12345 "NAMA ANDA"
php artisan serve
```

Mengubah tampilan: `npm install && npm run build`, lalu commit folder `public/build`.

Tes: `php artisan test`

## Dokumen

- [docs/DEPLOY.md](docs/DEPLOY.md) — pasang di VPS (aaPanel) untuk `plantasto.my.id`
- [docs/mentari.md](docs/mentari.md) — kontrak layanan AI Mentari
