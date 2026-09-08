# Sistem Gudang & Distribusi Alkap Pertanian

Konteks proyek untuk sesi coding / chat lanjutan. Baca file ini lebih dulu sebelum
mengerjakan apa pun di repo ini.

---

## 1. Ringkasan proyek

Sistem pergudangan dan distribusi **Alat Kelengkapan (Alkap) Pertanian** untuk program
penyaluran ke titik-titik binaan Kodim di beberapa provinsi.

Alur fisik barang:

```
VENDOR  ──►  GUDANG (Semarang)  ──►  LOKASI KODIM (desa binaan)
        bertahap              bertahap, multi-truk
```

Selama barang mengendap di gudang, ada **biaya sewa gudang** yang ditagihkan lewat
invoice dengan basis **Rp per kg per hari**.

Tiga keluaran utama sistem:

1. Pencatatan barang masuk yang akurat dan dapat diaudit
2. **Surat jalan** per pengiriman (permintaan awal klien)
3. **Invoice sewa gudang** per periode

## 2. Status

Terakhir disegarkan 8 September 2026 (Modul Invoice Sewa Gudang — kode
disusulkan ke keputusan final §3b: FIFO per-batch dicabut dari alur aktif,
ganti pakai `tanggal_masuk_item` MIN per item).

| Aspek | Status |
|---|---|
| Tahap | **Fase 1 berjalan.** Auth & RBAC, master data, Modul Barang Masuk, Modul Surat Jalan, Modul Kartu Stok & Stok Harian, Modul Activity Log, dan Modul Invoice Sewa Gudang sudah jadi di kode dan lolos test. Berikutnya Laporan (distribusi, biaya sewa). |
| Repo | `gudang-alkap`, branch `feat/invoice-sewa-gudang-skeleton` (belum merge ke `main`), remote `jsofthouse/warehouse-system` |
| Lingkungan | Masih lokal (Laragon, `APP_ENV=local`, MySQL `whs`). Target tetap online multi-user di VPS, belum dideploy. |
| Skala lokasi | Belum final (32 baris terlihat di data awal) |
| Data berat barang | **Estimasi internet**, 16 dari 17 item punya `berat_kg` — cukup buat modul Invoice jalan, tapi BUKAN data resmi klien. RAN TRAKTOR (ALK-01) sengaja dibiarkan null, definisinya belum jelas (`06-pertanyaan-klien.md` A6). |
| Template surat jalan resmi | Belum ada. Cetak jalan dulu pakai format umum `docs/05-format-dokumen.md` §1 (pertanyaan klien C1). |
| BAST | Belum ada info. Kolom `nomor_bast` sudah ada dan opsional, baru wajib di Fase 3. |

### 2.1 Yang sudah jadi di kode

| Modul | Keadaan |
|---|---|
| Auth, RBAC, kelola user | Jadi |
| Master gudang, item, lokasi, alokasi kebutuhan, tarif sewa | Jadi |
| Barang Masuk (Penerimaan) | Jadi — draft, posting, pembatalan, cetak PDF |
| Surat Jalan | Jadi — draft, posting, tandai diterima, pembatalan, cetak tiga rangkap |
| Kartu Stok & Stok Harian | Jadi — ringkasan stok on-hand, riwayat mutasi berpaginasi, snapshot harian terjadwal + hitung ulang manual. Sempat ada bug (mismatch format tanggal di `updateOrCreate`, sudah di-fix commit `52900cd`) — **6/6 test hijau dikonfirmasi Jo** |
| Invoice Sewa Gudang | Jadi — mesin hitung final tanpa FIFO (§3b): `tanggal_masuk_item` = MIN tanggal Penerimaan posted per item+gudang, satu invoice per surat jalan, draft → terbit. Tidak ada alokasi batch otomatis saat surat jalan diposting lagi (skema FIFO §3a dorman, tidak dipanggil). Detail di `docs/04-invoice-sewa-gudang.md` §3b. |
| Activity Log | Jadi — pencatatan login/login gagal lewat Listener, halaman list dengan filter lengkap (tanggal, user, aksi, jenis dokumen, IP), link "Riwayat" di 7 index + 3 halaman show. Dua penyimpangan dari rencana ditemukan & dikonfirmasi Jo saat eksekusi (logging login lama dihapus diganti Listener; morph map ternyata juga mengubah format `stok_mutasi.referensi_type`) — detail di `docs/12-modul-activity-log.md` §11. |
| Laporan (distribusi, biaya sewa) | **Belum** |

Dua hal yang menggantung dan sudah diketahui:

- `AGENTS.md` masih berisi bootstrap Laravel Boost, tidak disamakan dengan file ini.
  Sengaja dibiarkan — proyek ini cuma dikerjakan lewat Claude.
- `tests/Feature/ExampleTest.php::test_authenticated_user_can_see_dashboard` merah
  karena `UserFactory` tidak mengisi `is_active`. Sudah merah sejak sebelum modul
  transaksi ditulis, sengaja belum diperbaiki. Bukan regresi.

## 3. Stack

| Lapis | Pilihan |
|---|---|
| Backend | PHP / Laravel |
| Database | MySQL atau PostgreSQL |
| View | Blade (server-rendered, **bukan SPA**) |
| CSS | Bootstrap 5 + template admin Tabler |
| JS | Alpine.js |
| Dropdown pencarian | Tom Select |
| Dialog konfirmasi | SweetAlert2 |
| Grafik (Fase 2) | Chart.js |
| PDF | DomPDF atau Snappy |
| Scheduler | Laravel Task Scheduling + cron |
| Hosting | VPS (diakses lintas kota Jakarta - Semarang) |

**Yang sengaja TIDAK dipakai:** Livewire, Inertia, React/Vue, jQuery, DataTables.
Alasan tiap penolakan ada di `docs/07-panduan-frontend.md`. Jangan menambahkannya
tanpa alasan yang lebih kuat daripada yang tertulis di sana.

Aturan singkat: **semua aset di-vendor lokal ke `public/vendor/`, tidak memakai CDN.**
Internet gudang tidak bisa diandalkan, dan halaman yang blank karena CDN gagal
membuat operator kehilangan kepercayaan pada sistem.

## 4. Aktor

| Role | Lokasi | Ringkas |
|---|---|---|
| `super_admin` | Pusat/IT | Akses penuh, kelola user |
| `operator_pusat` | Jakarta | Master data, monitoring semua gudang, invoice |
| `operator_gudang` | Semarang, dll | Transaksi **khusus gudang tempatnya ditugaskan** |
| `viewer` | Mana saja | Baca saja |

## 5. Keputusan desain yang tidak boleh dilanggar

Empat aturan ini adalah inti kebenaran sistem. Melanggarnya menghasilkan bug yang
mahal dan sulit dilacak.

### 5.1 Stok memakai buku besar (ledger), bukan kolom `stok`

Jangan pernah menulis `UPDATE item SET stok = stok - qty`. Semua perubahan stok
ditulis sebagai baris baru di `stok_mutasi`. Stok on-hand adalah hasil agregasi.

Alasan: satu kesalahan input atau pembatalan dokumen membuat kolom `stok` melenceng
tanpa jejak. Dengan ledger, setiap perubahan punya dokumen sumbernya. Bonus: kartu
stok dan perhitungan invoice keduanya lahir dari tabel yang sama.

### 5.2 Scoping `gudang_id` dipaksakan di query, bukan di UI

Operator gudang hanya boleh menyentuh data gudangnya. Filter ini wajib ada di
level query/global scope. Menyembunyikan tombol di tampilan **bukan** kontrol akses.

Alasan: operator Semarang yang bisa memposting surat jalan gudang lain akan merusak
angka stok di dua tempat sekaligus.

### 5.3 Draft tidak menyentuh stok; hanya posting yang menulis mutasi

Dokumen punya tahap `DRAFT` (bebas diedit, belum bernomor, belum memotong stok) dan
`POSTED` (nomor resmi terbit, mutasi tertulis, terkunci).

Alasan: operator menyusun muatan truk sambil coba-coba. Kalau draft langsung memotong
stok, angka stok kacau sepanjang jam kerja.

Koreksi dokumen ter-posting dilakukan lewat **pembatalan** yang menulis mutasi balik.
Baris asli tidak pernah dihapus.

### 5.4 Nomor dokumen terbit saat posting, di dalam transaksi

Nomor urut per gudang per tahun. Diterbitkan saat posting agar tidak ada nomor bolong
akibat draft yang dibatalkan, dan di dalam transaksi dengan penguncian baris agar dua
operator yang posting bersamaan tidak mendapat nomor kembar.

## 6. Model data (ringkas)

16 tabel — `docs/02-model-data.md` masih versi lama (15 tabel, belum diupdate
sejak `alokasi_batch_keluar` ditambah, lihat §7). DDL final ikuti migration di
`database/migrations/`, bukan dokumen itu.

```
users              gudang            lokasi            item
alokasi_kebutuhan  penerimaan        penerimaan_detail
surat_jalan        surat_jalan_detail
stok_mutasi        stok_harian       alokasi_batch_keluar
tarif_sewa         invoice           invoice_detail
activity_log
```

Tiga tabel yang paling sering disalahpahami:

- `stok_mutasi` — buku besar stok. Sumber kebenaran untuk stok on-hand.
- `stok_harian` — snapshot stok akhir hari per item. Dasar perhitungan invoice.
- `alokasi_kebutuhan` — rencana kebutuhan per lokasi. Bukan stok, bukan pengiriman.

## 7. Rumus invoice sewa gudang

Final: **tanpa FIFO**, satu invoice per surat jalan — bukan snapshot harian
agregat (§3 lama), dan bukan alokasi per-batch (§3a, sempat dibangun lalu
dikoreksi hari yang sama). Final ada di §3b dokumen di bawah.

```
tanggal_masuk_item(item, gudang) = MIN(penerimaan.tanggal) atas seluruh
                                    Penerimaan posted untuk item & gudang itu
hari_simpan(item)    = tanggal_surat_jalan - tanggal_masuk_item
unit_hari(item)      = jumlah_kirim(item) x hari_simpan(item)
kg_hari(item)        = unit_hari(item) x item.berat_kg
Biaya(item)          = kg_hari(item) x harga_jual_per_satuan_per_hari
Total                = SUM(item) Biaya(item) + PPN
```

Konvensi: hari masuk dihitung, hari keluar tidak (otomatis konsisten lewat
selisih tanggal biasa, tidak perlu logic tambahan). Barang masuk dan keluar di
hari yang sama menghasilkan 0 hari.

`stok_harian` (snapshot akhir hari) tetap dipakai buat Kartu Stok & laporan
stok, tapi bukan lagi basis invoice. Tabel/model `alokasi_batch_keluar` dan
class `AlokasiFifoBatch` tetap ada di kode tapi dorman (tidak dipanggil dari
alur invoice mana pun) — riwayat keputusan, alur posting lama, dan contoh
perhitungan final ada di `docs/04-invoice-sewa-gudang.md` §3a & §3b.

## 8. Peta dokumen

| File | Isi |
|---|---|
| `docs/00-README.md` | Indeks dan cara memakai set dokumen ini |
| `docs/01-spesifikasi-sistem.md` | Spesifikasi utama: scope, aktor, alur, daftar modul |
| `docs/02-model-data.md` | ERD, kamus data, DDL SQL siap pakai |
| `docs/03-aturan-bisnis.md` | Aturan validasi, status dokumen, penomoran |
| `docs/04-invoice-sewa-gudang.md` | Rumus, contoh perhitungan, template invoice |
| `docs/05-format-dokumen.md` | Layout surat jalan dan invoice |
| `docs/06-pertanyaan-klien.md` | Hal yang belum pasti dan dampaknya |
| `docs/07-panduan-frontend.md` | Keputusan frontend, pola Alpine, CSS cetak |
| `docs/08-keamanan.md` | Checklist keamanan aplikasi dan database |
| `docs/09-modul-barang-masuk.md` | Rancangan detail modul barang masuk — **sudah dieksekusi**; dokumennya sendiri masih bertanda "siap eksekusi" dan belum punya catatan eksekusi. Juga mencatat perubahan dari `02`/`03`. |
| `docs/10-modul-surat-jalan.md` | Rancangan detail modul surat jalan — **sudah dieksekusi**; hasil eksekusi dan tiap penyimpangan dari rencana ada di §12. Juga mencatat perubahan dari `02`/`03`/`05`. |
| `docs/11-modul-kartu-stok-dan-stok-harian.md` | Rancangan detail Kartu Stok & Stok Harian — **sudah dieksekusi**; catatan eksekusi dan status test ada di §11. Prasyarat teknis Modul Invoice (§6.2 dokumen invoice). |
| `docs/12-modul-activity-log.md` | Rancangan detail Activity Log — **sudah dieksekusi**; catatan eksekusi, dua penyimpangan yang dikonfirmasi Jo, dan status test ada di §11. Melengkapi kewajiban audit login/login gagal di `03`/`08` dan celah matriks akses di `01`. |
| `data/item.csv` | Master 17 item, siap jadi seeder |
| `data/lokasi.csv` | Master lokasi tujuan, siap jadi seeder |

## 9. Konvensi coding

- **Nama tabel: TUNGGAL, bahasa Indonesia, `snake_case`.** `gudang`, `surat_jalan`,
  `stok_mutasi` — bukan `gudangs`/`surat_jalans`/`stok_mutasis`. Aturan jamak bahasa
  Inggris ditempelkan ke kata Indonesia menghasilkan bentuk yang tidak ada di bahasa
  mana pun. Konsekuensinya konvensi otomatis Eloquent tidak berlaku, jadi **setiap
  model wajib menyatakan `protected $table` secara eksplisit** — kalau lupa, Laravel
  diam-diam mencari tabel jamak dan errornya baru muncul saat query pertama.
  Pengecualian: tabel bawaan framework (`users`, `password_reset_tokens`, `sessions`,
  `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`) tetap
  apa adanya.
- Nama kolom: `snake_case`, bahasa Indonesia mengikuti istilah domain klien
  (`jumlah_kirim`, `nomor_surat_jalan`, `alasan_pembatalan`)
- **Nama fungsi dan variabel juga tidak boleh Indonesia + `-s`.** Kalau butuh
  menyatakan koleksi, pakai awalan `daftar`: `$daftarPenerimaan`,
  `daftarSuratJalan()`, `daftarAlokasiKebutuhan()` — bukan `$penerimaans` atau
  `suratJalans()`. Ini berlaku juga untuk nama relasi Eloquent, yang secara
  konvensi Laravel biasanya dijamakkan untuk `hasMany`.
- Pengecualian: baris milik dokumen itu sendiri tetap tunggal tanpa `daftar` —
  `$suratJalan->detail`, `$penerimaan->mutasi`. Kata yang memang Inggris boleh
  dijamakkan wajar (`users()`, `$rows`).
- Semua uang disimpan sebagai `DECIMAL`, tidak pernah `FLOAT`
- Tarif, PPN, dan faktor volumetrik adalah **data**, bukan konstanta di kode
- Semua tanggal disimpan sebagai `DATE`/`DATETIME`, zona waktu Asia/Jakarta
- Validasi stok diulang di backend saat posting, tidak cukup di frontend
- Setiap posting dan pembatalan menulis `activity_log`

## 10. Baseline keamanan

Sistem online lintas kota, jadi hal berikut masuk sejak awal:
hash password (bcrypt/argon2), RBAC di middleware dan query, scoping `gudang_id`,
validasi server-side penuh, proteksi CSRF, escaping output, prepared statement,
HTTPS wajib, cookie `secure`+`httponly`, rate limit login, audit log, backup harian.

Checklist implementasi lengkap ada di `docs/08-keamanan.md` — baca sebelum menulis
controller pertama. Tiga hal yang paling mudah terlewat di sistem ini:

1. **Otorisasi per dokumen, bukan hanya filter di daftar.** Endpoint detail dan
   terutama endpoint cetak PDF sering lolos dari pengecekan.
2. **Jangan pernah `$request->all()`.** Field `gudang_id`, `status`, dan nomor dokumen
   diisi server, tidak pernah dari request.
3. **Nilai uang dan stok tidak pernah datang dari client.** Selalu dihitung ulang
   di server.

Gunakan skill `security-first-coding` saat mulai menulis fitur.
