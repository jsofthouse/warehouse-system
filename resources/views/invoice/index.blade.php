{{--
    Skeleton — belum ada tombol "Buat Invoice". Mesin hitung invoice sesungguhnya
    masih terhalang keputusan arsitektur (a) Agregat vs (b) Per-batch/FIFO yang
    belum diputuskan tim internal, lihat "04-invoice-sewa-gudang.md" §0.
--}}
@extends('layouts.app')

@section('title', 'Invoice Sewa Gudang')
@section('pretitle', 'Transaksi')

@section('content')
<div class="alert alert-info">
  <strong>Modul ini masih skeleton.</strong> Mesin hitung invoice (buat, posting, cetak)
  belum dibangun — masih menunggu keputusan cara hitung saat barang keluar (agregat
  vs per-batch/FIFO). Lihat <code>docs/04-invoice-sewa-gudang.md</code> §0.
</div>

<div class="card">
  <div class="card-header">
    <form method="GET" class="d-flex gap-2 w-100">
      <select name="gudang_id" class="form-select" style="max-width: 260px" onchange="this.form.submit()">
        <option value="">Semua gudang</option>
        @foreach ($daftarGudang as $g)
          <option value="{{ $g->id }}" {{ (string) $gudangId === (string) $g->id ? 'selected' : '' }}>{{ $g->nama }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Nomor Invoice</th>
          <th>Gudang</th>
          <th>Periode</th>
          <th class="text-end">Total</th>
          <th>Status</th>
          <th class="w-1"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($daftarInvoice as $invoice)
          <tr>
            <td>{{ $invoice->nomor_invoice ?? '(draft)' }}</td>
            <td>{{ $invoice->gudang->nama }}</td>
            <td>
              {{ $invoice->periode_mulai->format('d/m/Y') }}
              &ndash;
              {{ $invoice->periode_selesai->format('d/m/Y') }}
            </td>
            <td class="text-end">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
            <td>{{ $invoice->status->value }}</td>
            <td></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada invoice.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $daftarInvoice->links() }}
  </div>
</div>
@endsection
