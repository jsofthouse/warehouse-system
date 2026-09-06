<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\TarifSewaRequest;
use App\Models\Gudang;
use App\Models\TarifSewa;
use App\Support\LogsActivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TarifSewaController extends Controller
{
    use LogsActivity;

    private const KOLOM_LOGGED = [
        'gudang_id', 'basis', 'tarif_per_satuan_per_hari', 'faktor_volumetrik_kg_per_m3',
        'ppn_persen', 'min_hari_simpan', 'pembulatan_rupiah', 'berlaku_mulai', 'berlaku_sampai',
    ];

    public function index(Request $request): View
    {
        $gudangId = $request->query('gudang_id', '');

        $tarifs = TarifSewa::query()
            ->with('gudang')
            ->when($gudangId !== '', fn ($q) => $q->where('gudang_id', $gudangId))
            ->orderByDesc('berlaku_mulai')
            ->paginate(20)
            ->withQueryString();

        $daftarGudang = Gudang::orderBy('nama')->get();

        return view('master.tarif.index', compact('tarifs', 'daftarGudang', 'gudangId'));
    }

    public function create(): View
    {
        $daftarGudang = Gudang::where('is_active', true)->orderBy('nama')->get();

        return view('master.tarif.create', ['tarif' => new TarifSewa, 'daftarGudang' => $daftarGudang]);
    }

    public function store(TarifSewaRequest $request): RedirectResponse
    {
        $tarif = TarifSewa::create($request->validated());

        $this->catatAktivitas('create', $tarif, null, $tarif->only(self::KOLOM_LOGGED), 'Tambah tarif sewa baru');

        return redirect()->route('master.tarif.index')
            ->with('success', 'Tarif sewa berhasil ditambahkan.');
    }

    public function edit(TarifSewa $tarif): View
    {
        $daftarGudang = Gudang::where('is_active', true)->orWhere('id', $tarif->gudang_id)->orderBy('nama')->get();

        return view('master.tarif.edit', compact('tarif', 'daftarGudang'));
    }

    public function update(TarifSewaRequest $request, TarifSewa $tarif): RedirectResponse
    {
        $dataLama = $tarif->only(self::KOLOM_LOGGED);

        $tarif->update($request->validated());

        $this->catatAktivitas('update', $tarif, $dataLama, $tarif->only(self::KOLOM_LOGGED), 'Ubah tarif sewa');

        return redirect()->route('master.tarif.index')
            ->with('success', 'Tarif sewa berhasil diperbarui.');
    }

    public function destroy(TarifSewa $tarif): RedirectResponse
    {
        $dataLama = $tarif->only(self::KOLOM_LOGGED);

        try {
            $tarif->delete();
        } catch (QueryException) {
            return redirect()->route('master.tarif.index')
                ->with('error', 'Tarif ini sudah dipakai di invoice yang pernah terbit, tidak bisa dihapus. Tutup periodenya saja (isi Berlaku Sampai).');
        }

        $this->catatAktivitas('delete', $tarif, $dataLama, null, 'Hapus tarif sewa');

        return redirect()->route('master.tarif.index')
            ->with('success', 'Tarif sewa berhasil dihapus.');
    }
}
