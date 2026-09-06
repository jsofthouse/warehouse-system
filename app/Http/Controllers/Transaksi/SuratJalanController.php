<?php

namespace App\Http\Controllers\Transaksi;

use App\Enums\StatusSuratJalan;
use App\Enums\TipeMutasiStok;
use App\Events\SuratJalanDibatalkan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\PembatalanSuratJalanRequest;
use App\Http\Requests\Transaksi\SuratJalanRequest;
use App\Http\Requests\Transaksi\TandaiDiterimaSuratJalanRequest;
use App\Models\Gudang;
use App\Models\Lokasi;
use App\Models\StokMutasi;
use App\Models\SuratJalan;
use App\Models\User;
use App\Support\BulanRomawi;
use App\Support\GeneratesNomorDokumen;
use App\Support\KetersediaanPengiriman;
use App\Support\LogsActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Modul Surat Jalan (Barang Keluar) — Fase 1.
 *
 * Aturan inti yang mengatur bentuk controller ini
 * ("10-modul-surat-jalan.md" §2 dan §5):
 * - Stok cuma berubah lewat baris baru di `stok_mutasi`, tidak pernah lewat
 *   UPDATE kolom stok.
 * - Draft tidak menyentuh stok; nomor resmi dan mutasi OUT lahir bersamaan saat
 *   posting, di dalam satu transaksi dengan penguncian baris.
 * - Jumlah kirim ditolak keras kalau melebihi stok gudang ATAU sisa alokasi
 *   lokasi. Dua-duanya dihitung ulang di server di dalam transaksi yang sama
 *   dengan penulisan mutasi — nilai dari browser tidak pernah dipercaya.
 * - Scoping gudang dipaksakan di query dan di SuratJalanPolicy per dokumen,
 *   bukan dengan menyembunyikan tombol.
 */
class SuratJalanController extends Controller
{
    use AuthorizesRequests, GeneratesNomorDokumen, LogsActivity;

    /**
     * SJ/SMG/0042/IX/2026 — bulan romawi dipakai karena dokumen ini diserahkan
     * ke pihak luar (Kodim), beda dari Bukti Penerimaan yang cuma arsip internal
     * ("10-modul-surat-jalan.md" §10.3). Deret urut-nya per gudang per tahun,
     * bulan ikut berganti tanpa memecah deret.
     */
    private const POLA_NOMOR = 'SJ/{gudang}/{urut}/{romawi}/{tahun}';

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        // Whitelist filter: kolom urut dan nilai status tidak pernah datang
        // mentah dari query string ("08-keamanan.md" §6.1).
        $filter = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudang,id'],
            'lokasi_id' => ['nullable', 'integer', 'exists:lokasi,id'],
            'status' => ['nullable', Rule::enum(StatusSuratJalan::class)],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $lintasGudang = $user->bisaAksesSemuaGudang();
        $cari = trim((string) ($filter['q'] ?? ''));

        $daftarSuratJalan = SuratJalan::query()
            ->with(['gudang:id,nama,kode', 'lokasi:id,kode,nama_kodim,kabupaten,provinsi'])
            ->withSum('detail as total_unit', 'jumlah_kirim')
            // Scoping keras dulu: operator_gudang & viewer tidak pernah bisa
            // melebar lewat query string, apa pun isi ?gudang_id=.
            ->when(! $lintasGudang, fn ($q) => $q->untukGudang((int) $user->gudang_id))
            ->when($lintasGudang && ! empty($filter['gudang_id']),
                fn ($q) => $q->untukGudang((int) $filter['gudang_id']))
            ->when(! empty($filter['lokasi_id']), fn ($q) => $q->where('lokasi_id', (int) $filter['lokasi_id']))
            ->when(! empty($filter['status']), fn ($q) => $q->where('status', $filter['status']))
            ->when(! empty($filter['dari']), fn ($q) => $q->whereDate('tanggal', '>=', $filter['dari']))
            ->when(! empty($filter['sampai']), fn ($q) => $q->whereDate('tanggal', '<=', $filter['sampai']))
            ->when($cari !== '', function ($q) use ($cari) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $q->where(function ($w) use ($like) {
                    $w->where('nomor_surat_jalan', 'like', $like)
                        ->orWhere('ekspedisi', 'like', $like)
                        ->orWhere('nomor_polisi', 'like', $like)
                        ->orWhere('nama_sopir', 'like', $like);
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('surat-jalan.index', [
            'daftarSuratJalan' => $daftarSuratJalan,
            'daftarGudang' => $lintasGudang ? $this->gudangAktif() : new EloquentCollection,
            'daftarLokasi' => $this->lokasiAktif(),
            'lintasGudang' => $lintasGudang,
            'filter' => $filter + ['q' => $cari],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SuratJalan::class);

        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        return view('surat-jalan.create', [
            'suratJalan' => new SuratJalan(['tanggal' => now()->toDateString()]),
            'daftarLokasi' => $this->lokasiAktif(),
            'daftarGudang' => $this->pilihanGudang($user),
            'kunciGudang' => ! $user->bisaAksesSemuaGudang(),
            'barisAwal' => [],
        ]);
    }

    /**
     * Endpoint AJAX layar buat surat jalan: begitu lokasi dipilih, tabel empat
     * angka pembanding diisi dari sini ("10-modul-surat-jalan.md" §4.2).
     *
     * Angka batas ikut dikirim, tapi itu cuma buat mengunci input di browser —
     * server tetap menghitung ulang di SuratJalanRequest dan sekali lagi saat
     * posting. Endpoint ini tidak pernah jadi satu-satunya penjaga.
     */
    public function pembanding(Request $request): JsonResponse
    {
        $this->authorize('create', SuratJalan::class);

        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        $data = $request->validate([
            'lokasi_id' => ['required', 'integer', Rule::exists('lokasi', 'id')->where('is_active', true)],
            'gudang_id' => ['nullable', 'integer', Rule::exists('gudang', 'id')->where('is_active', true)],
        ]);

        // gudang_id dari query string cuma dipakai kalau usernya memang lintas
        // gudang; operator_gudang selalu dikunci ke penempatannya.
        $gudangId = $user->bisaAksesSemuaGudang()
            ? (int) ($data['gudang_id'] ?? 0)
            : (int) $user->gudang_id;

        if ($gudangId === 0) {
            return response()->json(['message' => 'Pilih gudang asal dulu.'], 422);
        }

        $this->pastikanBolehPakaiGudang($user, $gudangId);

        $baris = KetersediaanPengiriman::untuk($gudangId, (int) $data['lokasi_id'])
            ->values()
            ->map(fn ($angka) => [
                'item_id' => $angka->item->id,
                'kode' => $angka->item->kode,
                'nama' => $angka->item->nama,
                'satuan' => $angka->item->satuan,
                'alokasi' => $angka->alokasi,
                'sudah_kirim' => $angka->sudah_kirim,
                'sisa' => $angka->sisa,
                'stok_gudang' => $angka->stok_gudang,
                'batas' => $angka->batas,
            ]);

        return response()->json(['baris' => $baris]);
    }

    public function store(SuratJalanRequest $request): RedirectResponse
    {
        $this->authorize('create', SuratJalan::class);

        $data = $request->validated();
        $this->pastikanBolehPakaiGudang($request->user(), (int) $data['gudang_id']);

        $suratJalan = DB::transaction(function () use ($data, $request) {
            $suratJalan = SuratJalan::create([
                'gudang_id' => $data['gudang_id'],
                'lokasi_id' => $data['lokasi_id'],
                'tanggal' => $data['tanggal'],
                'ekspedisi' => $data['ekspedisi'] ?? null,
                'nomor_polisi' => $data['nomor_polisi'] ?? null,
                'nama_sopir' => $data['nama_sopir'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->tulisUlangDetail($suratJalan, $data['detail'] ?? []);

            return $suratJalan;
        });

        $suratJalan->load('detail');

        $this->catatAktivitas('create', $suratJalan, null, $this->ringkasan($suratJalan), 'Buat draft surat jalan');

        return redirect()->route('surat-jalan.show', $suratJalan)
            ->with('success', 'Draft surat jalan tersimpan. Stok belum berkurang — baru berkurang setelah diposting.');
    }

    public function show(SuratJalan $suratJalan): View
    {
        $this->authorize('view', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi', 'pembuat', 'poster', 'pembatal']);

        return view('surat-jalan.show', [
            'suratJalan' => $suratJalan,
            'pengiriman' => $this->urutanPengiriman($suratJalan),
        ]);
    }

    public function edit(Request $request, SuratJalan $suratJalan): View
    {
        $this->authorize('update', $suratJalan);

        $suratJalan->load('detail.item');

        return view('surat-jalan.edit', [
            'suratJalan' => $suratJalan,
            'daftarLokasi' => $this->lokasiAktif(),
            'daftarGudang' => $this->pilihanGudang($request->user()),
            // Gudang dokumen tidak pernah pindah setelah dibuat, jadi terkunci
            // buat semua role di layar edit.
            'kunciGudang' => true,
            'barisAwal' => $suratJalan->detail
                ->mapWithKeys(fn ($detail) => [$detail->item_id => (int) $detail->jumlah_kirim])
                ->all(),
        ]);
    }

    public function update(SuratJalanRequest $request, SuratJalan $suratJalan): RedirectResponse
    {
        $this->authorize('update', $suratJalan);

        $data = $request->validated();
        $dataLama = $this->ringkasan($suratJalan);

        DB::transaction(function () use ($suratJalan, $data) {
            // gudang_id sengaja tidak ikut diperbarui: memindah dokumen ke gudang
            // lain sama saja memindahkan stok tanpa jejak.
            $suratJalan->update([
                'lokasi_id' => $data['lokasi_id'],
                'tanggal' => $data['tanggal'],
                'ekspedisi' => $data['ekspedisi'] ?? null,
                'nomor_polisi' => $data['nomor_polisi'] ?? null,
                'nama_sopir' => $data['nama_sopir'] ?? null,
            ]);

            // Baris detail diganti total (hapus lalu tulis ulang) — lebih mudah
            // dibaca dan tidak menyisakan baris yatim. Aman karena draft belum
            // pernah menulis mutasi stok.
            $suratJalan->detail()->delete();
            $this->tulisUlangDetail($suratJalan, $data['detail'] ?? []);
        });

        $suratJalan->refresh()->load('detail');

        $this->catatAktivitas('update', $suratJalan, $dataLama, $this->ringkasan($suratJalan), 'Ubah draft surat jalan');

        return redirect()->route('surat-jalan.show', $suratJalan)
            ->with('success', 'Draft surat jalan diperbarui.');
    }

    /**
     * Draft -> Posted. Ini satu-satunya jalan stok berkurang dari modul ini.
     */
    public function posting(Request $request, SuratJalan $suratJalan): RedirectResponse
    {
        $this->authorize('posting', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi']);

        // Validasi diulang penuh di server. Yang dilihat operator di browser cuma
        // kenyamanan — form bisa dilewati, dan item/alokasi/stok bisa saja berubah
        // setelah draft dibuat ("07-panduan-frontend.md" §5).
        if ($masalah = $this->masalahSebelumPosting($suratJalan)) {
            return back()->with('error', $masalah);
        }

        $hasil = DB::transaction(function () use ($suratJalan, $request) {
            // Kunci baris gudang lebih dulu: ini yang membuat dua posting untuk
            // gudang yang sama berbaris, bukan berjalan bersamaan. Tanpa ini,
            // dua dokumen bisa sama-sama lolos cek stok (masing-masing lihat
            // saldo sebelum yang lain menulis) dan penerbitan nomor kehilangan
            // pegangan saat deret tahun itu masih kosong.
            Gudang::query()->whereKey($suratJalan->gudang_id)->lockForUpdate()->first();

            // Kunci baris dokumen: dua operator yang menekan Posting bersamaan
            // tidak boleh sama-sama lolos dan menulis mutasi dobel.
            $terkunci = SuratJalan::query()->whereKey($suratJalan->getKey())->lockForUpdate()->first();

            if (! $terkunci || ! $terkunci->status->bisaDiposting()) {
                return 'ganda';
            }

            // Stok dan sisa alokasi dihitung ulang DI DALAM transaksi ini, bukan
            // dari angka yang tersimpan di draft ("10-modul-surat-jalan.md" §11.9).
            $pelanggaran = $this->pelanggaranBatasKirim($suratJalan);

            if ($pelanggaran !== []) {
                return $pelanggaran;
            }

            $nomor = $this->terbitkanNomorDokumenBerpola(
                self::POLA_NOMOR,
                [
                    'gudang' => $suratJalan->gudang->kodeDokumen(),
                    'romawi' => BulanRomawi::dari((int) $suratJalan->tanggal->month),
                    'tahun' => (int) $suratJalan->tanggal->year,
                ],
                ['gudang', 'tahun'],
                'surat_jalan',
                'nomor_surat_jalan',
            );

            foreach ($suratJalan->detail as $detail) {
                StokMutasi::create([
                    'gudang_id' => $suratJalan->gudang_id,
                    'item_id' => $detail->item_id,
                    // Tanggal dokumen, bukan tanggal input — perhitungan sewa
                    // gudang bergantung ke tanggal sebenarnya barang keluar.
                    'tanggal' => $suratJalan->tanggal,
                    'tipe' => TipeMutasiStok::Out,
                    'jumlah' => $detail->jumlah_kirim,
                    'referensi_type' => $suratJalan->getMorphClass(),
                    'referensi_id' => $suratJalan->getKey(),
                    'keterangan' => "Surat jalan {$nomor}",
                    'created_by' => $request->user()->id,
                ]);
            }

            $terkunci->forceFill([
                'nomor_surat_jalan' => $nomor,
                'status' => StatusSuratJalan::Posted,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ])->save();

            return true;
        });

        if ($hasil === 'ganda') {
            return back()->with('error', 'Dokumen ini sudah diposting atau dibatalkan pengguna lain. Muat ulang halaman.');
        }

        if (is_array($hasil)) {
            return back()->with('error', 'Posting ditolak — '.implode('; ', $hasil).'.');
        }

        $suratJalan->refresh()->load('detail');

        $this->catatAktivitas(
            'post',
            $suratJalan,
            null,
            $this->ringkasan($suratJalan),
            "Posting surat jalan {$suratJalan->nomor_surat_jalan}",
        );

        return redirect()->route('surat-jalan.show', $suratJalan)->with(
            'success',
            "Surat jalan {$suratJalan->nomor_surat_jalan} diposting. Stok gudang berkurang {$suratJalan->totalUnit()} unit."
        );
    }

    public function formTandaiDiterima(SuratJalan $suratJalan): View
    {
        $this->authorize('tandaiDiterima', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi']);

        return view('surat-jalan.tandai-diterima', compact('suratJalan'));
    }

    /**
     * Posted -> Diterima. Cuma metadata: stok sudah kepotong sejak posting,
     * jadi tidak ada mutasi baru dan tidak butuh approval
     * ("10-modul-surat-jalan.md" §10.6).
     */
    public function tandaiDiterima(TandaiDiterimaSuratJalanRequest $request, SuratJalan $suratJalan): RedirectResponse
    {
        $this->authorize('tandaiDiterima', $suratJalan);

        $data = $request->validated();
        $dataLama = $this->ringkasan($suratJalan);

        $berhasil = DB::transaction(function () use ($suratJalan, $data) {
            $terkunci = SuratJalan::query()->whereKey($suratJalan->getKey())->lockForUpdate()->first();

            if (! $terkunci || ! $terkunci->status->bisaDitandaiDiterima()) {
                return false;
            }

            $terkunci->forceFill([
                'status' => StatusSuratJalan::Diterima,
                'tanggal_terima' => $data['tanggal_terima'],
                'nama_penerima' => $data['nama_penerima'],
                'nomor_bast' => $data['nomor_bast'] ?? null,
            ])->save();

            return true;
        });

        if (! $berhasil) {
            return back()->withInput()->with(
                'error',
                'Status dokumen sudah berubah (diterima atau dibatalkan pengguna lain). Muat ulang halaman.'
            );
        }

        $suratJalan->refresh()->load('detail');

        $this->catatAktivitas(
            'terima',
            $suratJalan,
            $dataLama,
            $this->ringkasan($suratJalan),
            "Tandai diterima surat jalan {$suratJalan->nomor_surat_jalan}",
        );

        return redirect()->route('surat-jalan.show', $suratJalan)->with(
            'success',
            "Surat jalan {$suratJalan->nomor_surat_jalan} ditandai diterima oleh {$suratJalan->nama_penerima}. "
            .'Dokumen tidak bisa dibatalkan lagi.'
        );
    }

    public function formBatalkan(SuratJalan $suratJalan): View
    {
        $this->authorize('batalkan', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi']);

        return view('surat-jalan.batalkan', compact('suratJalan'));
    }

    /**
     * Posted -> Dibatalkan. Baris asli tidak pernah dihapus: stok dikembalikan
     * lewat mutasi balik bertipe IN yang mereferensikan dokumen yang sama.
     *
     * Beda dari Barang Masuk: tidak ada pengecekan konflik stok, karena menambah
     * stok tidak pernah bisa membuat nilainya negatif
     * ("10-modul-surat-jalan.md" §2.4 & §4.6.4).
     */
    public function batalkan(PembatalanSuratJalanRequest $request, SuratJalan $suratJalan): RedirectResponse
    {
        $this->authorize('batalkan', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi']);
        $alasan = $request->validated()['alasan_pembatalan'];
        $dataLama = $this->ringkasan($suratJalan);

        $berhasil = DB::transaction(function () use ($suratJalan, $request, $alasan) {
            Gudang::query()->whereKey($suratJalan->gudang_id)->lockForUpdate()->first();

            $terkunci = SuratJalan::query()->whereKey($suratJalan->getKey())->lockForUpdate()->first();

            if (! $terkunci || ! $terkunci->status->bisaDibatalkan()) {
                return false;
            }

            foreach ($suratJalan->detail as $detail) {
                StokMutasi::create([
                    'gudang_id' => $suratJalan->gudang_id,
                    'item_id' => $detail->item_id,
                    'tanggal' => $suratJalan->tanggal,
                    'tipe' => TipeMutasiStok::In,
                    'jumlah' => $detail->jumlah_kirim,
                    'referensi_type' => $suratJalan->getMorphClass(),
                    'referensi_id' => $suratJalan->getKey(),
                    'keterangan' => "Pembatalan surat jalan {$suratJalan->nomor_surat_jalan}",
                    'created_by' => $request->user()->id,
                ]);
            }

            $terkunci->forceFill([
                'status' => StatusSuratJalan::Dibatalkan,
                'dibatalkan_by' => $request->user()->id,
                'dibatalkan_at' => now(),
                'alasan_pembatalan' => $alasan,
            ])->save();

            return true;
        });

        if (! $berhasil) {
            return back()->with('error', 'Dokumen ini sudah dibatalkan atau ditandai diterima pengguna lain. Muat ulang halaman.');
        }

        $suratJalan->refresh()->load('detail');

        // Mutasi balik bertanggal dokumen asli, jadi snapshot stok_harian dari
        // tanggal itu ke depan perlu dihitung ulang. Listener-nya dibangun di
        // modul Invoice; sekarang cukup event-nya yang dilepas.
        SuratJalanDibatalkan::dispatch($suratJalan);

        $this->catatAktivitas(
            'cancel',
            $suratJalan,
            $dataLama,
            $this->ringkasan($suratJalan),
            "Pembatalan surat jalan {$suratJalan->nomor_surat_jalan}",
        );

        return redirect()->route('surat-jalan.show', $suratJalan)->with(
            'success',
            "Surat jalan {$suratJalan->nomor_surat_jalan} dibatalkan. Stok dikembalikan lewat mutasi balik."
        );
    }

    /** Surat Jalan tiga rangkap (PDF). Draft ditolak — belum punya nomor resmi. */
    public function cetak(SuratJalan $suratJalan): Response
    {
        // Endpoint cetak adalah yang paling sering lolos pengecekan otorisasi
        // ("08-keamanan.md" §2.1) — jadi dicek di sini, bukan cuma di route.
        $this->authorize('cetak', $suratJalan);

        $suratJalan->load(['detail.item', 'gudang', 'lokasi', 'pembuat', 'poster', 'pembatal']);

        $berkas = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '',
            str_replace('/', '-', (string) $suratJalan->nomor_surat_jalan),
        ).'.pdf';

        return Pdf::loadView('surat-jalan.cetak', [
            'suratJalan' => $suratJalan,
            'pengiriman' => $this->urutanPengiriman($suratJalan),
        ])->setPaper('a4')->stream($berkas);
    }

    // --- Pendukung ----------------------------------------------------------

    /** @return string|null pesan penolakan, atau null kalau dokumen siap diposting */
    private function masalahSebelumPosting(SuratJalan $suratJalan): ?string
    {
        if ($suratJalan->detail->isEmpty()) {
            return 'Surat jalan belum punya baris item. Isi minimal satu item sebelum posting.';
        }

        $nonaktif = $suratJalan->detail
            ->filter(fn ($detail) => ! ($detail->item?->is_active))
            ->map(fn ($detail) => $detail->item?->nama ?? "item #{$detail->item_id}");

        if ($nonaktif->isNotEmpty()) {
            return 'Item berikut sudah dinonaktifkan di master data, hapus dulu barisnya: '.$nonaktif->implode(', ').'.';
        }

        $itemIds = $suratJalan->detail->pluck('item_id');

        if ($itemIds->unique()->count() !== $itemIds->count()) {
            return 'Ada item yang muncul di lebih dari satu baris. Gabungkan jadi satu baris dulu.';
        }

        if ($suratJalan->detail->contains(fn ($detail) => $detail->jumlah_kirim < 1)) {
            return 'Ada baris dengan jumlah kirim kurang dari 1.';
        }

        if (! ($suratJalan->lokasi?->is_active)) {
            return 'Lokasi tujuan sudah dinonaktifkan di master data. Perbaiki dulu tujuannya.';
        }

        if ($suratJalan->tanggal->isFuture()) {
            return 'Tanggal pengiriman melewati hari ini. Perbaiki tanggalnya sebelum posting.';
        }

        return null;
    }

    /**
     * Batas keras jumlah kirim: stok gudang DAN sisa alokasi lokasi
     * ("10-modul-surat-jalan.md" §10.2). Dipanggil di dalam transaksi posting.
     *
     * @return array<int, string> pesan per baris yang melanggar
     */
    private function pelanggaranBatasKirim(SuratJalan $suratJalan): array
    {
        $ketersediaan = KetersediaanPengiriman::untuk(
            (int) $suratJalan->gudang_id,
            (int) $suratJalan->lokasi_id,
        );

        $pelanggaran = [];

        foreach ($suratJalan->detail as $detail) {
            $angka = $ketersediaan->get($detail->item_id);
            $nama = $detail->item?->nama ?? "item #{$detail->item_id}";

            if ($angka === null) {
                $pelanggaran[] = "{$nama}: item tidak ada lagi di master aktif";

                continue;
            }

            if ($detail->jumlah_kirim > $angka->stok_gudang) {
                $pelanggaran[] = sprintf(
                    '%s: stok gudang tinggal %d, mau dikirim %d',
                    $nama,
                    $angka->stok_gudang,
                    $detail->jumlah_kirim,
                );

                continue;
            }

            if ($detail->jumlah_kirim > $angka->sisa) {
                $pelanggaran[] = sprintf(
                    '%s: sisa alokasi lokasi ini tinggal %d (alokasi %d, sudah terkirim %d), mau dikirim %d',
                    $nama,
                    $angka->sisa,
                    $angka->alokasi,
                    $angka->sudah_kirim,
                    $detail->jumlah_kirim,
                );
            }
        }

        return $pelanggaran;
    }

    /**
     * "Pengiriman ke-n dari total" untuk lokasi tujuan dokumen ini
     * ("05-format-dokumen.md" §1). Yang dihitung cuma dokumen ter-posting /
     * diterima — draft dan dokumen batal tidak pernah sampai ke lapangan.
     *
     * @return array{ke: int|null, dari: int}
     */
    private function urutanPengiriman(SuratJalan $suratJalan): array
    {
        $urutan = SuratJalan::query()
            ->where('lokasi_id', $suratJalan->lokasi_id)
            ->terkirim()
            ->orderBy('tanggal')
            ->orderBy('id')
            ->pluck('id');

        $posisi = $urutan->search($suratJalan->getKey());

        return [
            'ke' => $posisi === false ? null : $posisi + 1,
            'dari' => $urutan->count(),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $detail */
    private function tulisUlangDetail(SuratJalan $suratJalan, array $detail): void
    {
        foreach ($detail as $baris) {
            $suratJalan->detail()->create([
                'item_id' => $baris['item_id'],
                'jumlah_kirim' => $baris['jumlah_kirim'],
            ]);
        }
    }

    /**
     * Lapis kedua setelah SuratJalanRequest: operator gudang tidak pernah bisa
     * membuat dokumen atas nama gudang lain, bahkan kalau prepareForValidation
     * suatu saat diubah orang lain.
     */
    private function pastikanBolehPakaiGudang(User $user, int $gudangId): void
    {
        if (! $user->bisaAksesSemuaGudang() && (int) $user->gudang_id !== $gudangId) {
            abort(403, 'Anda hanya bisa membuat surat jalan untuk gudang tempat Anda ditugaskan.');
        }
    }

    private function pastikanPunyaPenempatan(User $user): void
    {
        if (! $user->bisaAksesSemuaGudang() && $user->gudang_id === null) {
            abort(403, 'Akun Anda belum ditempatkan di gudang mana pun. Hubungi Super Admin.');
        }
    }

    private function lokasiAktif(): EloquentCollection
    {
        return Lokasi::query()
            ->where('is_active', true)
            ->orderBy('provinsi')
            ->orderBy('kabupaten')
            ->orderBy('nama_kodim')
            ->get(['id', 'kode', 'nama_kodim', 'provinsi', 'kabupaten', 'kecamatan', 'desa']);
    }

    private function gudangAktif(): EloquentCollection
    {
        return Gudang::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    private function pilihanGudang(User $user): EloquentCollection
    {
        if ($user->bisaAksesSemuaGudang()) {
            return $this->gudangAktif();
        }

        return Gudang::query()->whereKey($user->gudang_id)->get(['id', 'kode', 'nama']);
    }

    /** @return array<string, mixed> ringkasan buat activity_log (tanpa isi request mentah) */
    private function ringkasan(SuratJalan $suratJalan): array
    {
        return [
            'nomor_surat_jalan' => $suratJalan->nomor_surat_jalan,
            'gudang_id' => $suratJalan->gudang_id,
            'lokasi_id' => $suratJalan->lokasi_id,
            'tanggal' => $suratJalan->tanggal?->toDateString(),
            'status' => $suratJalan->status->value,
            'jumlah_baris' => $suratJalan->detail->count(),
            'total_unit' => $suratJalan->totalUnit(),
        ];
    }
}
