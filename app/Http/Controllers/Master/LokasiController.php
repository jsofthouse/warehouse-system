<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\LokasiImportRequest;
use App\Http\Requests\Master\LokasiRequest;
use App\Models\Lokasi;
use App\Support\LogsActivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import lokasi sengaja CSV saja, bukan .xlsx lewat maatwebsite/excel:
 * environment build ini tidak punya akses composer/internet buat pasang
 * package baru (lihat catatan sesi). Data awal klien pun sudah berupa CSV
 * (data/lokasi.csv). Kalau nanti maatwebsite/excel bisa dipasang, cukup
 * tambah handler .xlsx di import() ini — struktur baris/kolomnya sama.
 */
class LokasiController extends Controller
{
    use LogsActivity;

    private const KOLOM_LOGGED = [
        'kode', 'nama_kodim', 'provinsi', 'kabupaten', 'kecamatan', 'desa', 'alamat', 'is_active',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $provinsi = trim((string) $request->query('provinsi', ''));

        $daftarProvinsi = Lokasi::query()->select('provinsi')->distinct()->orderBy('provinsi')->pluck('provinsi');

        $daftarLokasi = Lokasi::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($w) use ($like) {
                    $w->where('kode', 'like', $like)
                        ->orWhere('nama_kodim', 'like', $like)
                        ->orWhere('kabupaten', 'like', $like)
                        ->orWhere('kecamatan', 'like', $like)
                        ->orWhere('desa', 'like', $like);
                });
            })
            ->when($provinsi !== '', fn ($query) => $query->where('provinsi', $provinsi))
            ->orderBy('provinsi')->orderBy('kabupaten')
            ->paginate(20)
            ->withQueryString();

        return view('master.lokasi.index', compact('daftarLokasi', 'q', 'provinsi', 'daftarProvinsi'));
    }

    public function create(): View
    {
        return view('master.lokasi.create', ['lokasi' => new Lokasi]);
    }

    public function store(LokasiRequest $request): RedirectResponse
    {
        $lokasi = Lokasi::create($request->validated());

        $this->catatAktivitas('create', $lokasi, null, $lokasi->only(self::KOLOM_LOGGED), 'Tambah lokasi baru');

        return redirect()->route('master.lokasi.index')
            ->with('success', "Lokasi \"{$lokasi->nama_kodim}\" berhasil ditambahkan.");
    }

    public function edit(Lokasi $lokasi): View
    {
        return view('master.lokasi.edit', compact('lokasi'));
    }

    public function update(LokasiRequest $request, Lokasi $lokasi): RedirectResponse
    {
        $dataLama = $lokasi->only(self::KOLOM_LOGGED);

        $lokasi->update($request->validated());

        $this->catatAktivitas('update', $lokasi, $dataLama, $lokasi->only(self::KOLOM_LOGGED), 'Ubah data lokasi');

        return redirect()->route('master.lokasi.index')
            ->with('success', "Lokasi \"{$lokasi->nama_kodim}\" berhasil diperbarui.");
    }

    public function destroy(Lokasi $lokasi): RedirectResponse
    {
        $nama = $lokasi->nama_kodim;
        $dataLama = $lokasi->only(self::KOLOM_LOGGED);

        try {
            $lokasi->delete();
        } catch (QueryException) {
            return redirect()->route('master.lokasi.index')
                ->with('error', "Lokasi \"{$nama}\" masih dipakai di surat jalan, tidak bisa dihapus. Nonaktifkan saja kalau sudah tidak dipakai.");
        }

        $this->catatAktivitas('delete', $lokasi, $dataLama, null, 'Hapus lokasi (alokasi kebutuhannya ikut terhapus)');

        return redirect()->route('master.lokasi.index')
            ->with('success', "Lokasi \"{$nama}\" berhasil dihapus.");
    }

    public function importForm(): View
    {
        return view('master.lokasi.import');
    }

    public function downloadTemplate(): StreamedResponse
    {
        $kolom = ['kode', 'nama_kodim', 'provinsi', 'kabupaten', 'kecamatan', 'desa', 'alamat', 'is_active'];
        $contoh = ['LOK-99', 'Kodim Contoh', 'Jawa Barat', 'Contoh', 'Contoh', 'Contoh', 'Desa Contoh, Kec. Contoh, Kabupaten Contoh', '1'];

        return response()->streamDownload(function () use ($kolom, $contoh) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $kolom);
            fputcsv($out, $contoh);
            fclose($out);
        }, 'template-import-lokasi.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(LokasiImportRequest $request): RedirectResponse
    {
        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->with('error', 'File tidak bisa dibaca.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return back()->with('error', 'File kosong atau bukan CSV yang valid.');
            }

            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
            $kolomWajib = ['kode', 'nama_kodim', 'provinsi'];
            $kolomHilang = array_values(array_diff($kolomWajib, $header));

            if (! empty($kolomHilang)) {
                return back()->with('error', 'Header CSV wajib punya kolom: '.implode(', ', $kolomWajib).'. Hilang: '.implode(', ', $kolomHilang).'. Unduh template dulu kalau perlu.');
            }

            $dibuat = 0;
            $diperbarui = 0;
            $errorBaris = [];
            $baris = 1; // baris 1 = header

            DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                $baris++;

                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // baris kosong, lewati diam-diam
                }

                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

                $kode = trim((string) ($data['kode'] ?? ''));
                $namaKodim = trim((string) ($data['nama_kodim'] ?? ''));
                $provinsi = trim((string) ($data['provinsi'] ?? ''));

                if ($kode === '' || $namaKodim === '' || $provinsi === '') {
                    $errorBaris[] = "Baris {$baris}: kode / nama_kodim / provinsi wajib diisi.";

                    continue;
                }

                if (mb_strlen($kode) > 30) {
                    $errorBaris[] = "Baris {$baris}: kode \"{$kode}\" lebih dari 30 karakter.";

                    continue;
                }

                $existing = Lokasi::where('kode', $kode)->first();

                $payload = [
                    'nama_kodim' => $namaKodim,
                    'provinsi' => $provinsi,
                    'kabupaten' => $this->sel($data, 'kabupaten'),
                    'kecamatan' => $this->sel($data, 'kecamatan'),
                    'desa' => $this->sel($data, 'desa'),
                    'alamat' => $this->sel($data, 'alamat'),
                    'is_active' => $this->selBool($data, 'is_active'),
                ];

                $lokasi = Lokasi::updateOrCreate(['kode' => $kode], $payload);

                $existing ? $diperbarui++ : $dibuat++;

                $this->catatAktivitas(
                    $existing ? 'update' : 'create',
                    $lokasi,
                    $existing?->only(self::KOLOM_LOGGED),
                    $lokasi->only(self::KOLOM_LOGGED),
                    'Import CSV lokasi'
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'Import gagal di tengah jalan, semua perubahan dibatalkan. Cek format file lalu coba lagi.');
        } finally {
            fclose($handle);
        }

        $ringkasan = "Import selesai: {$dibuat} lokasi baru, {$diperbarui} diperbarui.";

        if (! empty($errorBaris)) {
            $ringkasan .= ' '.count($errorBaris).' baris dilewati karena error — lihat rinciannya di bawah.';
        }

        return redirect()->route('master.lokasi.import.form')
            ->with('success', $ringkasan)
            ->with('import_errors', $errorBaris);
    }

    private function sel(array $data, string $kolom): ?string
    {
        $v = trim((string) ($data[$kolom] ?? ''));

        return $v === '' ? null : $v;
    }

    private function selBool(array $data, string $kolom): bool
    {
        $v = strtolower(trim((string) ($data[$kolom] ?? '1')));

        return ! in_array($v, ['0', 'false', 'no', 'tidak', ''], true);
    }
}
