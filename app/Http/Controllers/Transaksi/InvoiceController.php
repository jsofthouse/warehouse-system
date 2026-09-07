<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Modul Invoice Sewa Gudang — skeleton.
 *
 * Belum ada logic hitung (buat/posting/cetak) di sini. Mesin hitung invoice
 * sesungguhnya masih terhalang keputusan arsitektur (a) Agregat vs (b)
 * Per-batch/FIFO yang belum diputuskan tim internal — lihat
 * "04-invoice-sewa-gudang.md" §0. Jangan tambah logic hitung sebelum itu clear.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $gudangId = $request->query('gudang_id', '');

        $daftarInvoice = Invoice::query()
            ->with('gudang')
            ->when($gudangId !== '', fn ($q) => $q->where('gudang_id', $gudangId))
            ->orderByDesc('periode_mulai')
            ->paginate(20)
            ->withQueryString();

        $daftarGudang = Gudang::orderBy('nama')->get();

        return view('invoice.index', compact('daftarInvoice', 'daftarGudang', 'gudangId'));
    }

    /**
     * Template cetak standar — dipakai buat cek layout duluan sebelum mesin
     * hitung ada. Aman dipanggil untuk invoice draft sekalipun (nomor & detail
     * akan kosong/placeholder di templatenya sendiri).
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
}
