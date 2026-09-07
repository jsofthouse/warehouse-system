@extends('layouts.app')

@section('title', $invoice->nomor_invoice ?? 'Draft Invoice')
@section('pretitle', 'Transaksi · Invoice Sewa Gudang')

@section('page-actions')
    <div class="btn-list">
        <a href="{{ route('invoice.index') }}" class="btn btn-link">Kembali</a>

        <x-riwayat-link :subjek="$invoice" label="Riwayat" class="btn-outline-secondary" />

        <a href="{{ route('invoice.cetak', $invoice) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
            <i class="ti ti-printer me-1"></i> Cetak
        </a>

        @if ($invoice->status === App\Enums\StatusInvoice::Draft)
            <form method="POST" action="{{ route('invoice.posting', $invoice) }}" id="form-posting">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-checkbox me-1"></i> Posting
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Header Invoice</h3>
                    <div class="card-actions">
                        <span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nomor</dt>
                        <dd class="col-7">{{ $invoice->nomor_invoice ?? 'belum terbit (draft)' }}</dd>

                        <dt class="col-5 text-secondary">Gudang</dt>
                        <dd class="col-7">{{ $invoice->gudang->nama }}</dd>

                        <dt class="col-5 text-secondary">Surat Jalan</dt>
                        <dd class="col-7">
                            {{ $invoice->suratJalan?->nomor_surat_jalan ?? '—' }}
                        </dd>

                        <dt class="col-5 text-secondary">Periode Simpan</dt>
                        <dd class="col-7">
                            {{ $invoice->periode_mulai->format('d/m/Y') }}
                            &ndash;
                            {{ $invoice->periode_selesai->format('d/m/Y') }}
                        </dd>

                        <dt class="col-5 text-secondary">Tertagih</dt>
                        <dd class="col-7">{{ $invoice->nama_tertagih ?: '—' }}</dd>
                    </dl>
                </div>
                <div class="card-footer text-secondary small">
                    Dibuat {{ $invoice->created_at?->format('d/m/Y H:i') }}
                    oleh {{ $invoice->pembuat?->name ?? '—' }}.
                    @if ($invoice->posted_at)
                        <br>Diposting {{ $invoice->posted_at->format('d/m/Y H:i') }}
                        oleh {{ $invoice->poster?->name ?? '—' }}.
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rincian per Item</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Unit-hari</th>
                                <th class="text-end">kg-hari</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice->detail as $detail)
                                <tr>
                                    <td>{{ $detail->item?->nama ?? 'Item terhapus' }}</td>
                                    <td class="text-end">{{ number_format($detail->total_unit_hari, 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($detail->total_satuan_hari, 2, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4">Belum ada rincian item.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <dl class="row mb-0 justify-content-end text-end">
                        <dt class="col-3">Subtotal</dt>
                        <dd class="col-3">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</dd>

                        <dt class="col-3">PPN {{ rtrim(rtrim((string) $invoice->ppn_persen, '0'), '.') }}%</dt>
                        <dd class="col-3">Rp {{ number_format($invoice->ppn_nominal, 0, ',', '.') }}</dd>

                        <dt class="col-3 fw-bold">Total</dt>
                        <dd class="col-3 fw-bold">Rp {{ number_format($invoice->total, 0, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-posting')?.addEventListener('submit', function(e) {
            if (this.dataset.dikonfirmasi === '1') return;

            e.preventDefault();

            Swal.fire({
                title: 'Posting invoice ini?',
                html: 'Nomor invoice resmi akan terbit dan dokumen terkunci — rincian tidak bisa diubah lagi setelah ini.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, posting',
                cancelButtonText: 'Batal',
            }).then((hasil) => {
                if (hasil.isConfirmed) {
                    this.dataset.dikonfirmasi = '1';
                    this.submit();
                }
            });
        });
    </script>
@endpush
