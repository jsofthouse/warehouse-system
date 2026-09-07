@extends('layouts.app')

@section('title', 'Invoice Sewa Gudang')
@section('pretitle', 'Transaksi')

@section('page-actions')
  <a href="{{ route('invoice.pilih') }}" class="btn btn-primary">
    <i class="ti ti-plus me-1"></i> Buat Invoice
  </a>
@endsection

@section('content')
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
          <th>Surat Jalan</th>
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
            <td>{{ $invoice->suratJalan?->nomor_surat_jalan ?? '—' }}</td>
            <td>
              {{ $invoice->periode_mulai->format('d/m/Y') }}
              &ndash;
              {{ $invoice->periode_selesai->format('d/m/Y') }}
            </td>
            <td class="text-end">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
            <td><span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
            <td>
              <a href="{{ route('invoice.show', $invoice) }}" class="btn btn-icon btn-sm" title="Detail">
                <i class="ti ti-eye"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada invoice.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $daftarInvoice->links() }}
  </div>
</div>
@endsection
