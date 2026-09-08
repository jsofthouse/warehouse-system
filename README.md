# Sistem Gudang & Distribusi Alkap Pertanian

Aplikasi internal untuk mengelola distribusi **Alat Kelengkapan (Alkap) Pertanian** — 17 jenis barang yang disalurkan ke puluhan titik lokasi binaan Kodim di beberapa provinsi.

Barang tidak dikirim langsung dari vendor ke lokasi, tapi singgah dulu di gudang:

```
VENDOR / SUPPLIER  ──►  GUDANG (Semarang)  ──►  LOKASI KODIM (desa binaan)
      bertahap                                     bertahap, multi-truk
                        ▲
                        └── masa endap ditagihkan sebagai sewa gudang
```

Karena barang masuk bertahap dan keluar bertahap ke puluhan tujuan, tantangan utamanya bukan sekadar cetak dokumen, melainkan menjaga akurasi stok dan menjawab dua pertanyaan setiap saat: lokasi mana sudah dapat apa dan kurang apa, serta berapa biaya sewa gudang yang harus ditagihkan.

## Yang dikerjakan aplikasi ini

- Mencatat barang masuk dari vendor secara akurat dan dapat diaudit
- Menerbitkan surat jalan per pengiriman (satu lokasi bisa dikirim bertahap, multi-truk)
- Melacak sisa kebutuhan tiap lokasi sampai distribusinya tuntas
- Menghitung dan menerbitkan invoice sewa gudang berbasis kg-hari per periode
- Kartu stok dan snapshot stok harian sebagai basis perhitungan sewa
- Laporan rekap distribusi, sisa kebutuhan, dan biaya sewa
- Cetak surat jalan, bukti penerimaan, dan invoice ke PDF

## Peran pengguna

| Role | Lokasi | Bisa apa |
|---|---|---|
| **Super Admin** | Pusat / IT | Akses penuh, kelola user & konfigurasi |
| **Operator Pusat** | Jakarta | Master data, tarif sewa, invoice, monitoring semua gudang |
| **Operator Gudang** | Semarang, dll | Barang masuk & surat jalan, terbatas ke gudangnya sendiri |
| **Manajemen / Viewer** | Mana saja | Baca saja — dashboard dan laporan |

Operator gudang cuma bisa melihat dan menyentuh data gudangnya sendiri — pembatasan ini diterapkan di level query, bukan cuma disembunyikan di tampilan, supaya tidak ada operator yang tanpa sengaja merusak stok gudang lain.

## Alur singkat

1. **Setup** — Operator Pusat input master gudang, lokasi tujuan, 17 item, alokasi kebutuhan per lokasi, dan tarif sewa.
2. **Barang masuk** — Operator Gudang mencatat tiap kedatangan fisik dari vendor sebagai dokumen Penerimaan (draft dulu, posting kemudian baru mengubah stok).
3. **Barang keluar** — Operator Gudang membuat surat jalan per pengiriman ke satu lokasi; sistem menyarankan jumlah kirim berdasarkan sisa kebutuhan dan stok yang tersedia.
4. **Invoice sewa gudang** — di akhir periode, sistem menghitung akumulasi kg-hari per item dari snapshot stok harian dan mengalikannya dengan tarif yang berlaku.
5. **Penyelesaian** — status surat jalan diperbarui sampai seluruh alokasi lokasi tersebut terkirim.

## Skala data

- 17 jenis item, ±104 unit per lokasi
- Data awal: 32 lokasi (Jawa Barat & Jawa Tengah), jumlah total masih bisa bertambah
- ±48 m³ volume per lokasi dari item yang sudah berdimensi → tiap lokasi butuh 2–3 rit truk tronton

## Tech stack

- **Backend:** PHP 8.3+ / Laravel
- **Database:** MySQL / PostgreSQL
- **Frontend:** Blade + Bootstrap 5 (Tabler) + Alpine.js — aplikasi internal, sengaja tanpa SPA
- **Cetak PDF:** DomPDF
- **Scheduler:** Laravel Task Scheduling (snapshot stok harian)

Baseline keamanan yang wajib sejak Fase 1: password di-hash, RBAC di middleware *dan* query, scoping `gudang_id` dipaksakan di level query untuk semua transaksi, validasi server-side penuh, proteksi CSRF, HTTPS wajib, rate limiting login, dan log aktivitas untuk semua posting/pembatalan.

## Status pengembangan

Master data dan autentikasi sudah jadi. Modul transaksi (Barang Masuk, Surat Jalan, Invoice) sedang dibangun bertahap, dimulai dari **Barang Masuk**.

| Fase | Isi | Status |
|---|---|---|
| 1 — MVP | Auth & RBAC, master data, barang masuk, surat jalan + PDF, kartu stok, stok harian, tarif, invoice + PDF, laporan dasar | Sedang berjalan |
| 2 — Pendukung | Dashboard, import Excel, export laporan, packing list & kubikasi | Belum |
| 3 — Opsional | BAST, upload bukti terima, tampilan mobile, notifikasi | Belum |

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

## Menjalankan test

```bash
php artisan test
```

## Setup cron di hosting (scheduler)

Aplikasi punya satu scheduled command, `stok:hitung-harian`, didaftarkan di
`routes/console.php` — jalan tiap hari jam 23:55 (snapshot stok akhir hari,
basis perhitungan invoice sewa gudang). Laravel Task Scheduling cuma butuh
**satu** cron job yang mengecek jadwal tiap menit, bukan cron terpisah per
command.

### Cron job yang perlu dibuat di cPanel

cPanel → **Cron Jobs** → **Add New Cron Job**:

- **Common Settings:** `Once Per Minute (* * * * *)`
- **Command:**

  ```
  cd /home/ISI_USERNAME_CPANEL/ISI_PATH_APLIKASI && /usr/local/bin/ea-phpXX artisan schedule:run >> /dev/null 2>&1
  ```

  Ganti tiga placeholder:
  - `ISI_USERNAME_CPANEL` — username akun cPanel di hosting.
  - `ISI_PATH_APLIKASI` — path folder aplikasi Laravel ini di server (biasanya
    DI LUAR `public_html`, dengan document root subdomain di-set/symlink ke
    folder `public/` di dalamnya).
  - `ea-phpXX` — path binary PHP CLI. Konvensi cPanel + EasyApache 4 (paling
    umum sekarang): `/usr/local/bin/ea-php83` (angka sesuai versi PHP yang
    dipilih di MultiPHP Manager — app ini butuh PHP 8.3+). Kalau hostingnya
    masih pakai CloudLinux alt-php lama, formatnya beda:
    `/opt/alt/php83/usr/bin/php`. **Cek dulu ke provider hosting / MultiPHP
    Manager di cPanel, path mana yang benar-benar tersedia** — jangan pakai
    `php` polos di cron, itu sering resolve ke PHP CGI atau versi default
    server yang belum tentu 8.3+.

### Catatan penting

- **Jangan bikin cron terpisah untuk `stok:hitung-harian`.** Cron di atas
  cuma manggil `schedule:run` tiap menit; Laravel sendiri yang tahu jam
  23:55 lewat definisi di `routes/console.php`.
- `stok:hitung-harian` didaftarkan dengan `->onOneServer()`, yang butuh
  cache store dengan atomic lock (`database`, `redis`, atau `memcached`) —
  `.env.production` sudah diset `CACHE_STORE=database`, jangan diganti ke
  `file`/`array` di hosting atau lock-nya tidak berfungsi.
- Untuk debug kalau scheduler kelihatan tidak jalan, ganti sementara
  `>> /dev/null 2>&1` jadi `>> /home/ISI_USERNAME_CPANEL/schedule.log 2>&1`,
  cek isinya, lalu kembalikan ke `/dev/null` setelah beres — log yang
  menumpuk tiap menit bisa cepat membesar kalau dibiarkan.
