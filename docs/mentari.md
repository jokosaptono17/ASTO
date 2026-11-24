# Mentari di AstoOne

## Cara berpikir (MentariBrain)

Setiap pertanyaan melewati lapis demi lapis — yang paling pasti & gratis didahulukan:

| Lapis | Kelas | Isi |
| --- | --- | --- |
| 0. Bahasa | `Services\Mentari\Language` | Singkatan chat → kata baku ("brp poin sy" → "berapa poin saya"), huruf berulang, koreksi salah ketik Damerau ke kosakata AstoOne (kata umum & nama orang tidak dikoreksi; pencarian nama memakai `lite()`), akhiran "-nya" dipisah ("ujiannya" → "ujian nya"). |
| 1. Ingatan | `Memory` (sesi, 15 menit) | Pertanyaan lanjutan: "training dia?", "GL walinya siapa?", "cara pakainya?" ditulis ulang dengan subjek terakhir (kartu orang / aplikasi). |
| 2. Data asli | `MentariGuide` + `PeopleDesk`, `PointsDesk`, `AttendanceDesk`, `ExamDesk` | Kartu karyawan, poin, absensi, ujian (kode ujian **tidak** pernah diberikan ke peserta), aplikasi, statistik. Pertanyaan "bagaimana/kenapa" sengaja dilewatkan ke lapis berikutnya. |
| 3. Pengetahuan | `KnowledgeBase` (BM25, cache 5 menit) | 15 panduan bawaan (`GUIDES`) + FAQ admin + penjelasan aplikasi + pengumuman + kampanye. Dijawab langsung bila cocok kuat. |
| 4. AI gratis | `Ai\Agent` + `Ai\Providers` + `Ai\LlmClient` | Groq → Gemini → OpenRouter (format OpenAI). AI mengambil data lewat **alat** (tool calling) ke otak bawaan, jadi angka/nama selalu dari database & aturan lihat data tetap berlaku. |
| 5. Layanan lama | `MENTARI_ENDPOINT` | Kontrak lama di bawah, bila diatur. |
| 6. Cadangan | — | Maaf + tombol WA admin (+ saran aplikasi yang tersinggung). Pertanyaan dicatat untuk admin. |

Pertanyaan berstruktur (lapis 0–3) **tidak pernah** memakai kuota AI. Tamu (belum login) tidak pernah memakai AI.

## AI gratis

`.env` (cukup satu; urutan bisa diubah `MENTARI_AI_ORDER=groq,gemini,openrouter`):

```
GROQ_API_KEY=          # console.groq.com → API Keys (tanpa kartu kredit)
GEMINI_API_KEY=        # aistudio.google.com → Get API key (opsional)
OPENROUTER_API_KEY=    # openrouter.ai → Keys (opsional, 50 permintaan/hari)
```

Opsional: `GROQ_MODEL` (bawaan `openai/gpt-oss-120b`), `GEMINI_MODEL` (bawaan `gemini-flash-latest`),
`OPENROUTER_MODEL` (bawaan `openrouter/free`), `*_DAILY_LIMIT` (perkiraan kuota harian yang dihitung AstoOne),
`*_BASE_URL`, `MENTARI_AI_TIMEOUT` (20 dtk), `MENTARI_AI_PER_USER` (25 pertanyaan AI/orang/hari, bisa diubah di panel).
Setelah mengubah `.env`: `php artisan config:cache`. **Jangan pernah mengirim kunci API lewat chat.**

Pengaman:
- **Privasi**: hanya penyedia `personal: true` (Groq — tidak melatih model dengan data API) yang menerima nama, seksi,
  dan hasil alat pribadi (poin, absensi, ujian, data karyawan, training). Gemini & OpenRouter versi gratis hanya
  menerima pengetahuan umum; NRP dan nomor HP di pertanyaan/riwayat disamarkan, jawaban Mentari sebelumnya tidak dikirim.
  No HP tidak pernah dikirim ke penyedia mana pun.
- **Kuota**: batas per orang per hari (`mentari_ai_per_user`), hitungan per penyedia per hari, jawaban umum di-cache 6 jam.
- **Gangguan**: 429 → jeda ≥ 10 menit; 401/403/404 (kunci/model salah) → jeda 1 jam; 5xx → 3 menit; lalu penyedia berikutnya.
  Model yang menolak alat/parameter (400) dicoba ulang sekali tanpa alat. Sebabnya tampil di panel admin.
- **Saklar**: Panel → Mentari → AI gratis (hidup/mati + batas per orang).

## Panel admin (Panel → Mentari)

Alur berpikir & statistik 7 hari · status tiap penyedia (siap / jeda / kuota / kesalahan terakhir) ·
**pertanyaan yang belum terjawab** (dikelompokkan, tombol *Buat jawaban* mengisi formulir FAQ dan menandai beres) ·
jawaban yang dinilai 👎 · FAQ · uji jawaban (boleh memakai AI).

Jejak pertanyaan disimpan di `mentari_logs` (pertanyaan, sumber, penyedia, 👍/👎) dan dihapus otomatis setelah 90 hari.

---

# Kontrak layanan AI lama (MENTARI_ENDPOINT)

Mentari di AstoOne memakai **kontrak yang sama** dengan Mentari di MekanikPintar
(`docs/mentari-ai.md` di repo mekanikpintar-lms), sehingga satu layanan AI di VPS
bisa melayani keduanya. Bedakan sumbernya lewat `context.app = "astoone"`.

## Mengaktifkan

```
MENTARI_ENDPOINT=http://127.0.0.1:8787/chat
MENTARI_TOKEN=isi-rahasia-panjang
MENTARI_TIMEOUT=25
```

Lalu `php artisan config:cache`.

## Pembagian tugas

Pertanyaan berstruktur **selalu** dijawab otak bawaan dari data asli (tidak dikirim ke AI):

| Intent | Contoh | Sumber |
| --- | --- | --- |
| `people` | `KT16042`, "siapa ikhsanudin", "data saya", "GL wali saya", "mekanik asuh saya", "training saya", "training KT16042" | tabel `users` AstoOne + LMS (`LmsDirectory`) |
| `appKnowledge` | "apa itu Monata", "cara login Monata", "siapa admin Monata" | kolom Penjelasan / Cara pakai / PIC di Admin → Aplikasi |
| `stats` (admin) | "statistik portal", "siapa yang belum aktivasi di BUSER", "kampanye paling banyak diklik" | database AstoOne |
| `account`, `install`, `announcements`, `campaigns`, `faq`, `findApp` | "lupa PIN", "info terbaru", "cari shop manual" | database AstoOne |

Hanya intent `fallback`, `greeting`, `identity`, dan `thanks` (obrolan bebas) yang diteruskan ke
`MENTARI_ENDPOINT` bila diatur, dan hanya bila pengetahuan & AI gratis tidak menjawab lebih dulu.

### Aturan lihat data karyawan

- Semua yang login: nama, NRP, seksi, jabatan, grade, GL wali, lokasi, program.
- Riwayat training, poin, sertifikat: orangnya sendiri, GL walinya (`supervisor_nrp`), dan Administrator.
- No HP, status akun, login terakhir: hanya Administrator.

## Permintaan

`POST {MENTARI_ENDPOINT}` · `Authorization: Bearer {MENTARI_TOKEN}`

```json
{
  "message": "di mana shop manual komatsu?",
  "history": [{"role": "user", "content": "…"}, {"role": "assistant", "content": "…"}],
  "context": {
    "app": "astoone", "role": "user | she | admin | guest", "page": "home",
    "now": "2026-11-18 08:45", "today_label": "Rabu, 18 Nov 2026", "greeting": "Selamat pagi",
    "name": "AHMAD FAUZI", "first_name": "Ahmad", "nrp": "KT17163", "department": "PLANT", "position": "MEKANIK",
    "favorites": ["Shop Manual"],
    "apps": [{"id": 7, "name": "Shop Manual", "tagline": "…", "category": "Dokumen & Manual", "url": "/buka/7", "status": "up"}],
    "announcements": [{"title": "…", "level": "penting", "date": "2026-11-18", "summary": "…", "url": "/info/3"}],
    "campaigns": [{"title": "Bulan K3", "subtitle": null, "url": "/kampanye/2"}]
  },
  "help_whatsapp": "6281313333315",
  "app_url": "https://plantasto.my.id"
}
```

Tamu (halaman masuk) hanya mengirim `app`, `role: guest`, waktu, dan `page`.

## Balasan

```json
{ "reply": "Shop manual Komatsu ada di aplikasi Shop Manual.", "actions": [{ "label": "Buka Shop Manual", "href": "/buka/7" }] }
```

Balasan `/mentari/chat` ke browser juga membawa `cards` (kartu `person`, `stats`, `list`) dan
`intent`. Aksi bisa berupa tautan (`href`) atau pertanyaan lanjutan (`ask`) yang dikirim ulang ke Mentari.
Layanan AI cukup mengembalikan `reply` dan `actions`.

- `reply` wajib, maks 4000 karakter. `actions` maks 3.
- Demi keamanan hanya tautan relatif (`/…`), tautan ke `APP_URL`, dan `https://wa.me/…` yang diteruskan.
  Untuk membuka aplikasi, pakai `url` dari `context.apps` (`/buka/{id}`) agar kunjungan tercatat.
- Gagal / kosong / lewat waktu → otak bawaan menjawab.
