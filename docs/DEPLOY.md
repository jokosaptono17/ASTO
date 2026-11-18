# Deploy AstoOne ke VPS (aaPanel) — plantasto.my.id

Jalankan sebagai root lewat SSH/terminal aaPanel. Lakukan bertahap dan cek hasil tiap langkah.

## 1. Siapkan situs & database (aaPanel)

1. **Website → Add site**: domain `plantasto.my.id` dan `www.plantasto.my.id`, PHP 8.3,
   buat database MySQL `sql_plantasto` (catat user & password).
2. **App Store → PHP 8.3 → Setting**
   - *Install extensions*: pastikan `fileinfo` terpasang (untuk unggah gambar) — `gd`, `zip`, `pdo_mysql`, `mbstring` biasanya sudah ada.
   - *Disabled functions*: `putenv`, `proc_open`, `symlink` jangan dinonaktifkan (dibutuhkan composer & `storage:link`).
     Bila situs Laravel lain (induksi, mekanikpintar) sudah jalan, biasanya ini sudah beres.

## 2. Ambil kode

```bash
cd /www/wwwroot
rm -rf plantasto.my.id/*  plantasto.my.id/.user.ini 2>/dev/null   # folder bawaan aaPanel, masih kosong
# .user.ini dikunci aaPanel; bila gagal dihapus: chattr -i plantasto.my.id/.user.ini lalu ulangi
git clone -b portal-v2 https://github.com/jokosaptono17/ASTO.git plantasto.my.id
cd plantasto.my.id
composer install --no-dev --optimize-autoloader
```

## 3. Konfigurasi

Pastikan Anda berada di `/www/wwwroot/plantasto.my.id` dan di folder itu ada `artisan`
(bukan subfolder `plantasto.my.id/plantasto.my.id`).

Isi `.env` (nano: simpan **Ctrl+O** lalu Enter, keluar **Ctrl+X**). Ubah baris yang **sudah ada** — jangan menambah baris
baru dengan nama yang sama, karena baris yang paling bawah yang dipakai (misalnya `ADMIN_NRP=` kosong di akhir file):

| Kunci | Isi |
| --- | --- |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | dari aaPanel → Databases |
| `ADMIN_NRP`, `ADMIN_NAME` | NRP dan nama lengkap Anda (admin pertama) |
| `APP_URL` | **sementara** `http://plantasto.my.id` — ganti ke `https://` setelah SSL aktif (langkah 5) |
| `SESSION_SECURE_COOKIE` | **sementara** `false` — ganti ke `true` setelah SSL aktif |

```bash
cp .env.example .env
nano .env
php artisan key:generate
php artisan migrate --seed --force      # tabel + aplikasi awal + admin pertama dari ADMIN_NRP
php artisan storage:link
chown -R www:www storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 4. Arahkan Nginx ke folder `public`

aaPanel → Website → plantasto.my.id → **Site directory**: Running directory = `/public`
(matikan *Anti-XSS / open_basedir* bila menghalangi). **URL rewrite** → pilih template `laravel5`.

## 5. Domain & SSL

1. Cek IP VPS: `curl -4 ifconfig.me`. Di Hostinger → Domain → DNS/Nameserver:
   ubah satu record **A `@`** (185.199.108.153) menjadi IP VPS dengan TTL 300, hapus tiga A `@` GitHub lainnya
   (185.199.109/110/111.153), hapus **CNAME `www`** (jokosaptono17.github.io) lalu tambah **A `www`** → IP VPS.
2. GitHub → repo ASTO → Settings → Pages: hapus custom domain / matikan Pages.
3. aaPanel → Website → SSL → Let's Encrypt untuk kedua domain → aktifkan *Force HTTPS*.
4. Kembalikan `.env`: `APP_URL=https://plantasto.my.id` dan `SESSION_SECURE_COOKIE=true`, lalu `php artisan config:cache`.

## 6. Cron (lampu status aplikasi)

aaPanel → Cron → Add: Shell script, setiap 1 menit:

```bash
cd /www/wwwroot/plantasto.my.id && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Masuk pertama

Buka https://plantasto.my.id → ketik NRP Anda → konfirmasi nama depan → buat PIN.
Lalu Profil → **Panel admin** → Pengguna → **Impor dari Excel** untuk memasukkan NRP & nama karyawan.
Tambahkan Admin SHE dengan mengubah peran pengguna di menu Pengguna.

## Update berikutnya

```bash
cd /www/wwwroot/plantasto.my.id
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Bila mengubah `public/sw.js`, naikkan `VERSION` di dalamnya agar HP mengambil versi baru.

## Cadangan

Yang perlu dicadangkan: database `sql_plantasto` dan folder `storage/app/public` (gambar kampanye & ikon).
