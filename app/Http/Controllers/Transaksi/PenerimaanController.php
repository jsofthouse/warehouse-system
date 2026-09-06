<?php

namespace App\Http\Controllers\Transaksi;

use App\Enums\StatusPenerimaan;
use App\Enums\TipeMutasiStok;
use App\Events\PenerimaanDibatalkan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\PembatalanPenerimaanRequest;
use App\Http\Requests\Transaksi\PenerimaanRequest;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Penerimaan;
use App\Models\StokMutasi;
use App\Models\User;
use App\Support\GeneratesNomorDokumen;
use App\Support\LogsActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Modul Barang Masuk (Penerimaan) — Fase 1.
 *
 * Tiga aturan inti yang mengatur bentuk controller ini:
 * - Stok cuma berubah lewat baris baru di `stok_mutasis`, tidak pernah lewat
 *   UPDATE kolom stok (CLAUDE.md §5.1).
 * - Draft tidak menyentuh stok; nomor resmi dan mutasi lahir bersamaan saat
 *   posting, di dalam satu transaksi (CLAUDE.md §5.3 & §5.4).
 * - Scoping gudang dipaksakan di query dan di policy per dokumen, bukan dengan
 *   menyembunyikan tombol (CLAUDE.md §5.2, "08-keamanan.md" §2.1).
 */
class PenerimaanController extends Controller
{
    use AuthorizesRequests, GeneratesNomorDokumen, LogsActivity;

    private const PREFIX_NOMOR = 'BM';

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        // Whitelist filter: kolom urut dan nilai status tidak pernah datang
        // mentah dari query string ("08-keamanan.md" §6.1).
        $filter = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudangs,id'],
            'status' => ['nullable', Rule::enum(StatusPenerimaan::class)],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $lintasGudang = $user->bisaAksesSemuaGudang();
        $cari = trim((string) ($filter['q'] ?? ''));

        $penerimaans = Penerimaan::query()
            ->with('gudang:id,nama,kode')
            ->withSum('details as total_unit', 'jumlah')
            // Scoping keras dulu: operator_gudang & viewer tidak pernah bisa
            // melebar lewat query string, apa pun isi ?gudang_id=.
            ->when(! $lintasGudang, fn ($q) => $q->untukGudang((int) $user->gudang_id))
            ->when($lintasGudang && ! empty($filter['gudang_id']),
                fn ($q) => $q->untukGudang((int) $filter['gudang_id']))
            ->when(! empty($filter['status']), fn ($q) => $q->where('status', $filter['status']))
            ->when(! empty($filter['dari']), fn ($q) => $q->whereDate('tanggal', '>=', $filter['dari']))
            ->when(! empty($filter['sampai']), fn ($q) => $q->whereDate('tanggal', '<=', $filter['sampai']))
            ->when($cari !== '', function ($q) use ($cari) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $q->where(function ($w) use ($like) {
                    $w->where('vendor_nama', 'like', $like)
                        ->orWhere('nomor_penerimaan', 'like', $like)
                        ->orWhere('nomor_dokumen_vendor', 'like', $like);
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('penerimaan.index', [
            'penerimaans' => $penerimaans,
            'daftarGudang' => $lintasGudang ? $this->gudangAktif() : new EloquentCollection,
            'lintasGudang' => $lintasGudang,
            'filter' => $filter + ['q' => $cari],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Penerimaan::class);

        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        return view('penerimaan.create', [
            'penerimaan' => new Penerimaan(['tanggal' => now()->toDateString()]),
            'items' => $this->itemAktif(),
            'daftarGudang' => $this->pilihanGudang($user),
            'kunciGudang' => ! $user->bisaAksesSemuaGudang(),
        ]);
    }

    public function store(PenerimaanRequest $request): RedirectResponse
    {
        $this->authorize('create', Penerimaan::class);

        $data = $request->validated();
        $this->pastikanBolehPakaiGudang($request->user(), (int) $data['gudang_id']);

        $penerimaan = DB::transaction(function () use ($data, $request) {
            $penerimaan = Penerimaan::create([
                'gudang_id' => $data['gudang_id'],
                'tanggal' => $data['tanggal'],
                'vendor_nama' => $data['vendor_nama'],
                'nomor_dokumen_vendor' => $data['nomor_dokumen_vendor'] ?? null,
                'no_kontrak_referensi' => $data['no_kontrak_referensi'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->tulisUlangDetail($penerimaan, $data['detail'] ?? []);

            return $penerimaan;
        });

        $this->catatAktivitas('create', $penerimaan, null, $this->ringkasan($penerimaan), 'Buat draft penerimaan');

        return redirect()->route('penerimaan.show', $penerimaan)
            ->with('success', 'Draft penerimaan tersimpan. Stok belum berubah — baru berubah setelah diposting.');
    }

    public function show(Penerimaan $penerimaan): View
    {
        $this->authorize('view', $penerimaan);

        $penerimaan->load(['details.item', 'gudang', 'pembuat', 'poster', 'pembatal']);

        return view('penerimaan.show', compact('penerimaan'));
    }

    public function edit(Request $request, Penerimaan $penerimaan): View
    {
        $this->authorize('update', $penerimaan);

        $penerimaan->load('details.item');

        return view('penerimaan.edit', [
            'penerimaan' => $penerimaan,
            'items' => $this->itemAktif(),
            'daftarGudang' => $this->pilihanGudang($request->user()),
            // Gudang dokumen tidak pernah pindah setelah dibuat, jadi terkunci
            // buat semua role di layar edit.
            'kunciGudang' => true,
        ]);
    }

    public function update(PenerimaanRequest $request, Penerimaan $penerimaan): RedirectResponse
    {
        $this->authorize('update', $penerimaan);

        $data = $request->validated();
        $dataLama = $this->ringkasan($penerimaan);

        DB::transaction(function () use ($penerimaan, $data) {
            // gudang_id sengaja tidak ikut diperbarui: memindah dokumen ke gudang
            // lain sama saja memindahkan stok tanpa jejak.
            $penerimaan->update([
                'tanggal' => $data['tanggal'],
                'vendor_nama' => $data['vendor_nama'],
                'nomor_dokumen_vendor' => $data['nomor_dokumen_vendor'] ?? null,
                'no_kontrak_referensi' => $data['no_kontrak_referensi'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            // Baris detail diganti total (hapus lalu tulis ulang) — lebih mudah
            // dibaca dan tidak menyisakan baris yatim. Aman karena draft belum
            // pernah menulis mutasi stok.
            $penerimaan->details()->delete();
            $this->tulisUlangDetail($penerimaan, $data['detail'] ?? []);
        });

        $penerimaan->refresh()->load('details');

        $this->catatAktivitas('update', $penerimaan, $dataLama, $this->ringkasan($penerimaan), 'Ubah draft penerimaan');

        return redirect()->route('penerimaan.show', $penerimaan)
            ->with('success', 'Draft penerimaan diperbarui.');
    }

    /**
     * Draft -> Posted. Ini satu-satunya jalan stok bertambah dari modul ini.
     */
    public function posting(Request $request, Penerimaan $penerimaan): RedirectResponse
    {
        $this->authorize('posting', $penerimaan);

        $penerimaan->load(['details.item', 'gudang']);

        // Validasi diulang penuh di server. Yang dilihat operator di browser cuma
        // kenyamanan — form bisa dilewati, dan item bisa saja dinonaktifkan
        // setelah draft dibuat ("07-panduan-frontend.md" §5).
        if ($masalah = $this->masalahSebelumPosting($penerimaan)) {
            return back()->with('error', $masalah);
        }

        if (! $this->bisaLangsungPosting($penerimaan)) {
            return back()->with('error', 'Penerimaan ini perlu disetujui dulu sebelum bisa diposting.');
        }

        $berhasil = DB::transaction(function () use ($penerimaan, $request) {
            // Kunci baris dokumen: dua operator yang menekan Posting bersamaan
            // tidak boleh sama-sama lolos dan menulis mutasi dobel.
            $terkunci = Penerimaan::query()->whereKey($penerimaan->getKey())->lockForUpdate()->first();

            if (! $terkunci || $terkunci->status !== StatusPenerimaan::Draft) {
                return false;
            }

            $nomor = $this->terbitkanNomorDokumen(
                self::PREFIX_NOMOR,
                $penerimaan->gudang->kodeDokumen(),
                (int) $penerimaan->tanggal->year,
                'penerimaans',
                'nomor_penerimaan',
            );

            foreach ($penerimaan->details as $detail) {
                StokMutasi::create([
                    'gudang_id' => $penerimaan->gudang_id,
                    'item_id' => $detail->item_id,
                    // Tanggal dokumen, bukan tanggal input — perhitungan sewa
                    // gudang bergantung ke tanggal sebenarnya barang masuk.
                    'tanggal' => $penerimaan->tanggal,
                    'tipe' => TipeMutasiStok::In,
                    'jumlah' => $detail->jumlah,
                    'referensi_type' => $penerimaan->getMorphClass(),
                    'referensi_id' => $penerimaan->getKey(),
                    'keterangan' => "Penerimaan {$nomor}",
                    'created_by' => $request->user()->id,
                ]);
            }

            $terkunci->forceFill([
                'nomor_penerimaan' => $nomor,
                'status' => StatusPenerimaan::Posted,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ])->save();

            return true;
        });

        if (! $berhasil) {
            return back()->with('error', 'Dokumen ini sudah diposting atau dibatalkan pengguna lain. Muat ulang halaman.');
        }

        $penerimaan->refresh()->load('details');

        $this->catatAktivitas(
            'post',
            $penerimaan,
            null,
            $this->ringkasan($penerimaan),
            "Posting penerimaan {$penerimaan->nomor_penerimaan}",
        );

        return redirect()->route('penerimaan.show', $penerimaan)->with(
            'success',
            "Penerimaan {$penerimaan->nomor_penerimaan} diposting. Stok gudang bertambah {$penerimaan->totalUnit()} unit."
        );
    }

    public function formBatalkan(Penerimaan $penerimaan): View
    {
        $this->authorize('batalkan', $penerimaan);

        $penerimaan->load(['details.item', 'gudang']);

        return view('penerimaan.batalkan', compact('penerimaan'));
    }

    /**
     * Posted -> Dibatalkan. Baris asli tidak pernah dihapus: stok dikembalikan
     * lewat mutasi OUT yang mereferensikan dokumen yang sama
     * ("03-aturan-bisnis.md" §1 & §3.5).
     */
    public function batalkan(PembatalanPenerimaanRequest $request, Penerimaan $penerimaan): RedirectResponse
    {
        $this->authorize('batalkan', $penerimaan);

        $penerimaan->load(['details.item', 'gudang']);
        $alasan = $request->validated()['alasan_pembatalan'];
        $dataLama = $this->ringkasan($penerimaan);

        $hasil = DB::transaction(function () use ($penerimaan, $request, $alasan) {
            $terkunci = Penerimaan::query()->whereKey($penerimaan->getKey())->lockForUpdate()->first();

            if (! $terkunci || $terkunci->status !== StatusPenerimaan::Posted) {
                return 'ganda';
            }

            // Barang yang sudah terlanjur dikirim keluar tidak bisa "dikembalikan"
            // ke vendor lewat pembatalan — stoknya akan minus. Dicek di dalam
            // transaksi yang sama dengan penulisan mutasi ("08-keamanan.md" §4).
            $kekurangan = $this->itemYangBikinStokNegatif($penerimaan);

            if ($kekurangan !== []) {
                return $kekurangan;
            }

            foreach ($penerimaan->details as $detail) {
                StokMutasi::create([
                    'gudang_id' => $penerimaan->gudang_id,
                    'item_id' => $detail->item_id,
                    'tanggal' => $penerimaan->tanggal,
                    'tipe' => TipeMutasiStok::Out,
                    'jumlah' => $detail->jumlah,
                    'referensi_type' => $penerimaan->getMorphClass(),
                    'referensi_id' => $penerimaan->getKey(),
                    'keterangan' => "Pembatalan penerimaan {$penerimaan->nomor_penerimaan}",
                    'created_by' => $request->user()->id,
                ]);
            }

            $terkunci->forceFill([
                'status' => StatusPenerimaan::Dibatalkan,
                'dibatalkan_by' => $request->user()->id,
                'dibatalkan_at' => now(),
                'alasan_pembatalan' => $alasan,
            ])->save();

            return true;
        });

        if ($hasil === 'ganda') {
            return back()->with('error', 'Dokumen ini sudah dibatalkan pengguna lain. Muat ulang halaman.');
        }

        if (is_array($hasil)) {
            return back()->withInput()->with(
                'error',
                'Pembatalan ditolak karena stok akan jadi minus — '.implode('; ', $hasil).
                '. Batalkan dulu surat jalan yang mengeluarkan barang ini.'
            );
        }

        $penerimaan->refresh()->load('details');

        // Mutasi balik bertanggal dokumen asli, jadi snapshot stok_harian dari
        // tanggal itu ke depan perlu dihitung ulang. Listener-nya dibangun di
        // modul Invoice; sekarang cukup event-nya yang dilepas.
        PenerimaanDibatalkan::dispatch($penerimaan);

        $this->catatAktivitas(
            'cancel',
            $penerimaan,
            $dataLama,
            $this->ringkasan($penerimaan),
            "Pembatalan penerimaan {$penerimaan->nomor_penerimaan}",
        );

        return redirect()->route('penerimaan.show', $penerimaan)->with(
            'success',
            "Penerimaan {$penerimaan->nomor_penerimaan} dibatalkan. Stok dikembalikan lewat mutasi balik."
        );
    }

    /** Bukti Penerimaan Barang (PDF). Draft ditolak — belum punya nomor resmi. */
    public function cetak(Penerimaan $penerimaan): Response
    {
        // Endpoint cetak adalah yang paling sering lolos pengecekan otorisasi
        // ("08-keamanan.md" §2.1) — jadi dicek di sini, bukan cuma di route.
        $this->authorize('cetak', $penerimaan);

        $penerimaan->load(['details.item', 'gudang', 'pembuat', 'poster', 'pembatal']);

        $berkas = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $penerimaan->nomor_penerimaan).'.pdf';

        return Pdf::loadView('penerimaan.cetak', compact('penerimaan'))
            ->setPaper('a4')
            ->stream($berkas);
    }

    // --- Pendukung ----------------------------------------------------------

    /**
     * Gerbang tunggal transisi draft -> posted.
     *
     * Approval berjenjang belum dipakai, jadi selalu true. Kalau nanti klien
     * minta persetujuan atasan, perubahannya cukup di method ini (plus kolom
     * approval-nya) — pemanggil di posting() tidak perlu ikut berubah.
     */
    private function bisaLangsungPosting(Penerimaan $penerimaan): bool
    {
        return true;
    }

    /** @return string|null pesan penolakan, atau null kalau dokumen siap diposting */
    private function masalahSebelumPosting(Penerimaan $penerimaan): ?string
    {
        if ($penerimaan->details->isEmpty()) {
            return 'Penerimaan belum punya baris item. Tambahkan minimal satu item sebelum posting.';
        }

        $nonaktif = $penerimaan->details
            ->filter(fn ($detail) => ! ($detail->item?->is_active))
            ->map(fn ($detail) => $detail->item?->nama ?? "item #{$detail->item_id}");

        if ($nonaktif->isNotEmpty()) {
            return 'Item berikut sudah dinonaktifkan di master data, hapus dulu barisnya: '.$nonaktif->implode(', ').'.';
        }

        $itemIds = $penerimaan->details->pluck('item_id');

        if ($itemIds->unique()->count() !== $itemIds->count()) {
            return 'Ada item yang muncul di lebih dari satu baris. Gabungkan jadi satu baris dulu.';
        }

        if ($penerimaan->details->contains(fn ($detail) => $detail->jumlah < 1)) {
            return 'Ada baris dengan jumlah kurang dari 1.';
        }

        if ($penerimaan->tanggal->isFuture()) {
            return 'Tanggal penerimaan melewati hari ini. Perbaiki tanggalnya sebelum posting.';
        }

        return null;
    }

    /**
     * Stok on-hand = SUM(IN) - SUM(OUT) per item di gudang dokumen ini
     * (CLAUDE.md §5.1). Dipanggil di dalam transaksi pembatalan.
     *
     * @return array<int, string> pesan per item yang stoknya akan minus
     */
    private function itemYangBikinStokNegatif(Penerimaan $penerimaan): array
    {
        $saldo = StokMutasi::query()
            ->where('gudang_id', $penerimaan->gudang_id)
            ->whereIn('item_id', $penerimaan->details->pluck('item_id'))
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(CASE WHEN tipe = ? THEN jumlah ELSE -jumlah END) as saldo', [TipeMutasiStok::In->value])
            ->pluck('saldo', 'item_id');

        $kekurangan = [];

        foreach ($penerimaan->details as $detail) {
            $onHand = (int) ($saldo[$detail->item_id] ?? 0);
            $kurang = $detail->jumlah - $onHand;

            if ($kurang > 0) {
                $kekurangan[] = sprintf(
                    '%s: sisa stok %d, perlu ditarik %d (kurang %d)',
                    $detail->item?->nama ?? "item #{$detail->item_id}",
                    $onHand,
                    $detail->jumlah,
                    $kurang,
                );
            }
        }

        return $kekurangan;
    }

    /** @param  array<int, array<string, mixed>>  $detail */
    private function tulisUlangDetail(Penerimaan $penerimaan, array $detail): void
    {
        foreach ($detail as $baris) {
            $penerimaan->details()->create([
                'item_id' => $baris['item_id'],
                'jumlah' => $baris['jumlah'],
                'keterangan' => $baris['keterangan'] ?? null,
            ]);
        }
    }

    /**
     * Lapis kedua setelah PenerimaanRequest: operator gudang tidak pernah bisa
     * membuat dokumen atas nama gudang lain, bahkan kalau prepareForValidation
     * suatu saat diubah orang lain.
     */
    private function pastikanBolehPakaiGudang(User $user, int $gudangId): void
    {
        if (! $user->bisaAksesSemuaGudang() && (int) $user->gudang_id !== $gudangId) {
            abort(403, 'Anda hanya bisa membuat penerimaan untuk gudang tempat Anda ditugaskan.');
        }
    }

    private function pastikanPunyaPenempatan(User $user): void
    {
        if (! $user->bisaAksesSemuaGudang() && $user->gudang_id === null) {
            abort(403, 'Akun Anda belum ditempatkan di gudang mana pun. Hubungi Super Admin.');
        }
    }

    private function itemAktif(): EloquentCollection
    {
        return Item::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'satuan']);
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
    private function ringkasan(Penerimaan $penerimaan): array
    {
        return [
            'nomor_penerimaan' => $penerimaan->nomor_penerimaan,
            'gudang_id' => $penerimaan->gudang_id,
            'tanggal' => $penerimaan->tanggal?->toDateString(),
            'vendor_nama' => $penerimaan->vendor_nama,
            'status' => $penerimaan->status->value,
            'jumlah_baris' => $penerimaan->details->count(),
            'total_unit' => $penerimaan->totalUnit(),
        ];
    }
}
