# Mentari di AstoOne — kontrak layanan AI

Mentari di AstoOne memakai **kontrak yang sama** dengan Mentari di MekanikPintar
(`docs/mentari-ai.md` di repo mekanikpintar-lms), sehingga satu layanan AI di VPS
bisa melayani keduanya. Bedakan sumbernya lewat `context.app = "astoone"`.

## Mengaktifkan

```
MENTARI_ENDPOINT=http://127.0.0.1:8787/chat
MENTARI_TOKEN=isi-rahasia-panjang
MENTARI_TIMEOUT=25
```

Lalu `php artisan config:cache`. Tanpa endpoint, Mentari memakai otak bawaan
(`App\Services\Mentari\MentariGuide`): mencari aplikasi, pengumuman, kampanye,
jawaban siap pakai dari menu **Admin → Mentari**, dan bantuan PIN/NRP lewat WhatsApp.

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

- `reply` wajib, maks 4000 karakter. `actions` maks 3.
- Demi keamanan hanya tautan relatif (`/…`), tautan ke `APP_URL`, dan `https://wa.me/…` yang diteruskan.
  Untuk membuka aplikasi, pakai `url` dari `context.apps` (`/buka/{id}`) agar kunjungan tercatat.
- Gagal / kosong / lewat waktu → otak bawaan menjawab.
