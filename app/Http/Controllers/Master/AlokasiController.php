<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AlokasiSetMassalRequest;
use App\Http\Requests\Master\AlokasiUpdateRequest;
use App\Models\AlokasiKebutuhan;
use App\Models\Item;
use App\Models\Lokasi;
use App\Support\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlokasiController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $daftarLokasi = Lokasi::query()
            ->withSum('daftarAlokasiKebutuhan as total_alokasi', 'jumlah_kebutuhan')
            ->withCount('daftarAlokasiKebutuhan as jumlah_item_diatur')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($w) use ($like) {
                    $w->where('kode', 'like', $like)->orWhere('nama_kodim', 'like', $like);
                });
            })
            ->orderBy('provinsi')->orderBy('kabupaten')
            ->paginate(20)
            ->withQueryString();

        $totalItemAktif = Item::where('is_active', true)->count();

        return view('master.alokasi.index', compact('daftarLokasi', 'q', 'totalItemAktif'));
    }

    public function show(Lokasi $lokasi): View
    {
        $daftarItem = Item::where('is_active', true)->orderBy('kode')->get();
        $alokasi = $lokasi->daftarAlokasiKebutuhan()->pluck('jumlah_kebutuhan', 'item_id');

        return view('master.alokasi.show', compact('lokasi', 'daftarItem', 'alokasi'));
    }

    public function update(AlokasiUpdateRequest $request, Lokasi $lokasi): RedirectResponse
    {
        $itemIdAktif = Item::where('is_active', true)->pluck('id')->all();
        $jumlah = array_intersect_key(
            $request->validated()['jumlah'],
            array_flip($itemIdAktif)
        );

        $dataLama = $lokasi->daftarAlokasiKebutuhan()->pluck('jumlah_kebutuhan', 'item_id')->toArray();

        DB::transaction(function () use ($lokasi, $jumlah) {
            foreach ($jumlah as $itemId => $qty) {
                AlokasiKebutuhan::updateOrCreate(
                    ['lokasi_id' => $lokasi->id, 'item_id' => $itemId],
                    ['jumlah_kebutuhan' => $qty]
                );
            }
        });

        $dataBaru = $lokasi->daftarAlokasiKebutuhan()->pluck('jumlah_kebutuhan', 'item_id')->toArray();

        $this->catatAktivitas('update', $lokasi, $dataLama, $dataBaru, 'Ubah alokasi kebutuhan per item');

        return redirect()->route('master.alokasi.show', $lokasi)
            ->with('success', "Alokasi kebutuhan untuk \"{$lokasi->nama_kodim}\" berhasil disimpan.");
    }

    public function setMassalForm(): View
    {
        $daftarItem = Item::where('is_active', true)->orderBy('kode')->get();
        $jumlahLokasi = Lokasi::count();

        return view('master.alokasi.set-massal', compact('daftarItem', 'jumlahLokasi'));
    }

    public function setMassal(AlokasiSetMassalRequest $request): RedirectResponse
    {
        $itemIdAktif = Item::where('is_active', true)->pluck('id')->all();
        $jumlah = array_intersect_key(
            $request->validated()['jumlah'],
            array_flip($itemIdAktif)
        );

        $lokasiIds = Lokasi::pluck('id');

        if ($lokasiIds->isEmpty()) {
            return back()->with('error', 'Belum ada data lokasi — tambah lokasi dulu sebelum set alokasi massal.');
        }

        DB::transaction(function () use ($lokasiIds, $jumlah) {
            foreach ($lokasiIds as $lokasiId) {
                foreach ($jumlah as $itemId => $qty) {
                    AlokasiKebutuhan::updateOrCreate(
                        ['lokasi_id' => $lokasiId, 'item_id' => $itemId],
                        ['jumlah_kebutuhan' => $qty]
                    );
                }
            }
        });

        // Log satu baris ringkasan, bukan per-lokasi — activity_log tidak perlu
        // banjir ratusan baris untuk satu aksi massal.
        $this->catatAktivitas(
            'update',
            null,
            null,
            ['jumlah_kebutuhan_per_item' => $jumlah, 'jumlah_lokasi_terdampak' => $lokasiIds->count()],
            'Set alokasi massal seragam ke semua lokasi'
        );

        return redirect()->route('master.alokasi.index')
            ->with('success', "Alokasi seragam berhasil diterapkan ke {$lokasiIds->count()} lokasi.");
    }
}
