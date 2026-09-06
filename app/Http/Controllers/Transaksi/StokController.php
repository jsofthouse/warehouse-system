<?php

namespace App\Http\Controllers\Transaksi;

use App\Enums\TipeMutasiStok;
use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\StokHarian;
use App\Models\StokMutasi;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Modul Kartu Stok & Stok Harian — Fase 1.
 *
 * Modul ini murni pembaca/agregator: tidak pernah menulis ke `stok_mutasi`
 * (satu-satunya sumber kebenaran stok, CLAUDE.md §5.1). Stok on-hand selalu
 * dihitung dari StokMutasi::saldoAkhir(), tidak pernah dari kolom yang
 * di-cache. Lihat "Modul Kartu Stok dan Stok Harian.md" untuk rancangan
 * lengkap.
 *
 * Scoping gudang tetap dipaksakan di query untuk operator_gudang, sama
 * seperti modul transaksi lain (CLAUDE.md §5.2) — meskipun modul ini baca
 * saja, operator gudang tidak boleh mengintip stok gudang lain.
 */
class StokController extends Controller
{
    use LogsActivity;

    /** Batas rentang hitung ulang manual dari UI — lihat §5 aturan validasi 3. */
    private const MAKS_HARI_HITUNG_ULANG = 366;

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        $lintasGudang = $user->bisaAksesSemuaGudang();

        $filter = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudang,id'],
        ]);

        $gudangId = $this->resolveGudangId($user, $lintasGudang, $filter['gudang_id'] ?? null);

        $daftarItem = $this->itemAktif();

        $ringkasan = $daftarItem->map(fn (Item $item) => [
            'item' => $item,
            'stok' => StokMutasi::saldoAkhir($gudangId, $item->id),
        ]);

        return view('stok.index', [
            'ringkasan' => $ringkasan,
            'gudangDipilih' => Gudang::find($gudangId),
            'daftarGudang' => $lintasGudang ? $this->gudangAktif() : new EloquentCollection,
            'lintasGudang' => $lintasGudang,
            'filter' => $filter,
        ]);
    }

    public function riwayat(Request $request, Item $item): View
    {
        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        $lintasGudang = $user->bisaAksesSemuaGudang();

        $filter = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudang,id'],
            'tipe' => ['nullable', 'in:IN,OUT'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $gudangId = $this->resolveGudangId($user, $lintasGudang, $filter['gudang_id'] ?? null);
        $gudang = Gudang::findOrFail($gudangId);

        // Saldo berjalan dihitung dari SELURUH riwayat kronologis dulu, baru
        // filter tampilan (tipe/tanggal) diterapkan di atasnya — supaya angka
        // saldo yang ditampilkan tetap benar walau baris di sekitarnya sedang
        // disaring ("Modul Kartu Stok dan Stok Harian.md" §4.1).
        $seluruhMutasi = StokMutasi::query()
            ->where('gudang_id', $gudangId)
            ->where('item_id', $item->id)
            ->with(['referensi', 'pembuat:id,name'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldo = 0;
        $denganSaldo = $seluruhMutasi->map(function (StokMutasi $mutasi) use (&$saldo) {
            $saldo += $mutasi->tipe === TipeMutasiStok::In ? $mutasi->jumlah : -$mutasi->jumlah;
            $mutasi->setAttribute('saldo_berjalan', $saldo);

            return $mutasi;
        });

        $tersaring = $denganSaldo
            ->when(! empty($filter['tipe']), fn ($c) => $c->where('tipe', TipeMutasiStok::from($filter['tipe'])))
            ->when(! empty($filter['dari']), fn ($c) => $c->filter(
                fn (StokMutasi $m) => $m->tanggal->gte(Carbon::parse($filter['dari']))
            ))
            ->when(! empty($filter['sampai']), fn ($c) => $c->filter(
                fn (StokMutasi $m) => $m->tanggal->lte(Carbon::parse($filter['sampai']))
            ))
            ->reverse() // terbaru dulu, konsisten dengan daftar transaksi lain
            ->values();

        $perHalaman = 25;
        $halaman = (int) ($filter['page'] ?? 1);

        $riwayat = new LengthAwarePaginator(
            $tersaring->forPage($halaman, $perHalaman),
            $tersaring->count(),
            $perHalaman,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('stok.riwayat', [
            'item' => $item,
            'gudang' => $gudang,
            'stokSaatIni' => $saldo,
            'riwayat' => $riwayat,
            'filter' => $filter,
        ]);
    }

    public function harian(Request $request): View
    {
        $user = $request->user();
        $this->pastikanPunyaPenempatan($user);

        $lintasGudang = $user->bisaAksesSemuaGudang();

        $filter = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudang,id'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $gudangId = $this->resolveGudangId($user, $lintasGudang, $filter['gudang_id'] ?? null);
        $gudang = Gudang::findOrFail($gudangId);

        $sampai = ! empty($filter['sampai']) ? Carbon::parse($filter['sampai']) : Carbon::today();
        $dari = ! empty($filter['dari']) ? Carbon::parse($filter['dari']) : $sampai->copy()->startOfMonth();

        $daftarItem = $this->itemAktif();

        $snapshot = StokHarian::query()
            ->where('gudang_id', $gudangId)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->get()
            ->groupBy(fn (StokHarian $s) => $s->tanggal->toDateString())
            ->map(fn ($baris) => $baris->keyBy('item_id'));

        $daftarTanggal = [];
        for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
            $daftarTanggal[] = $t->copy();
        }

        return view('stok.harian', [
            'gudang' => $gudang,
            'daftarGudang' => $lintasGudang ? $this->gudangAktif() : new EloquentCollection,
            'lintasGudang' => $lintasGudang,
            'daftarItem' => $daftarItem,
            'daftarTanggal' => $daftarTanggal,
            'snapshot' => $snapshot,
            'filter' => array_merge($filter, ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    public function hitungUlang(Request $request): RedirectResponse
    {
        $user = $request->user();
        $lintasGudang = $user->bisaAksesSemuaGudang();

        $data = $request->validate([
            'gudang_id' => ['nullable', 'integer', 'exists:gudang,id'],
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        $gudangId = $this->resolveGudangId($user, $lintasGudang, $data['gudang_id'] ?? null);

        $dari = Carbon::parse($data['dari']);
        $sampai = Carbon::parse($data['sampai']);

        // Aturan validasi 3: batasi rentang dari UI, rentang lebih panjang
        // dijalankan lewat artisan langsung di server.
        if ($dari->diffInDays($sampai) + 1 > self::MAKS_HARI_HITUNG_ULANG) {
            return back()->withErrors([
                'sampai' => 'Rentang hitung ulang maksimal '.self::MAKS_HARI_HITUNG_ULANG.' hari lewat halaman ini. Untuk rentang lebih panjang, jalankan lewat artisan di server.',
            ]);
        }

        Artisan::call('stok:hitung-harian', [
            '--gudang' => $gudangId,
            '--dari' => $dari->toDateString(),
            '--sampai' => $sampai->toDateString(),
        ]);

        $this->catatAktivitas(
            'hitung_ulang_stok_harian',
            null,
            deskripsi: "Hitung ulang stok harian gudang #{$gudangId}, {$dari->toDateString()} s/d {$sampai->toDateString()}",
            dataBaru: ['gudang_id' => $gudangId, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
        );

        return back()->with('success', 'Stok harian berhasil dihitung ulang untuk rentang tanggal yang dipilih.');
    }

    /**
     * Operator gudang selalu dipaksa ke gudangnya sendiri, apa pun isi query
     * string — pola sama dengan modul transaksi lain (CLAUDE.md §5.2). Role
     * lintas-gudang boleh memilih lewat filter, default ke gudang pertama.
     */
    private function resolveGudangId(User $user, bool $lintasGudang, ?int $gudangIdDiminta): int
    {
        if (! $lintasGudang) {
            return (int) $user->gudang_id;
        }

        if ($gudangIdDiminta !== null) {
            return $gudangIdDiminta;
        }

        $gudangPertama = $this->gudangAktif()->first();

        if ($gudangPertama === null) {
            abort(404, 'Belum ada gudang aktif.');
        }

        return (int) $gudangPertama->id;
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
}
