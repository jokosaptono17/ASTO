# AstoOne (repo ASTO)

Portal PWA Plant ASTO: Laravel 12 + Blade + Alpine + Tailwind 4. Bahasa UI & komentar: Indonesia.

- Login NRP + PIN 6 angka (`AuthController`); kolom password Laravel = `users.pin` (`getAuthPasswordName`).
- Peran: `user`, `she` (kampanye & pengumuman), `admin` — gate `manage-content` dan `admin` di `AppServiceProvider`.
- Model aplikasi = `AppLink` (tabel `apps`). Buka aplikasi lewat route `apps.go` agar kunjungan tercatat.
- Gambar diolah `ImageService` (GD → WebP) ke disk `public`.
- Mentari: `MentariController` → `MentariGuide` (+ `PeopleDesk` untuk kartu karyawan/statistik/training) menjawab dari data asli; hanya obrolan bebas ke `MENTARI_ENDPOINT`. Kontrak & aturan lihat data di `docs/mentari.md`.
- Data LMS dibaca lewat koneksi read-only `lms`/`sertifikat` (`LmsDirectory`), opsional — semua jalur harus tetap jalan bila tidak diatur.
- Aset di-build lalu di-commit (`public/build`), karena VPS tidak memakai Node. Setelah ubah CSS/JS/Blade: `npm run build`.
- Tes: `php artisan test` (SQLite memori). Deploy: `docs/DEPLOY.md`.
- Desain: palet "pit malam" (`resources/css/app.css` @theme) — hijau-hitam, kontur bench tambang (`x-contour`), neon `#3dff8e`, aksen hi-vis `#ffc23d`; font Plus Jakarta Sans + Chakra Petch.
