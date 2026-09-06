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
