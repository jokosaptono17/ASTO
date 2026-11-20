# Poin HEBAT

Poin reward **tertutup**: tidak bisa dibeli, dicairkan menjadi uang, atau ditransfer antar karyawan —
hanya bisa ditukar dengan hadiah yang disediakan tim admin. Terpisah dari poin LMS (mekanikpintar.com).

## Karyawan (`/poin`)
- Dompet: saldo, level (Rookie → Skilled 250 → Expert 1.000 → Master 3.000 → Legend 7.500, dari total poin seumur hidup),
  poin bulan ini, kupon aktif, peringkat bulan ini.
- Riwayat poin (semua / masuk / keluar), cara dapat poin, papan peringkat.
- Katalog hadiah (`/poin/hadiah`): tukar dengan **PIN** → kupon QR (`/poin/kupon/{kode}`) → ditunjukkan ke admin.
- Kartu Poin HEBAT di beranda; Mentari: "poin saya", "hadiah apa saja".

## Poin otomatis (Panel → Poin HEBAT → Riwayat & aturan → Aturan poin)
| Aturan | Bawaan |
| --- | --- |
| Hadir P5M / safety talk / event | +5 |
| Menjadi pemateri | +20 |
| Hadir 7 hari beruntun (tiap kelipatan 7) | +25 |

Jam tidur **tidak** memengaruhi poin (agar karyawan tetap jujur soal fatigue). Bila host menghapus kehadiran,
poin dari kehadiran itu dicabut otomatis.

## Pemberian poin event
Panel → Pemberian poin → **Pemberian baru**: judul, penerima (cari nama/NRP, satu seksi sekaligus, atau tempel
kolom NRP · poin · catatan dari Excel).
- Administrator dengan total ≤ batas (bawaan 500 poin) → langsung terkirim.
- Selebihnya, dan semua pengajuan Admin SHE → **menunggu persetujuan Administrator**. Pengajuan sendiri harus
  disetujui Administrator lain (kecuali hanya ada satu Administrator aktif).

## Penyerahan hadiah
Panel → Kupon & penyerahan: scan QR kupon (kamera di panel, atau kamera HP biasa langsung membuka halaman
penyerahan) / ketik kode → cocokkan nama & NRP dengan ID card → **Serahkan hadiah**. Kupon yang dibatalkan
mengembalikan poin dan stok.

## Audit
Semua perubahan saldo tercatat di buku besar `point_transactions` (tidak pernah diubah/dihapus) dengan kunci
idempoten agar tidak ada poin ganda. Penyesuaian manual hanya oleh Administrator, wajib alasan. Ekspor Excel
tersedia di Riwayat poin.
