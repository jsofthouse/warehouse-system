<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\GudangRequest;
use App\Models\Gudang;
use App\Support\LogsActivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GudangController extends Controller
{
    use LogsActivity;

    private const KOLOM_LOGGED = ['kode', 'nama', 'kota', 'alamat', 'is_active'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $gudangs = Gudang::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($w) use ($like) {
                    $w->where('kode', 'like', $like)
                        ->orWhere('nama', 'like', $like)
                        ->orWhere('kota', 'like', $like);
                });
            })
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('master.gudang.index', compact('gudangs', 'q'));
    }

    public function create(): View
    {
        return view('master.gudang.create', ['gudang' => new Gudang]);
    }

    public function store(GudangRequest $request): RedirectResponse
    {
        $gudang = Gudang::create($request->validated());

        $this->catatAktivitas('create', $gudang, null, $gudang->only(self::KOLOM_LOGGED), 'Tambah gudang baru');

        return redirect()->route('master.gudang.index')
            ->with('success', "Gudang \"{$gudang->nama}\" berhasil ditambahkan.");
    }

    public function edit(Gudang $gudang): View
    {
        return view('master.gudang.edit', compact('gudang'));
    }

    public function update(GudangRequest $request, Gudang $gudang): RedirectResponse
    {
        $dataLama = $gudang->only(self::KOLOM_LOGGED);

        $gudang->update($request->validated());

        $this->catatAktivitas('update', $gudang, $dataLama, $gudang->only(self::KOLOM_LOGGED), 'Ubah data gudang');

        return redirect()->route('master.gudang.index')
            ->with('success', "Gudang \"{$gudang->nama}\" berhasil diperbarui.");
    }

    public function destroy(Gudang $gudang): RedirectResponse
    {
        $nama = $gudang->nama;
        $dataLama = $gudang->only(self::KOLOM_LOGGED);

        try {
            $gudang->delete();
        } catch (QueryException) {
            // Masih direferensikan tabel lain (user, tarif, transaksi) — dibiarkan
            // ditolak oleh foreign key restrictOnDelete, bukan dicek manual di sini,
            // supaya aturan "restrict" tetap satu sumber kebenaran (skema).
            return redirect()->route('master.gudang.index')
                ->with('error', "Gudang \"{$nama}\" masih dipakai di data lain (user/tarif/transaksi), tidak bisa dihapus. Nonaktifkan saja kalau sudah tidak dipakai.");
        }

        $this->catatAktivitas('delete', $gudang, $dataLama, null, 'Hapus gudang');

        return redirect()->route('master.gudang.index')
            ->with('success', "Gudang \"{$nama}\" berhasil dihapus.");
    }
}
