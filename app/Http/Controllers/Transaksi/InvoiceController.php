<?php

namespace App\Http\Controllers\Transaksi;

use App\Enums\StatusInvoice;
use App\Enums\StatusSuratJalan;
use App\Exceptions\PerhitunganBiayaSewaGagal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaksi\InvoiceRequest;
use App\Models\Gudang;
use App\Models\Invoice;
use App\Models\SuratJalan;
use App\Support\BulanRomawi;
use App\Support\GeneratesNomorDokumen;
use App\Support\LogsActivity;
use App\Support\PerhitunganBiayaSewa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul Invoice Sewa Gudang — dihitung langsung dari tanggal dokumen, tanpa
 * alokasi per-batch ("04-invoice-sewa-gudang.md" §0 & §3b, final; §3a lama
 * sudah dorman). Tidak ada penulisan alokasi otomatis saat surat jalan
 * diposting — App\Support\PerhitunganBiayaSewa menghitung langsung dari
 * `tanggal_masuk_item` (MIN tanggal Penerimaan posted per item) tiap kali
 * dipanggil.
 */
class InvoiceController extends Controller
{
    use GeneratesNomorDokumen, LogsActivity;

    private const PREFIX_NOMOR = 'INV';

    private const POLA_NOMOR = 'INV/{gudang}/{urut}/{romawi}/{tahun}';

    /** Status surat jalan yang boleh ditagih — sudah keluar gudang, belum tentu diterima. */
    private const STATUS_SIAP_DITAGIH = [StatusSuratJalan::Posted, StatusSuratJalan::Diterima];

    public function index(Request $request): View
    {
        $gudangId = $request->query('gudang_id', '');

        $daftarInvoice = Invoice::query()
            ->with(['gudang', 'suratJalan'])
            ->when($gudangId !== '', fn ($q) => $q->where('gudang_id', $gudangId))
            ->orderByDesc('periode_mulai')
            ->paginate(20)
            ->withQueryString();

        $daftarGudang = Gudang::orderBy('nama')->get();

        return view('invoice.index', compact('daftarInvoice', 'daftarGudang', 'gudangId'));
    }

    /** Layar "Buat Invoice": daftar surat jalan siap ditagih yang belum py invoice. */
    public function pilih(): View
    {
        $daftarSuratJalan = SuratJalan::query()
            ->with(['gudang', 'lokasi'])
            ->withSum('detail as total_unit', 'jumlah_kirim')
            ->whereIn('status', array_map(fn ($s) => $s->value, self::STATUS_SIAP_DITAGIH))
            ->whereDoesntHave('invoice')
            ->orderByDesc('tanggal')
            ->paginate(20);

        return view('invoice.pilih', compact('daftarSuratJalan'));
    }

    /** Pratinjau draft — dihitung dari alokasi, TIDAK disimpan. */
    public function draft(SuratJalan $suratJalan): View|RedirectResponse
    {
        if ($suratJalan->invoice()->exists()) {
            return redirect()->route('invoice.pilih')
                ->with('error', "Surat jalan {$suratJalan->nomor_surat_jalan} sudah py invoice.");
        }

        if (! in_array($suratJalan->status, self::STATUS_SIAP_DITAGIH, true)) {
            return redirect()->route('invoice.pilih')
                ->with('error', 'Surat jalan ini belum diposting, tidak bisa ditagih.');
        }

        try {
            $hitung = PerhitunganBiayaSewa::hitung($suratJalan);
        } catch (PerhitunganBiayaSewaGagal $e) {
            return redirect()->route('invoice.pilih')->with('error', $e->getMessage());
        }

        $suratJalan->load(['detail.item', 'gudang', 'lokasi']);

        return view('invoice.draft', compact('suratJalan', 'hitung'));
    }

    /**
     * Simpan draft invoice. Angkanya dihitung ULANG penuh di sini, tidak pernah
     * dipercaya dari apa yang tampil di layar pratinjau (CLAUDE.md §10 poin 3).
     */
    public function store(InvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $hasil = DB::transaction(function () use ($data, $request) {
            // Kunci baris surat jalan: dua operator yang menekan Konfirmasi
            // bersamaan buat surat jalan yang sama tidak boleh sama-sama lolos.
            $suratJalan = SuratJalan::query()->whereKey($data['surat_jalan_id'])->lockForUpdate()->first();

            if (! $suratJalan || ! in_array($suratJalan->status, self::STATUS_SIAP_DITAGIH, true)) {
                return 'status-tidak-valid';
            }

            if ($suratJalan->invoice()->exists()) {
                return 'sudah-ada-invoice';
            }

            try {
                $hitung = PerhitunganBiayaSewa::hitung($suratJalan);
            } catch (PerhitunganBiayaSewaGagal $e) {
                return $e->getMessage();
            }

            $invoice = Invoice::create([
                'gudang_id' => $suratJalan->gudang_id,
                'surat_jalan_id' => $suratJalan->id,
                'periode_mulai' => $hitung['periode_mulai'],
                'periode_selesai' => $hitung['periode_selesai'],
                'subtotal' => $hitung['subtotal'],
                'ppn_persen' => $hitung['ppn_persen'],
                'ppn_nominal' => $hitung['ppn_nominal'],
                'total' => $hitung['total'],
                'nama_tertagih' => $data['nama_tertagih'] ?? null,
                'alamat_tertagih' => $data['alamat_tertagih'] ?? null,
                'npwp_tertagih' => $data['npwp_tertagih'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($hitung['baris'] as $baris) {
                $invoice->detail()->create($baris);
            }

            return $invoice;
        });

        if ($hasil === 'status-tidak-valid') {
            return back()->with('error', 'Surat jalan ini belum siap ditagih (belum diposting atau sudah dibatalkan).');
        }

        if ($hasil === 'sudah-ada-invoice') {
            return redirect()->route('invoice.pilih')->with('error', 'Surat jalan ini sudah keburu py invoice.');
        }

        if (is_string($hasil)) {
            return back()->with('error', $hasil);
        }

        $this->catatAktivitas('create', $hasil, null, $this->ringkasan($hasil), 'Buat draft invoice sewa gudang');

        return redirect()->route('invoice.show', $hasil)
            ->with('success', 'Draft invoice tersimpan. Nomor resmi terbit setelah diposting.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['gudang', 'suratJalan', 'detail.item', 'pembuat', 'poster', 'pembatal']);

        return view('invoice.show', compact('invoice'));
    }

    /** Draft -> Terbit. Nomor resmi terbit di sini, bukan saat draft dibuat. */
    public function posting(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->load('gudang');

        $hasil = DB::transaction(function () use ($invoice, $request) {
            $terkunci = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

            if (! $terkunci || $terkunci->status !== StatusInvoice::Draft) {
                return false;
            }

            $nomor = $this->terbitkanNomorDokumenBerpola(
                self::POLA_NOMOR,
                [
                    'gudang' => $invoice->gudang->kodeDokumen(),
                    'romawi' => BulanRomawi::dari((int) $invoice->periode_selesai->month),
                    'tahun' => (int) $invoice->periode_selesai->year,
                ],
                ['gudang', 'tahun'],
                'invoice',
                'nomor_invoice',
            );

            $terkunci->forceFill([
                'nomor_invoice' => $nomor,
                'status' => StatusInvoice::Terbit,
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ])->save();

            return true;
        });

        if (! $hasil) {
            return back()->with('error', 'Invoice ini sudah diposting atau dibatalkan pengguna lain. Muat ulang halaman.');
        }

        $invoice->refresh();

        $this->catatAktivitas(
            'post',
            $invoice,
            null,
            $this->ringkasan($invoice),
            "Posting invoice {$invoice->nomor_invoice}",
        );

        return redirect()->route('invoice.show', $invoice)
            ->with('success', "Invoice {$invoice->nomor_invoice} diposting.");
    }

    /**
     * Template cetak standar — aman dipanggil untuk invoice draft sekalipun
     * (nomor akan kosong/placeholder di templatenya sendiri).
     */
    public function cetak(Invoice $invoice): Response
    {
        $invoice->load(['gudang', 'detail.item', 'pembuat', 'poster', 'pembatal']);

        $berkas = preg_replace(
            '/[^A-Za-z0-9\-]/',
            '',
            str_replace('/', '-', (string) ($invoice->nomor_invoice ?? 'draft-'.$invoice->id)),
        ).'.pdf';

        return Pdf::loadView('invoice.cetak', ['invoice' => $invoice])
            ->setPaper('a4')
            ->stream($berkas);
    }

    /** @return array<string, mixed> ringkasan buat activity_log */
    private function ringkasan(Invoice $invoice): array
    {
        return [
            'nomor_invoice' => $invoice->nomor_invoice,
            'gudang_id' => $invoice->gudang_id,
            'surat_jalan_id' => $invoice->surat_jalan_id,
            'status' => $invoice->status->value,
            'total' => (string) $invoice->total,
        ];
    }
}
