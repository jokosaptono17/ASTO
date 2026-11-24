# AstoOne — Dokumen Serah-Terima Proyek

> Portal aplikasi Plant ASTO · `https://plantasto.my.id` · repo `jokosaptono17/ASTO` (cabang `main`)
> Disusun 24 November 2026, setelah PR #1–#12 di-merge. Pemilik proyek: Plant Instruktur, Dept. Plant jobsite ASTO.

Dokumen ini adalah titik masuk tunggal untuk siapa pun yang meneruskan pengembangan AstoOne,
baik developer manusia maupun AI agent. Isinya: apa yang sudah dibangun, mengapa dibangun begitu,
cara kerjanya, cara menjalankan dan men-deploy-nya, serta aturan yang tidak boleh dilanggar.

**Dokumen ini sengaja tidak memuat rahasia apa pun** (password, `APP_KEY`, token, rahasia SSO, IP server,
data pribadi karyawan). Semua nilai rahasia hanya ada di `.env` server. Sebutkan nama kuncinya saja,
jangan pernah nilainya.

Dokumen pendamping yang lebih rinci per modul (tetap berlaku):

| File | Isi |
|---|---|
| `CLAUDE.md` | Ringkasan padat untuk AI agent (dibaca otomatis oleh Claude Code) |
| `docs/DEPLOY.md` | Langkah deploy pertama di aaPanel, DNS, SSL, cron, koneksi LMS read-only |
| `docs/absensi.md` | Absensi P5M / Safety Talk / event, geofence GPS, peran host |
| `docs/mentari.md` | Kontrak layanan AI Mentari, pembagian intent, aturan lihat data |
| `docs/poin.md` | Poin HEBAT: aturan, pemberian, penukaran, audit |
| `docs/ujian.md` | Ujian Event: bank soal, alur, aturan keamanan |

---

## Daftar isi

1. [Aturan emas (baca dulu)](#1-aturan-emas-baca-dulu)
2. [Gambaran produk](#2-gambaran-produk)
3. [Riwayat pengembangan](#3-riwayat-pengembangan)
4. [Keputusan yang sudah dikunci](#4-keputusan-yang-sudah-dikunci)
5. [Arsitektur & teknologi](#5-arsitektur--teknologi)
6. [Struktur folder](#6-struktur-folder)
7. [Peran & hak akses](#7-peran--hak-akses)
8. [Modul satu per satu](#8-modul-satu-per-satu)
9. [Skema database](#9-skema-database)
10. [Peta route](#10-peta-route)
11. [Konfigurasi `.env`](#11-konfigurasi-env)
12. [Deploy & operasional](#12-deploy--operasional)
13. [Integrasi dengan sistem lain](#13-integrasi-dengan-sistem-lain)
14. [Pengujian & QA](#14-pengujian--qa)
15. [Konvensi kode & desain](#15-konvensi-kode--desain)
16. [Alur kerja Git & PR](#16-alur-kerja-git--pr)
17. [Jebakan yang pernah ditemui](#17-jebakan-yang-pernah-ditemui)
18. [Keamanan & privasi data](#18-keamanan--privasi-data)
19. [Pekerjaan lanjutan & ide](#19-pekerjaan-lanjutan--ide)
20. [Checklist untuk developer / agent baru](#20-checklist-untuk-developer--agent-baru)
21. [Glosarium](#21-glosarium)

---

## 1. Aturan emas (baca dulu)

1. **Bahasa**: UI, komentar kode, pesan commit, dan deskripsi PR memakai **Bahasa Indonesia**.
2. **Satu perubahan = satu cabang = satu PR baru.** Jangan menambah commit ke cabang yang PR-nya sudah di-merge.
   Selalu berikan tautan PR ke pemilik agar ia yang me-merge.
3. **Aset di-build dan di-commit.** VPS tidak memakai Node. Setiap mengubah CSS, JS, atau Blade (Tailwind memindai
   Blade), jalankan `npm run build` lalu commit `public/build`.
4. **Jangan pernah commit rahasia**: `.env`, password database, `APP_KEY`, token, rahasia SSO, foto karyawan.
   Jangan juga mengulang atau menyimpan rahasia yang kebetulan terlihat di percakapan.
5. **Database LMS hanya dibaca** (koneksi `lms`/`sertifikat` dengan user MySQL khusus `SELECT`). Jangan pernah menulis atau mengubahnya.
6. **No HP karyawan hanya terlihat oleh Administrator.** Data kesehatan tidak dikumpulkan selain jam tidur untuk status fatigue absensi.
7. **Pekerjaan di VPS dilakukan bertahap dan bisa diverifikasi.** Pemilik yang menjalankan perintah di server.
   Jangan menghapus apa pun di server tanpa konfirmasinya.
8. **Saldo poin hanya lewat `PointService::post()`** dan **ujian hanya lewat `ExamService`**. Jangan menulis tabel
   `point_transactions`, `users.points_*`, `exam_attempts`, atau `exam_answers` langsung dari controller.
9. **Kunci jawaban ujian tidak boleh dikirim ke browser peserta**, dalam bentuk apa pun.
10. Jalankan `php artisan test` sebelum membuat PR. Semua tes harus lulus (saat ini 110 tes).

---

## 2. Gambaran produk

AstoOne menggantikan halaman portal statis lama di GitHub Pages. Kini ia menjadi **aplikasi web yang bisa dipasang
(PWA) dari Chrome**, dengan tampilan bergaya super-app (terinspirasi beranda Traveloka). Penggunanya karyawan Plant ASTO:
mekanik, GL, tim SHE, dan admin.

Fitur utama:

| Fitur | Untuk siapa | Inti |
|---|---|---|
| Portal aplikasi | Semua | Ikon aplikasi Plant ASTO (LMS, shop manual, Monata, dll.), favorit, pencarian, lampu status online |
| Kampanye & pengumuman | Semua · dikelola SHE/admin | Banner 2:1, kartu 4:5, pop-up 3:4 saat aplikasi dibuka (bisa wajib-baca), pita pengumuman penting |
| Mentari | Semua | Asisten chat: kartu karyawan, pengetahuan aplikasi, statistik, data training LMS; obrolan bebas ke AI opsional |
| Absensi | Semua · host GL | Satu QR `/absen`, geofence GPS, jadwal rutin P5M/Safety Talk otomatis, status fatigue, rekap & Excel |
| Poin HEBAT | Semua · admin | Poin reward tertutup, katalog hadiah, kupon QR, pemberian poin event dengan persetujuan |
| Ujian Event | Peserta ditunjuk · admin | Ujian kompetisi (Best Mekanik/GL), satu kode per event, nilai di server, pantau langsung |
| SSO MekanikPintar | Semua | "Masuk dengan AstoOne" ke LMS memakai surat jalan bertanda tangan HMAC |
| Panel admin | Admin/SHE/GL | Sidebar berkelompok, palet perintah Ctrl+K, impor/ekspor Excel |

Login memakai **NRP + PIN 6 angka**. Aktivasi pertama: ketik NRP, konfirmasi nama depan, lalu buat PIN.
Orang luar tidak bisa masuk karena NRP harus terdaftar lebih dulu di tabel `users` (diimpor admin dari Excel manpower).

---

## 3. Riwayat pengembangan

Semua PR sudah di-merge ke `main`.

| PR | Tanggal | Judul | Isi pokok | Migrasi |
|---|---|---|---|---|
| — | Agu 2026 | Halaman statis lama | `index.html` di GitHub Pages | — |
| #1 | 18 Nov | AstoOne: portal PWA + panel admin | Laravel, login NRP+PIN, beranda super-app, kampanye, pengumuman, aplikasi & kategori, pengguna (impor/ekspor Excel, reset PIN), Mentari dasar, PWA, lampu status | `create_portal_tables` |
| #2 | 18 Nov | Mentari lebih pintar | Kartu karyawan, pengetahuan aplikasi (Penjelasan/Cara pakai/PIC), statistik admin, data training LMS read-only, kolom grade/GL wali/lokasi/program | `add_details_and_app_knowledge` |
| #3 | 19 Nov | Absensi P5M/Safety Talk | Satu QR, geofence GPS poligon, jadwal rutin tanpa cron, 1 HP 1 NRP, fatigue, peran GL, layar TV, rekap & Excel | `create_attendance_tables` |
| #4 | 19 Nov | Absensi di beranda + Lokasi saya | Kartu "Absensi hari ini", linimasa, statistik bulan ini; panel lokasi saya di Area GPS | — |
| #5 | 20 Nov | Tampilan desktop | Navbar + hero foto ala Traveloka, admin dengan sidebar berkelompok & palet perintah Ctrl+K, foto hero bisa diganti | — |
| #6 | 20 Nov | Kampanye pop-up | Format pop-up 3:4, frekuensi once/daily/session, hitung mundur tutup, wajib-baca, target seksi, statistik yang belum membaca | `add_popup_to_campaigns` |
| #7 | 20 Nov | Poin HEBAT | Dompet, level, katalog hadiah, kupon QR, poin otomatis dari absensi, pemberian poin event + persetujuan empat mata, buku besar | `create_points_tables` |
| #8 | 22 Nov | Pencarian pengguna langsung | `/admin/pengguna` mencari saat mengetik (NRP berspasi, urutan nama bebas, departemen, jabatan), respons fragmen | — |
| #9 | 24 Nov | Ujian Event | Pengganti `ppdasto.github.io/soal_ujian` + Apps Script: bank soal, impor sheet lama, peserta ditunjuk, notifikasi login, ruang ujian, pantau langsung, peringkat, analisis butir | `create_exam_tables` |
| #10 | 24 Nov | Perbaiki dropdown | Daftar `<option>` diberi latar gelap padat agar terbaca di Chrome Windows/Android | — |
| #11 | 24 Nov | Lanjut otomatis | Ruang ujian pindah ke soal berikutnya setelah memilih (jeda 450 ms, bisa dimatikan) | — |
| #12 | 24 Nov | SSO MekanikPintar | `/sso/mekanikpintar`: layar persetujuan, surat jalan HMAC-SHA256 90 detik sekali pakai | — (hanya `.env`) |
| #13 | 24 Nov | Dokumen serah-terima | `docs/HANDOVER.md` (dokumen ini) | — |
| #14 | 25 Nov | Mentari cerdas | Bahasa chat & salah ketik, ingatan percakapan, pengetahuan BM25 + 15 panduan bawaan, ujian, log pertanyaan + 👍/👎, AI gratis Groq/Gemini/OpenRouter dengan tool calling, kuota & privasi | `create_mentari_logs_table` |

Jumlah tes bertambah dari 24 (PR #1) menjadi **110** (PR #14).

---

## 4. Keputusan yang sudah dikunci

Keputusan ini dibuat bersama pemilik. Jangan mengubahnya tanpa bertanya.

**Platform & tampilan**
- Laravel di VPS Hetzner sendiri (aaPanel), domain `plantasto.my.id` (DNS di Hostinger, `www` dialihkan ke domain utama).
- Bisa dipasang dari Chrome (PWA). Tampilan HP diutamakan; desktop punya tata letak sendiri (≥ `lg`).
- Tema gelap "pit malam" dengan neon hijau dan aksen hi-vis kuning. Hero desktop memakai foto hutan berkabut milik pemilik
  (bisa diganti di Pengaturan).
- Konten kampanye dan pengumuman dikelola dari panel admin, tidak di-hardcode. Tim SHE juga bisa menjadi admin konten.

**Absensi**
- Satu QR untuk semua absensi. Karyawan yang belum login tetap bisa memulai dari QR itu.
- P5M setiap hari: shift 1 pukul 05:30–07:00, shift 2 pukul 17:30–19:00. Safety Talk setiap Sabtu pukul 06:00–08:00.
  Sesi rutin dibuat otomatis. Event khusus (sarasehan, recall insiden) dibuat GL/admin.
- Lokasi: WS 25, Quarry, Crusher. **Poligon dipetakan di lokasi** dengan admin berjalan di batas area, bukan digambar dari citra peta (citra sering usang).
- GPS gagal → tetap boleh absen, ditandai "lokasi belum terverifikasi". Pasti di luar area → ditolak.
- Satu HP dipakai dua NRP di sesi yang sama → ditolak.
- Jam tidur **tidak** memengaruhi poin, supaya karyawan jujur soal fatigue.

**Poin HEBAT**
- Poin tertutup: tidak bisa dibeli, dicairkan, atau ditransfer. Hadiah disediakan tim admin.
- Terpisah dari poin LMS mekanikpintar.com.
- Ide e-wallet/voucher sempat dibahas, tetapi yang dibangun adalah poin tertutup ini.

**Mentari**
- Tier 1: berbasis aturan dari data asli. Tier 2: data manpower dan LMS. Tier 3 (PR #14): AI **gratis saja** (tanpa
  langganan) — Groq utama, Gemini & OpenRouter cadangan; AI lokal di VPS ditolak karena VPS hanya 2 core / 3,8 GB.
- Pertanyaan berstruktur **selalu** dijawab dari database/pengetahuan; AI hanya untuk yang tidak terjawab, dan AI
  mengambil data lewat alat (bukan mengarang). Data karyawan hanya ke penyedia yang tidak melatih model dengan data API.

**Ujian Event**
- Dibangun di AstoOne (bukan Apps Script lagi): peserta login, identitas dari akun.
- **Satu kode untuk semua peserta event**, diumumkan pengawas saat ujian dimulai.
- Admin menunjuk peserta yang wajib ikut. Peserta mendapat pemberitahuan otomatis saat membuka beranda.
- Bank soal dikelola di aplikasi dan bisa diimpor dari Excel, termasuk sheet lama `INTEGRATED_KUMPULAN_SOAL`.

---

## 5. Arsitektur & teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8.3, Laravel 12, MySQL (produksi) · SQLite memori (tes) |
| Frontend | Blade + Alpine.js 3 + Tailwind CSS 4 (plugin Vite), Remix Icon |
| Font | Plus Jakarta Sans (teks) + Chakra Petch (angka/judul), di-bundle lewat `@fontsource` (bukan CDN) |
| Pustaka JS | `leaflet` (peta area GPS), `qrcode` (QR/kupon/poster), `jsqr` (scan QR di panel) |
| Excel | Pembaca/penulis XLSX buatan sendiri `App\Services\Spreadsheet` (ZipArchive + XML), tanpa paket Composer tambahan |
| Gambar | `App\Services\ImageService` (GD → WebP) ke disk `public` (`storage/app/public`) |
| PWA | `public/manifest.webmanifest`, `public/sw.js` (versi di konstanta `VERSION`), halaman `/offline` |
| Server | Hetzner VPS + aaPanel + Nginx (`Running directory = /public`, rewrite `laravel5`), Let's Encrypt |
| Penjadwal | Cron `schedule:run` tiap menit, hanya untuk lampu status aplikasi dan pembersihan log |

Prinsip desain sistem:
- **Tanpa antrean/worker.** Hal yang biasanya butuh cron dibuat "malas":
  - sesi absensi rutin dibuat saat pertama kali dibutuhkan (`AttendanceSchedule::ensureSessions()`);
  - percobaan ujian yang lewat waktu ditutup saat halaman terkait dibuka (`ExamService::closeOverdue()`).
- **Logika bisnis di Service**, bukan di controller: `PointService`, `ExamService`, `QuestionImporter`, `UserImporter`,
  `Geo`, `LmsDirectory`, dan `Services\Mentari\*`.
- **Fitur opsional harus tetap jalan bila tidak diatur**: LMS read-only, `MENTARI_ENDPOINT`, dan SSO (mengembalikan 404 bila rahasia kosong).
- Middleware `SecurityHeaders` mengirim `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, dan
  `Permissions-Policy: camera=(self), microphone=(), geolocation=(self)`. Kamera dan GPS wajib HTTPS.
- `trustProxies('*')` aktif karena berjalan di belakang Nginx/Cloudflare.

---

## 6. Struktur folder

```
app/
  Console/Commands/      CheckApps (astoone:check-apps), MakeAdmin (astoone:admin {nrp} {name?} --role=)
  Http/Controllers/      Auth, Home, App, Info, Popup, Profile, Mentari, Attendance, Point, Exam, Sso
  Http/Controllers/Admin Dashboard, User, AppLink, Category, Campaign, Announcement, MentariFaq, Setting,
                         Attendance* (Session, Schedule, Location, Type, Recap, Export), Points/*, Exams/*
  Http/Middleware/       SecurityHeaders
  Models/                User, AppLink(tabel apps), Category, Campaign(+Click, View), Announcement, Setting,
                         MentariFaq, ActivityLog, AppVisit, Attendance*(Type, Location, Schedule, Session, Record),
                         Point*(Transaction, Rule, Grant, GrantRecipient), Reward, Redemption,
                         QuestionSet, Question, Exam, ExamParticipant, ExamAttempt, ExamAnswer
  Services/              Geo, ImageService, LmsDirectory, Spreadsheet, UserImporter,
                         Mentari/(MentariGuide, MentariContext, PeopleDesk, AttendanceDesk, PointsDesk),
                         Points/PointService, Exams/(ExamService, QuestionImporter, ExamException)
config/services.php      mentari, lms, mekanikpintar (SSO)
database/migrations/     9 migrasi (lihat §9)
docs/                    DEPLOY, absensi, mentari, poin, ujian, HANDOVER (dokumen ini)
public/build/            aset hasil Vite (DI-COMMIT)
public/sw.js             service worker — naikkan VERSION bila diubah
resources/css/app.css    @theme palet + komponen (.btn-*, .field, .chip, .exam-*, dll.)
resources/js/            app.js (Alpine + komponen umum), mentari.js, attendance.js, geo.js, exam.js
resources/views/
  components/layouts/    portal, admin, guest, exam (ruang ujian & layar proyektor)
  partials/              head, desktop-nav, desktop-footer, toast
  portal/                home, _home-desktop, _popup, apps, info, profile, attendance/*, points/*, exams/*
  admin/                 dashboard, users, apps, campaigns, announcements, mentari, settings,
                         attendance/*, points/*, exams/* (index, form, show, attempt, screen, bank/*)
  sso/mekanikpintar      layar persetujuan SSO
routes/web.php           semua route web (± 174 route)
routes/console.php       jadwal: astoone:check-apps tiap 10 menit, prune log mingguan
tests/Feature, tests/Unit
```

---

## 7. Peran & hak akses

`users.role`: `user` (Karyawan) · `gl` (GL / Host absensi) · `she` (Admin SHE) · `admin` (Administrator).

Gate di `AppServiceProvider`:

| Gate | Siapa | Membuka |
|---|---|---|
| `admin` | admin | Pengguna, Aplikasi & Kategori, Mentari FAQ, Pengaturan, jenis kegiatan absensi |
| `manage-content` | admin, she | Kampanye, Pengumuman, jadwal rutin & area GPS absensi |
| `host-attendance` | admin, she, gl | Sesi absensi, rekap, layar TV, event |
| `manage-points` | admin, she | Poin HEBAT (persetujuan & penyesuaian manual tetap khusus Administrator) |
| `manage-exams` | admin, she | Ujian Event & bank soal |

Aturan lihat data karyawan (Mentari, profil):
- Semua yang login boleh melihat nama, NRP, seksi, jabatan, grade, GL wali, lokasi, dan program.
- Riwayat training, poin LMS, dan sertifikat hanya untuk orangnya sendiri, GL walinya (`supervisor_nrp`), dan Administrator.
- No HP, status akun, dan login terakhir hanya untuk Administrator.

Membuat admin dari terminal: `php artisan astoone:admin KTxxxxx "NAMA" --role=admin`.
Admin pertama dibuat oleh seeder dari `ADMIN_NRP`/`ADMIN_NAME`.

---

## 8. Modul satu per satu

### 8.1 Autentikasi (`AuthController`)
- Alur: `/masuk` (ketik NRP) → `/masuk/pin` (PIN 6 angka dengan papan angka, mendukung keyboard)
  atau `/aktivasi` bila belum aktif (nama depan + PIN baru + ulangi).
- PIN disimpan di kolom `users.pin` (hash). `getAuthPasswordName()` mengembalikan `pin`.
- Proteksi: kunci 5 menit setelah 5 kali salah (`RateLimiter`). PIN lemah seperti `111111` atau `123456` ditolak.
  Login memakai remember-me.
- Tombol bantuan WhatsApp untuk NRP belum terdaftar atau lupa PIN. Nomornya diatur di Pengaturan (`Setting::whatsappNumber()`).
- `redirect()->intended()` dipakai, sehingga QR absensi dan SSO kembali ke tujuan setelah login.
- Reset PIN oleh admin di `/admin/pengguna/{user}`. Karyawan bisa mengganti PIN di `/profil/pin`.

### 8.2 Beranda, aplikasi & PWA
- HP: 5 ikon utama + pintasan + "+N lainnya", pencarian, pita pengumuman, terakhir dibuka, banner, kartu kampanye, favorit.
- Desktop: hero foto (`Setting::hero()`, bawaan `public/images/hero-default.webp`) + panel kaca `_home-desktop`
  (tab kategori, cari dengan `/`), lalu dua kolom.
- Aplikasi = model `AppLink` (tabel `apps`), punya kolom Penjelasan/Cara pakai/PIC untuk Mentari.
  **Selalu buka aplikasi lewat route `apps.go` (`/buka/{app}`)** agar kunjungan tercatat di `app_visits`.
- Lampu status: `astoone:check-apps` mengecek URL tiap aplikasi setiap 10 menit (butuh cron).
- Urutan kartu di beranda (HP): Ujian untuk Anda → Absensi hari ini → Poin → kampanye.
- Pop-up: pemberitahuan ujian didahulukan dari pop-up kampanye; hanya satu yang tampil per buka.

### 8.3 Kampanye & pengumuman
- Format kampanye: `card` (4:5), `banner` (2:1), `popup` (3:4, 900×1200). Gambar dipotong dan dikompres ke WebP.
- Pop-up: `popup_frequency` (once/daily/session), `popup_seconds` (hitung mundur tombol tutup), `popup_ack`
  (wajib "Saya sudah membaca"), dan `target_departments`. Jejak per orang di `campaign_views`.
  `Campaign::popupFor($user)` memilih satu pop-up, dengan wajib-baca didahulukan.
- Pratinjau admin: `/?pratinjau-popup={id}`.
- Statistik: sasaran, sudah melihat, konfirmasi baca, klik, daftar yang belum membaca, dan tombol *Salin untuk WA*.
- Pengumuman punya level (mis. penting/darurat) yang tampil di pita beranda. Lonceng desktop memakai `users.announcements_seen_at`.

### 8.4 Mentari (`MentariController` → `Services\Mentari\MentariBrain`)
Alur lengkap, pengaturan AI, privasi, dan panel admin: **`docs/mentari.md`**. Ringkasnya:
`Language` (singkatan/salah ketik) → `Memory` (pertanyaan lanjutan) → `MentariGuide` (data asli) → `KnowledgeBase`
(BM25: panduan bawaan `GUIDES`, FAQ, aplikasi, pengumuman) → `Ai\Agent` (Groq → Gemini → OpenRouter, tool calling ke
`MentariGuide::tool()`) → `MENTARI_ENDPOINT` → cadangan. Jejak di `mentari_logs`; admin mengubah pertanyaan yang
belum terjawab menjadi FAQ. Respons chat membawa `source` (guide/knowledge/ai/fallback), `provider_label`, `log_id`.

Detail otak bawaan:
- Intent berstruktur dijawab dari database:
  - `people` lewat `PeopleDesk` (kartu karyawan, data saya, GL wali, mekanik asuh, training);
  - `appKnowledge`;
  - `stats` (admin);
  - `account`, `install`, `announcements`, `campaigns`, `faq`, `findApp`;
  - poin lewat `PointsDesk`, absensi lewat `AttendanceDesk`, ujian lewat `ExamDesk` (kode ujian hanya untuk admin ujian).
- Hanya yang tidak terjawab (`fallback`) yang sampai ke AI gratis; `greeting`/`identity`/`thanks` hanya ke
  `MENTARI_ENDPOINT` lama bila diatur. Bila semua gagal, otak bawaan yang menjawab.
- Balasan ke browser: `reply`, `actions` (`href` atau `ask`), `cards` (`person`/`stats`/`list`), `intent`.
  Tautan dari AI disaring: hanya relatif, `APP_URL`, atau `wa.me`.
- FAQ dikelola di `/admin/mentari` (ada halaman uji).
- Kontrak lengkap: `docs/mentari.md`.

### 8.5 Absensi
Panduan lengkap: `docs/absensi.md`.
- QR tetap `/absen` (poster A4 dari panel). Alurnya: login bila perlu → cek GPS → pilih kegiatan yang dibuka
  → peran Audience/Pemateri → jam tidur → fatigue (**≥ 6 jam Fit**, **5–6 jam Coaching atasan**, **< 5 jam Fatigue risk**).
- Geofence: `AttendanceLocation::locate()` + `Services\Geo` (titik dalam poligon + jarak ke tepi).
  Toleransi `buffer_m` (disarankan 20–30 m). Akurasi > 50 m dianggap tidak terverifikasi.
  `geo_status`: `inside` / `unverified` / `none`.
- Cookie perangkat `astoone_device`: satu HP satu NRP per sesi.
- Mode sesi `checkin_mode`: `gps` | `qr` (token 6 angka HMAC dari `secret` sesi, berganti tiap 30 detik di layar TV) | `open`.
- Jadwal rutin di `attendance_schedules`. `ensureSessions()` membuat sesi hari itu bila belum ada
  (unik `attendance_schedule_id + date`).
- Host: halaman live, belum hadir + salin WA, catat manual, tutup/buka, layar TV, ekspor Excel. Rekap dengan filter.
- Poin otomatis dicatat di `AttendanceController::record()` dan dicabut di `removeRecord()`.
- Keterbatasan yang diketahui: aplikasi web tidak bisa mendeteksi Fake GPS atau mode penyamaran.
  Karena itu GL melihat area, jarak, dan akurasi tiap peserta.

### 8.6 Poin HEBAT
Panduan lengkap: `docs/poin.md`.
- **Semua perubahan saldo lewat `PointService::post()`**: kunci baris user, `idem_key` unik (tidak ada poin ganda),
  dan saldo tidak pernah minus. Hasilnya dicatat ke buku besar `point_transactions`, yang tidak pernah diubah atau dihapus.
  Cache saldo ada di `users.points_balance` (saldo) dan `users.points_earned` (total seumur hidup, menentukan level).
- Level: Rookie → Skilled (250) → Expert (1.000) → Master (3.000) → Legend (7.500).
- Aturan otomatis di `point_rules`: hadir +5, pemateri +20, 7 hari beruntun +25.
- Pemberian poin event `point_grants`: draf → pending → approved.
  - Administrator dengan total ≤ batas (`points_approval_threshold`, bawaan 500) langsung terkirim.
  - Selebihnya, dan semua pengajuan Admin SHE, butuh persetujuan **Administrator lain** (empat mata).
- Hadiah `rewards` → penukaran `redemptions` dengan konfirmasi PIN → kupon QR `/poin/kupon/{code}` (route key `code`)
  → admin memindai, mencocokkan ID card, lalu menyerahkan. Membatalkan kupon mengembalikan poin dan stok.

### 8.7 Ujian Event
Panduan lengkap: `docs/ujian.md`.
- **Bank soal**: `QuestionSet` (paket) → `Question`. `options` berupa JSON `{"A": …, "B": …}` (2–6 opsi),
  `answer` berupa huruf, plus `explanation`, `image_path` (path lokal WebP atau URL luar), dan `is_active`.
- **Impor** `QuestionImporter`:
  - kolom dicari dari judulnya (Paket/KodeUjian, Pertanyaan/Soal, Gambar, A–F atau Opsi A–F, Jawaban/Kunci, Pembahasan);
  - sheet bernama `Soal` dipakai otomatis (`Spreadsheet::read($path, $ext, 'Soal')`);
  - kunci boleh berupa huruf atau teks opsinya;
  - impor ulang aman, karena sidik soal = teks + gambar + opsi; teks sama dengan gambar berbeda tetap dianggap soal berbeda;
  - URL GitHub `blob…?raw=true` dinormalkan ke `raw.githubusercontent.com`, dan Google Drive ke `drive.google.com/thumbnail`.
- **Event** `Exam`:
  - `code` unik (huruf besar, tanpa spasi; disarankan dari singkatan judul + 4 karakter acak tanpa O/0/I/1);
  - `audience`: `assigned` (hanya peserta ditunjuk) | `open` (siapa pun yang login dan punya kode);
  - jadwal `opens_at`/`closes_at`, `duration_minutes`, `pass_score`, `max_attempts`, `max_violations` (0 = hanya dicatat);
  - `result_mode`: `hidden` | `score` | `review` (pembahasan baru terbuka setelah `closes_at`);
  - acak soal/opsi; sumber `exam_question_set.take` (N acak per paket, null = semua).
- **Peserta ditunjuk** `exam_participants` (`label` mis. "Kandidat Best Mekanik", `notified_at`).
  Admin bisa memilih lewat cari nama/NRP, satu seksi, satu jabatan, atau tempel NRP (+ label) dari Excel.
- **Pemberitahuan**: `ExamService::pendingFor($user)` → kartu "Ujian untuk Anda" di beranda,
  plus pop-up otomatis **sekali per sesi login** (`session('exam_notice.{id}')`).
- **Alur peserta**:
  1. Lobi `/ujian/{exam}`: aturan + kolom kode.
  2. `start()` menyusun lembar acak (baris `exam_answers` dengan `position` dan `option_order` mis. `CADB`).
  3. Ruang `/ujian/sesi/{attempt}` (layout `exam`, tanpa navigasi).
  4. Autosave per pilihan, `sinkron` tiap 30 detik (detak jantung + jawaban offline + waktu tambahan dari admin).
  5. Pelanggaran saat keluar halaman > 1,5 detik.
  6. Kumpul → hasil `/ujian/hasil/{attempt}`.
- **Ruang ujian (`examRoom` di `resources/js/exam.js`)**:
  - satu soal per layar, ragu-ragu, daftar nomor, zoom gambar, ukuran huruf;
  - pintasan A–D/1–6 dan ← →;
  - **lanjut otomatis** 450 ms setelah memilih (bisa dimatikan; di soal terakhir pindah ke soal kosong atau membuka konfirmasi);
  - antrean offline di `localStorage` (`astoone-exam-{id}`).
- **Invarian keamanan**:
  - `paper()` tidak pernah memuat kunci;
  - nilai dihitung `grade()` di server;
  - `deadline_at = min(mulai + durasi, closes_at)`; jawaban diterima sampai deadline + `GRACE_SECONDS` (20);
  - dua ketukan "Mulai" bersamaan aman (unik `exam_id, user_id, attempt_no`);
  - soal yang pernah dijawab hanya dinonaktifkan, tidak dihapus.
- **Admin**:
  - pantau langsung (polling 8 detik `feed`) dengan aksi tambah waktu, kumpulkan, diskualifikasi/batalkan, hapus & izinkan ulang;
  - peringkat: percobaan terbaik per orang, nilai sama → waktu tercepat;
  - analisis butir: soal yang pengecohnya lebih dipilih daripada kunci ditandai "kunci perlu dicek", lalu bisa **Hitung ulang nilai**;
  - ekspor Excel (peringkat + matriks 1/0/- per soal);
  - layar proyektor `/admin/ujian/{id}/layar` (kode besar + QR ke `/ujian` + penghitung);
  - salin ujian, buka/tutup sekarang.

### 8.8 SSO "Masuk dengan AstoOne" untuk MekanikPintar (`SsoController`)
- Alur:
  1. LMS → `/sso/mekanikpintar?state=…&return=…` (`state` alfanumerik 20–80 karakter; `return` **harus sama persis**
     dengan `MEKANIKPINTAR_SSO_CALLBACK`).
  2. Login bila perlu.
  3. Layar persetujuan "Masuk ke MekanikPintar?".
  4. POST `approve` → redirect ke callback dengan `assertion` (base64url JSON) + `sig` (base64url HMAC-SHA256 dengan
     `MEKANIKPINTAR_SSO_SECRET`).
- Isi surat jalan: `aud=mekanikpintar`, `nrp`, `name`, `activated_at`, `iat`, `exp` (+90 detik), `nonce`, `state`. PIN tidak pernah dikirim.
- Persetujuan sekali pakai: data tunggu diambil dengan `session()->pull()` dan kedaluwarsa dalam 10 menit.
- Tersedia "Bukan saya" (keluar lalu login lagi dan kembali ke layar ini) dan "Batal" (`?error=cancelled`).
- Bila rahasia atau callback kosong, semua route mengembalikan 404.
- Sisi LMS (route `/login/astoone/kembali`) wajib memverifikasi tanda tangan, `aud`, `exp`, `state` (cocok dengan sesi
  browser), dan nonce belum pernah dipakai. Rahasianya harus sama dengan `ASTOONE_SSO_SECRET` di `.env` LMS.

### 8.9 Panel admin umum
- Layout `components/layouts/admin`. Sidebar berkelompok: Utama, Absensi, Poin HEBAT, Ujian Event, Konten, Sistem.
  Lencana langsung menampilkan jumlah absen dibuka dan ujian berlangsung.
- Topbar dengan jejak navigasi dan **palet perintah Ctrl+K** (`adminPalette`): cari menu, aksi cepat, cari pengguna/aplikasi/rekap/bank soal.
- Pengguna:
  - pencarian langsung (fragmen Blade `view()->fragment()`);
  - impor Excel `UserImporter` (kolom dikenali dari judul: NRP, Nama, Departemen/Seksi, Jabatan, No HP, Grade, GL wali,
    Lokasi, Program; pengguna `user` yang tidak ada di file dinonaktifkan);
  - ekspor dan template.
- Pengaturan (`settings`): `site_name`, `site_tagline`, `slogan`, `hero_image`, `campaign_section_title`, `mentari_greeting`,
  `points_approval_threshold`, nomor WA bantuan. Di-cache selamanya dan dibersihkan otomatis saat disimpan.
- Log aktivitas: `ActivityLog::record($aksi, $keterangan)` untuk tindakan penting (aktivasi, SSO, ujian, impor, dll.).

---

## 9. Skema database

| Migrasi | Tabel |
|---|---|
| `0001_01_01_000000` | `users` (nrp unik, name, department, position, phone, role, pin, is_active, activated_at, last_login_at, announcements_seen_at, remember_token), `sessions` |
| `0001_01_01_000001/2` | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` (bawaan Laravel) |
| `2026_11_18_000001` portal | `categories`, `apps`, `campaigns`, `campaign_clicks`, `announcements`, `app_visits`, `favorites`, `settings` (key-value), `mentari_faqs`, `activity_logs` |
| `2026_11_18_000002` | `users` + grade, supervisor, supervisor_nrp, location, program · `apps` + kolom pengetahuan (penjelasan, cara pakai, PIC) |
| `2026_11_19_000001` absensi | `attendance_types` (warna, ikon, track_fatigue, allow_speaker), `attendance_locations` (polygon JSON, buffer_m, center, area_m2), `attendance_schedules` (days JSON, open/close_time, late_after, location_ids, target_departments), `attendance_sessions` (date, shift, opens/closes_at, checkin_mode, code unik, secret, host, speaker, …), `attendance_records` (salinan nrp/nama/jabatan/seksi, role audience/pemateri, jam tidur, fatigue, is_late, method, geo_status, koordinat, device_id, note, checked_in_at, …) |
| `2026_11_20_000001` | `campaigns` + popup_frequency, popup_seconds, popup_ack, target_departments · `campaign_views` (views, first/last_seen, acknowledged_at, dismissed_at) |
| `2026_11_20_000002` poin | `users` + points_balance, points_earned · `point_transactions` (buku besar, idem_key unik), `point_rules`, `point_grants`, `point_grant_recipients`, `rewards`, `redemptions` |
| `2026_11_25_000001` | `mentari_logs` (user, pertanyaan, normalized, intent, source, provider, answered, helpful, ms, resolved_at) — dibersihkan 90 hari |
| `2026_11_24_000001` ujian | `question_sets`, `questions`, `exams`, `exam_question_set` (take), `exam_participants`, `exam_attempts` (status in_progress/submitted/disqualified, finish_reason user/timeout/violations/admin, deadline_at, total/answered/correct/score, violations + log), `exam_answers` (position, option_order, choice, is_correct, flagged, answered_at) |

Catatan: `exam_attempts.status` punya default di database. Saat membuat model, isi `status` secara eksplisit
(lihat §17), karena atribut default DB tidak ada di model yang baru di-`create()`.

---

## 10. Peta route

Semua route ada di `routes/web.php`, sekitar 174 route. Lihat dengan `php artisan route:list --except-vendor`.

| Area | Route penting |
|---|---|
| Tamu | `/masuk`, `/masuk/pin`, `/masuk/ganti-nrp`, `/aktivasi`, `/offline`, `/up` (health) |
| Portal | `/`, `/aplikasi`, `/buka/{app}`, `/aplikasi/{app}/favorit`, `/info`, `/info/{announcement}`, `/kampanye/{campaign}` (+ `/popup`, `/popup/tutup`), `/profil`, `/profil/pin`, `/mentari/chat`, `/keluar` |
| Absensi | `/absen`, `/absen/{code}`, `/absen/kode`, `/absen/lokasi`, `/absensi-saya` |
| Poin | `/poin`, `/poin/hadiah`, `/poin/hadiah/{reward}/tukar`, `/poin/kupon/{code}` |
| Ujian | `/ujian`, `/ujian/kode`, `/ujian/{exam}`, `/ujian/{exam}/mulai`, `/ujian/sesi/{attempt}` (+ `/jawab`, `/sinkron`, `/pelanggaran`, `/kumpulkan`), `/ujian/hasil/{attempt}` |
| SSO | `/sso/mekanikpintar` (GET/POST), `/batal`, `/ganti-akun` |
| Admin | `/admin`, `/admin/pengguna`, `/admin/aplikasi`, `/admin/kategori`, `/admin/kampanye`, `/admin/pengumuman`, `/admin/mentari`, `/admin/pengaturan`, `/admin/absensi/*` (sesi, jadwal, lokasi, jenis, rekap, poster-qr), `/admin/poin/*` (aturan, hadiah, kupon, pemberian, penyesuaian, riwayat), `/admin/ujian/*` (bank, impor, template, ekspor, feed, layar, peserta) |

Rate limit penting:
- kode ujian 12/menit, jawab 300/menit, sinkron dan pelanggaran 60/menit;
- SSO 30/menit (tampil) dan 20/menit (setuju);
- login memakai `RateLimiter` sendiri.

---

## 11. Konfigurasi `.env`

Hanya nama kunci. Nilai ada di server dan tidak boleh ditulis di repo, dokumen, atau chat.

| Kunci | Keterangan |
|---|---|
| `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` | standar Laravel (`APP_KEY` dibuat `key:generate`, jangan diganti setelah ada data) |
| `APP_URL` | `https://plantasto.my.id` |
| `APP_TIMEZONE` | zona jam absensi & ujian (bawaan `Asia/Jakarta`; ganti `Asia/Makassar` bila jobsite memakai WITA) |
| `DB_*` | database MySQL produksi (dari aaPanel) |
| `SESSION_SECURE_COOKIE` | `true` setelah HTTPS aktif |
| `ADMIN_NRP`, `ADMIN_NAME` | admin pertama untuk seeder |
| `MENTARI_ENDPOINT`, `MENTARI_TOKEN`, `MENTARI_TIMEOUT` | layanan AI Mentari lama (opsional) |
| `GROQ_API_KEY`, `GEMINI_API_KEY`, `OPENROUTER_API_KEY` | AI gratis Mentari (opsional; cukup Groq). Opsional juga `*_MODEL`, `*_DAILY_LIMIT`, `*_BASE_URL`, `MENTARI_AI_ORDER`, `MENTARI_AI_TIMEOUT`, `MENTARI_AI_PER_USER` — lihat `docs/mentari.md` |
| `LMS_DB_DATABASE`, `LMS_DB_USERNAME`, `LMS_DB_PASSWORD`, `SERT_DB_DATABASE` | koneksi read-only ke LMS & sertifikat (opsional; user `SELECT` saja, lihat `docs/DEPLOY.md` §8) |
| `MEKANIKPINTAR_SSO_SECRET` | rahasia SSO, sama persis dengan `ASTOONE_SSO_SECRET` di LMS (kosong = SSO mati) |
| `MEKANIKPINTAR_SSO_CALLBACK` | `https://mekanikpintar.com/login/astoone/kembali` |

Di `.env`, baris paling bawah yang dipakai bila ada nama ganda. **Ubah baris yang ada, jangan menambah duplikat.**
Setelah mengubah `.env`, jalankan `php artisan config:cache` (atau `config:clear`).

---

## 12. Deploy & operasional

Lokasi di server: `/www/wwwroot/plantasto.my.id` (aaPanel, PHP 8.3, Nginx root `/public`).

**Deploy pertama**: ikuti `docs/DEPLOY.md`. Repo di-clone dari cabang `main` (dulu `portal-v2`, kini sudah disatukan).

**Update rutin** (setelah PR di-merge):
```bash
cd /www/wwwroot/plantasto.my.id
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader     # bila composer.lock berubah
php artisan migrate --force                         # bila ada migrasi baru
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```
Bila hanya tampilan yang berubah, `git pull` + `php artisan optimize:clear && php artisan view:cache` sudah cukup.
Minta pengguna menekan Ctrl+F5 sekali bila aset tampak lama.

**Cron** (aaPanel → Cron, tiap 1 menit):
`cd /www/wwwroot/plantasto.my.id && php artisan schedule:run >> /dev/null 2>&1`.
Cron ini hanya dibutuhkan lampu status aplikasi dan pembersihan log. Absensi dan ujian tidak bergantung pada cron.

**Service worker**: bila `public/sw.js` diubah, naikkan `VERSION` (sekarang `astoone-v1`) agar HP mengambil versi baru.

**Cadangan**: database produksi + folder `storage/app/public` (gambar kampanye, ikon, hadiah, soal).

**Pemeriksaan pasca-deploy**:
- `/up` mengembalikan 200;
- masuk sebagai admin;
- buka beranda, panel, satu halaman absensi, dan `/admin/ujian`.

---

## 13. Integrasi dengan sistem lain

| Sistem | Hubungan |
|---|---|
| **MekanikPintar (LMS)** `mekanikpintar.com`, repo `mekanikpintar-lms` | AstoOne membaca data training/poin/achievement lewat koneksi `lms` read-only (`LmsDirectory`). SSO "Masuk dengan AstoOne" ke LMS (§8.8). Poin LMS **terpisah** dari Poin HEBAT. |
| **Sertifikat** `sertifikat.mekanikpintar.com` (Flask) | Data sertifikat dibaca lewat koneksi `sertifikat` read-only. |
| **Mentari AI** | Satu layanan AI di VPS bisa melayani AstoOne dan LMS (kontrak sama, dibedakan `context.app`). |
| **Ujian lama** `ppdasto.github.io/soal_ujian` + Apps Script `SYSTEM_SOAL` + Google Sheet `INTEGRATED_KUMPULAN_SOAL` | Digantikan modul Ujian Event. Soal dipindah dengan mengunduh sheet sebagai .xlsx lalu mengimpornya di Bank soal. Sistem lama punya celah (kunci jawaban & password admin terbuka di kode halaman), jadi **matikan setelah migrasi selesai**. |
| **Aplikasi lain di portal** (induksi, idcard, workshop/BANDARA, dll.) | Hanya ditautkan sebagai `AppLink`. Tidak ada integrasi data. |
| **WhatsApp** | Hanya tautan `wa.me` (tombol bantuan, "Salin untuk WA"). Belum ada pengiriman otomatis. |

---

## 14. Pengujian & QA

`php artisan test` memakai SQLite memori dan saat ini menjalankan 94 tes.

| File | Tes | Cakupan |
|---|---|---|
| `AuthTest` | 7 | aktivasi, PIN, kunci salah, intended redirect |
| `PortalTest` | 8 | beranda, aplikasi, favorit, kunjungan |
| `AdminTest` | 10 | gate, CRUD konten, impor pengguna |
| `UserSearchTest` | 3 | pencarian langsung pengguna |
| `MentariPeopleTest` | 9 | kartu karyawan, aturan lihat data, LMS tiruan |
| `MentariBrainTest` | 13 | bahasa chat, ingatan, ujian tanpa bocor kode, pengetahuan, log → FAQ, 👍/👎, AI dengan alat (Http::fake), fallback 429, privasi Gemini, coba ulang tanpa alat, kuota per orang, saklar admin, tamu tanpa AI |
| `Unit/MentariLanguageTest` | 3 | normalisasi, nama tidak dikoreksi, jarak Damerau |
| `AttendanceTest`, `AttendanceGeoTest` | 10 + 11 | jadwal rutin, mode sesi, geofence, 1 HP 1 NRP, rekap |
| `PopupTest` | 5 | frekuensi, wajib baca, target seksi |
| `PointsTest` | 7 | buku besar, idempoten, penukaran, persetujuan |
| `ExamTest` | 19 | kunci tak bocor, kode, peserta ditunjuk, jadwal, skor server, offline, batas waktu, pelanggaran, lanjut, privasi, notifikasi, mode hasil, impor sheet lama & xlsx, alur admin, hitung ulang, peringkat |
| `SsoMekanikPintarTest` | 4 | login → kembali, tanda tangan, callback asing, batal/ganti akun |
| `Unit/SpreadsheetTest` | 1 | baca/tulis XLSX |

**QA visual lokal**, cara yang dipakai selama pengembangan:
1. Siapkan database SQLite terpisah, mis. `DB_DATABASE=/tmp/qa/x.sqlite php artisan migrate:fresh --seed`,
   lalu buat data contoh lewat skrip PHP yang mem-bootstrap Laravel.
2. Jalankan `DB_DATABASE=… php artisan serve --port=8123`.
3. Ambil screenshot dengan Playwright (Chrome headless) di 390×844 (HP, `is_mobile`) dan 1440×900 (desktop).
   Login cukup dengan mengisi `#nrp`, Enter, lalu menekan 6 digit PIN dengan keyboard.
4. Simpan skrip QA **di luar** folder proyek dan jalankan Python dengan `-I`. Nama file seperti `queue.py`
   pernah menimpa modul standar Python.
5. Periksa juga konsol browser agar tidak ada error.

---

## 15. Konvensi kode & desain

**Kode**
- Penamaan route dan URL berbahasa Indonesia (`/masuk`, `/absen`, `/poin/hadiah`, `/admin/ujian/bank`),
  sedangkan nama route dan kelas berbahasa Inggris (`exams.room`, `ExamService`).
- Validasi di controller (`$request->validate`) dengan pesan Indonesia. Logika bisnis di Service.
  Error bisnis memakai exception khusus (`PointException`, `ExamException`).
- Komponen Alpine didaftarkan di `alpine:init` per modul JS (`exam.js`, `attendance.js`, `geo.js`, `mentari.js`)
  dan diimpor `app.js` sebelum `Alpine.start()`.
- Excel: baca dengan `Spreadsheet::read($path, $ext, $preferSheet)`, tulis dengan `Spreadsheet::writeXlsx($path, $rows, $sheet, $widths)`.
  Importer selalu mengenali kolom dari judulnya, bukan dari posisi.
- Gambar: `ImageService` → `storeCover` (dipotong sesuai rasio), `storeFit` (diperkecil tanpa dipotong, latar putih untuk diagram),
  `storeIcon` (ikon persegi), dan `delete`.
- Tindakan penting dicatat dengan `ActivityLog::record()`.

**Desain** (palet "pit malam" di `resources/css/app.css` `@theme`)
- Latar hijau-hitam (`pit`, `bench`, `ridge`, `seam`), teks `ink`/`fog`/`mist`, aksen **neon `#3dff8e`** (aksi utama, sukses),
  **hi-vis `#ffc23d`** (perhatian, kompetisi/ujian), `alarm` (bahaya).
- Motif: garis kontur bench tambang (`<x-contour>`). Font Plus Jakarta Sans + Chakra Petch (angka, judul, kode).
- Komponen CSS: `.btn-neon`, `.btn-hivis`, `.btn-ghost`, `.btn-wa`, `.field`, `.label`, `.error`, `.chip` (+ varian),
  `.a-card`, `.a-th`/`.a-td`, `.icon-btn`, `.section-title`, `.exam-opt`, `.nav-cell`, `.code-cell`.
- HP: target sentuh ≥ 44 px, `env(safe-area-inset-*)`, efek hover hanya di `@media (hover: hover)`.
- Desktop ≥ `lg`: portal memakai `partials/desktop-nav` + `desktop-footer`, dan tabbar disembunyikan.
- Hormati `prefers-reduced-motion`.
- Dropdown `select.field` memakai latar padat agar daftar opsi terbaca (lihat §17).

---

## 16. Alur kerja Git & PR

1. `git fetch origin && git checkout -b nama-cabang-deskriptif origin/main`.
2. Ubah kode, tambah/ubah tes, lalu `php artisan test`. Bila ada perubahan tampilan, `npm run build`.
3. Commit berbahasa Indonesia (baris judul ringkas + badan berisi poin perubahan).
4. `git push -u origin nama-cabang`, lalu buat PR ke `main` dengan deskripsi berisi Ringkasan, Deploy (migrasi atau tidak),
   dan Tes. Berikan tautan PR ke pemilik.
5. Setelah PR di-merge, perubahan berikutnya **wajib cabang & PR baru**.
6. Patch dari sesi lain bisa diterapkan dengan `git am file.patch` di cabang baru dari `origin/main`,
   lalu tes, build, dan PR seperti biasa (begitulah PR #12 masuk).

---

## 17. Jebakan yang pernah ditemui

| Gejala | Penyebab | Solusi |
|---|---|---|
| Field bertingkat hilang setelah `validate()` | `validate()` hanya mengembalikan key yang punya aturan | Beri aturan untuk tiap key bertingkat, atau ambil dengan `$request->input()` |
| String kosong jadi `null` (juga di JSON) | Middleware `ConvertEmptyStringsToNull` | Tangani `null`. Pakai nilai penanda bila "kosong" punya arti |
| `hasOne()->where()->latestOfMany()` mengabaikan `where` | Agregat `latestOfMany` tidak membawa kondisi | `ofMany(['id' => 'max'], fn ($q) => $q->where(...))` |
| Model baru `create()` lalu `status` bernilai `null` | Default kolom ada di database, bukan di model | Isi kolom default secara eksplisit saat `create()` (contoh: `ExamService::start`) |
| `round()` menghasilkan float sehingga `assertSame` gagal | Tipe PHP | Cast `(int) round(...)` |
| Class Tailwind baru tidak berpengaruh di server | Aset tidak di-build ulang | `npm run build` lalu commit `public/build` |
| Teks dropdown tak terbaca di Chrome Windows/Android | Daftar `<option>` memakai latar `select` yang transparan | Latar padat untuk `select.field` dan `option` (PR #10) |
| Pilihan di soal berikutnya tampak tersorot di HP | `:hover` "menempel" setelah sentuhan | Hover hanya di `@media (hover: hover)` (PR #11) |
| Kamera/GPS tidak berjalan | `Permissions-Policy` memblokir, atau bukan HTTPS | `camera=(self), geolocation=(self)` + HTTPS |
| HP masih memakai aset lama | Service worker menyimpan cache | Naikkan `VERSION` di `public/sw.js` |
| Navigasi hanya hash (`#peringkat`) tidak mengganti tab | Dokumen tidak dimuat ulang | Dengarkan `hashchange` (sudah dipasang di halaman pantau ujian) |
| Setelan `.env` tidak berubah | Ada baris ganda (yang paling bawah menang), atau config ter-cache | Ubah baris yang ada, lalu `config:cache` |
| `.user.ini` aaPanel tidak bisa dihapus | Dikunci atribut immutable | `chattr -i .user.ini` (hati-hati, hanya saat deploy pertama) |
| Dua soal dengan teks sama dianggap duplikat saat impor | Sidik hanya dari teks | Sidik = teks + gambar + opsi (sudah diperbaiki) |
| PR ditolak karena isi deskripsi | Deskripsi memuat password/rahasia | Jangan pernah menyalin rahasia ke commit, PR, atau dokumen |

---

## 18. Keamanan & privasi data

- **Rahasia** hanya di `.env` server: DB, `APP_KEY`, `MENTARI_TOKEN`, password user LMS read-only, `MEKANIKPINTAR_SSO_SECRET`.
  Bila rahasia pernah terlihat di chat, log, atau tangkapan layar, **ganti** nilainya di server.
- **PIN** di-hash, tidak pernah ditampilkan atau dikirim ke sistem lain (termasuk SSO).
- **Data karyawan**: diimpor dari Excel manpower. No HP hanya untuk Administrator. Riwayat training hanya untuk diri sendiri, GL wali, dan Administrator.
  Data kesehatan tidak disimpan, kecuali jam tidur absensi untuk status fatigue (keselamatan kerja).
- **Foto karyawan** tidak di-commit ke repo.
- **LMS**: akses `SELECT` saja ke kolom yang perlu. Kolom password dan email LMS tidak diberi izin.
- **Ujian**: kunci tidak keluar dari server, nilai dan waktu di server, lembar jawaban privat (404 untuk orang lain),
  dan pembahasan baru terbuka setelah ujian ditutup.
- **Poin**: buku besar tidak dapat diubah, idempoten, persetujuan empat mata untuk pemberian besar.
- **SSO**: callback dicocokkan persis, HMAC-SHA256, kedaluwarsa 90 detik, nonce, dan `state` terikat ke browser.
- **Absensi**: identitas dari akun, geofence, satu HP satu NRP. Keterbatasan Fake GPS sudah diketahui (lihat §8.5).
- Header keamanan dari `SecurityHeaders`. Cookie sesi `secure` di produksi.

---

## 19. Pekerjaan lanjutan & ide

Belum dikerjakan. Urutan tidak mengikat; tanyakan prioritas ke pemilik.

**Ujian Event**
- Impor riwayat hasil lama dari sheet `Hasil` (± 900 baris) sebagai arsip.
- Poin HEBAT otomatis untuk peserta yang lulus atau menjadi juara.
- Matikan atau alihkan `ppdasto.github.io/soal_ujian` dan Apps Script `SYSTEM_SOAL` setelah soal dipindah.
- Opsional: mengunduh gambar soal eksternal ke server (`ImageService`) agar tidak bergantung GitHub.

**Mentari**
- Isi `GROQ_API_KEY` di server untuk menghidupkan AI gratis (lihat `docs/mentari.md`).
- Rutin buka Panel → Mentari: ubah pertanyaan yang belum terjawab menjadi FAQ, lengkapi Penjelasan/Cara pakai/PIC aplikasi.
- Ide lanjutan: pengetahuan dari dokumen (SOP/shop manual ringkas) ke `KnowledgeBase`; satu layanan AI untuk LMS juga.

**Absensi**
- Notifikasi WhatsApp otomatis (pemilik punya akun Fonnte), mis. pengingat P5M atau yang belum hadir.

**Poin**
- Ide e-wallet/voucher bernilai pernah dibahas. Bila dihidupkan, butuh kajian aturan perusahaan.

**SSO**
- Memastikan sisi LMS memverifikasi nonce sekali pakai dan `state`.
- Bila diperlukan, menambah sistem lain (induksi, idcard) dengan pola yang sama.

**Operasional**
- Pertimbangkan cadangan otomatis database + `storage/app/public`.

---

## 20. Checklist untuk developer / agent baru

1. Baca §1 (aturan emas), `CLAUDE.md`, lalu dokumen modul yang relevan di `docs/`.
2. `composer install`, `npm install`, salin `.env.example` → `.env`, `php artisan key:generate`,
   lalu `php artisan migrate --seed` (SQLite lokal juga bisa).
3. `php artisan test`: pastikan semua tes hijau (110 per PR #14) sebelum mengubah apa pun.
4. Buat cabang baru dari `origin/main`. Jangan bekerja di `main`.
5. Setelah perubahan: tes, `npm run build` bila ada perubahan tampilan, QA di HP 390 px dan desktop 1440 px.
6. Commit berbahasa Indonesia, push, buat PR, dan berikan tautannya ke pemilik.
7. Tulis langkah deploy di PR (migrasi? `.env` baru? cukup `git pull`?).
8. Jangan menjalankan apa pun di server produksi tanpa pemilik. Berikan perintah langkah demi langkah agar ia yang menjalankannya.
9. Perbarui dokumen ini (§3 riwayat, §9 skema, §19 backlog) bila menambah modul atau keputusan baru.

---

## 21. Glosarium

| Istilah | Arti |
|---|---|
| **AstoOne** | Nama aplikasi portal ini |
| **Plant ASTO** | Departemen Plant di jobsite ASTO, PT Kalimantan Prima Persada (KPP) |
| **NRP** | Nomor induk karyawan (mis. format `KTxxxxx`), dipakai sebagai username |
| **PIN** | 6 angka rahasia milik karyawan untuk masuk AstoOne |
| **GL** | Group Leader. "GL wali" = atasan pembina seorang mekanik (`supervisor_nrp`) |
| **SHE** | Safety, Health & Environment. Admin SHE mengelola kampanye/pengumuman |
| **P5M** | Briefing keselamatan rutin awal shift |
| **Safety Talk** | Pertemuan keselamatan mingguan (Sabtu) |
| **Fatigue** | Status kelelahan dari jam tidur: Fit to Work / Coaching atasan / Fatigue risk |
| **Geofence / area GPS** | Poligon lokasi sah untuk absen (WS 25, Quarry, Crusher) |
| **Poin HEBAT** | Poin reward tertutup AstoOne |
| **Ujian Event** | Ujian kompetisi/event (Best Mekanik, Best GL, pre/post test) |
| **Paket soal** | Kelompok soal di bank soal (dulu kolom `KodeUjian` di Google Sheet) |
| **Mentari** | Maskot & asisten chat (juga ada di LMS) |
| **MekanikPintar / LMS** | Learning Management System training mekanik (`mekanikpintar.com`) |
| **SSO** | Single sign-on: masuk ke LMS memakai akun AstoOne |
| **IDP / MAP / ATMP** | Program pengembangan mekanik / rencana training tahunan (konteks LMS) |
| **aaPanel** | Panel hosting di VPS (situs, database, SSL, cron) |
| **PWA** | Progressive Web App: aplikasi web yang bisa dipasang dari Chrome |
