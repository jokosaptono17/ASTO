# AstoOne (repo ASTO)

Portal PWA Plant ASTO: Laravel 12 + Blade + Alpine + Tailwind 4. Bahasa UI & komentar: Indonesia.

- Login NRP + PIN 6 angka (`AuthController`); kolom password Laravel = `users.pin` (`getAuthPasswordName`).
- Peran: `user`, `gl` (host absensi), `she` (kampanye & pengumuman), `admin` — gate `host-attendance`, `manage-content`, `admin` di `AppServiceProvider`.
- Absensi (`docs/absensi.md`): satu QR `/absen` → cek GPS (`AttendanceLocation::locate`, poligon + toleransi, `Services\Geo`) → pilih kegiatan. Sesi rutin dari `AttendanceSchedule::ensureSessions()` (tanpa cron); event via `Admin\AttendanceSessionController` (mode gps | qr token HMAC 30 dtk | open). `AttendanceRecord`: fatigue ≥6j fit / 5–6j coaching / <5j risk, `geo_status` inside|unverified|none, cookie `astoone_device` = 1 HP 1 NRP per sesi.
- Model aplikasi = `AppLink` (tabel `apps`). Buka aplikasi lewat route `apps.go` agar kunjungan tercatat.
- Gambar diolah `ImageService` (GD → WebP) ke disk `public`.
- Mentari: `MentariController` → `MentariGuide` (+ `PeopleDesk` untuk kartu karyawan/statistik/training) menjawab dari data asli; hanya obrolan bebas ke `MENTARI_ENDPOINT`. Kontrak & aturan lihat data di `docs/mentari.md`.
- Data LMS dibaca lewat koneksi read-only `lms`/`sertifikat` (`LmsDirectory`), opsional — semua jalur harus tetap jalan bila tidak diatur.
- Aset di-build lalu di-commit (`public/build`), karena VPS tidak memakai Node. Setelah ubah CSS/JS/Blade: `npm run build`.
- Tes: `php artisan test` (SQLite memori). Deploy: `docs/DEPLOY.md`.
- Desktop (≥ `lg`): portal pakai `partials/desktop-nav` + `desktop-footer`, tabbar disembunyikan; beranda `bleed` dengan hero foto (`Setting::hero()`, bawaan `public/images/hero-default.webp`, bisa diganti di Pengaturan) + panel kaca `portal/_home-desktop`. Admin: sidebar berkelompok, topbar + palet perintah Ctrl+K (`adminPalette`).
- Desain: palet "pit malam" (`resources/css/app.css` @theme) — hijau-hitam, kontur bench tambang (`x-contour`), neon `#3dff8e`, aksen hi-vis `#ffc23d`; font Plus Jakarta Sans + Chakra Petch.
