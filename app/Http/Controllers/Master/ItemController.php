<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ItemRequest;
use App\Models\Item;
use App\Support\LogsActivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    use LogsActivity;

    private const KOLOM_LOGGED = [
        'kode', 'nama', 'satuan', 'berat_kg', 'panjang_cm', 'lebar_cm', 'tinggi_cm', 'volume_m3', 'is_active',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $items = Item::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($w) use ($like) {
                    $w->where('kode', 'like', $like)->orWhere('nama', 'like', $like);
                });
            })
            ->orderBy('kode')
            ->paginate(20)
            ->withQueryString();

        return view('master.item.index', compact('items', 'q'));
    }

    public function create(): View
    {
        return view('master.item.create', ['item' => new Item]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $data = $this->dataDenganVolume($request->validated());
        $item = Item::create($data);

        $this->catatAktivitas('create', $item, null, $item->only(self::KOLOM_LOGGED), 'Tambah item baru');

        return redirect()->route('master.item.index')
            ->with('success', "Item \"{$item->nama}\" berhasil ditambahkan.");
    }

    public function edit(Item $item): View
    {
        return view('master.item.edit', compact('item'));
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $dataLama = $item->only(self::KOLOM_LOGGED);

        $item->update($this->dataDenganVolume($request->validated()));

        $this->catatAktivitas('update', $item, $dataLama, $item->only(self::KOLOM_LOGGED), 'Ubah data item');

        return redirect()->route('master.item.index')
            ->with('success', "Item \"{$item->nama}\" berhasil diperbarui.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        $nama = $item->nama;
        $dataLama = $item->only(self::KOLOM_LOGGED);

        try {
            $item->delete();
        } catch (QueryException) {
            return redirect()->route('master.item.index')
                ->with('error', "Item \"{$nama}\" masih dipakai di alokasi/transaksi, tidak bisa dihapus. Nonaktifkan saja kalau sudah tidak dipakai.");
        }

        $this->catatAktivitas('delete', $item, $dataLama, null, 'Hapus item');

        return redirect()->route('master.item.index')
            ->with('success', "Item \"{$nama}\" berhasil dihapus.");
    }

    /**
     * volume_m3 SELALU dihitung dari dimensi, tidak pernah diketik manual atau
     * diterima dari request — biar konsisten kalau dimensi dikoreksi belakangan.
     * Lihat catatan di database/seeders/ItemSeeder.php.
     */
    private function dataDenganVolume(array $data): array
    {
        $p = $data['panjang_cm'] ?? null;
        $l = $data['lebar_cm'] ?? null;
        $t = $data['tinggi_cm'] ?? null;

        $data['volume_m3'] = ($p !== null && $l !== null && $t !== null)
            ? round(($p * $l * $t) / 1_000_000, 4)
            : null;

        return $data;
    }
}
