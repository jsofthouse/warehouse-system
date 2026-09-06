@extends('layouts.app')

@section('title', $penerimaan->nomor_penerimaan ?? 'Draft Penerimaan')
@section('pretitle', 'Transaksi · Penerimaan Barang')

@section('page-actions')
    <div class="btn-list">
        <a href="{{ route('penerimaan.index') }}" class="btn btn-link">Kembali</a>

        <x-riwayat-link :subjek="$penerimaan" label="Riwayat" class="btn-outline-secondary" />

        @can('cetak', $penerimaan)
            <a href="{{ route('penerimaan.cetak', $penerimaan) }}" class="btn btn-outline-secondary" target="_blank"
                rel="noopener">
                <i class="ti ti-printer me-1"></i> Cetak
            </a>
        @endcan

        @can('update', $penerimaan)
            <a href="{{ route('penerimaan.edit', $penerimaan) }}" class="btn btn-outline-primary">
                <i class="ti ti-pencil me-1"></i> Ubah
            </a>
        @endcan

        @can('posting', $penerimaan)
            <form method="POST" action="{{ route('penerimaan.posting', $penerimaan) }}" id="form-posting">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-checkbox me-1"></i> Posting
                </button>
            </form>
        @endcan

        @can('batalkan', $penerimaan)
            <a href="{{ route('penerimaan.batalkan.form', $penerimaan) }}" class="btn btn-outline-danger">
                <i class="ti ti-ban me-1"></i> Batalkan
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @if ($penerimaan->status === App\Enums\StatusPenerimaan::Dibatalkan)
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-title">Dokumen dibatalkan</h4>
            <div class="text-secondary">
                Dibatalkan {{ $penerimaan->dibatalkan_at?->format('d/m/Y H:i') }}
                oleh {{ $penerimaan->pembatal?->name ?? '—' }}.
                Stok sudah dikembalikan lewat mutasi balik.
            </div>
            <div class="mt-2"><strong>Alasan:</strong> {{ $penerimaan->alasan_pembatalan }}</div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Header Dokumen</h3>
                    <div class="card-actions">
                        <span class="badge {{ $penerimaan->status->badge() }}">{{ $penerimaan->status->label() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nomor</dt>
                        <dd class="col-7">{{ $penerimaan->nomor_penerimaan ?? 'belum terbit (draft)' }}</dd>

                        <dt class="col-5 text-secondary">Tanggal</dt>
                        <dd class="col-7">{{ $penerimaan->tanggal->format('d/m/Y') }}</dd>

                        <dt class="col-5 text-secondary">Gudang</dt>
                        <dd class="col-7">{{ $penerimaan->gudang->nama }}</dd>

                        <dt class="col-5 text-secondary">Vendor</dt>
                        <dd class="col-7">{{ $penerimaan->vendor_nama }}</dd>

                        <dt class="col-5 text-secondary">SJ Vendor</dt>
                        <dd class="col-7">{{ $penerimaan->nomor_dokumen_vendor ?: '—' }}</dd>

                        <dt class="col-5 text-secondary">Kontrak/Ref</dt>
                        <dd class="col-7">{{ $penerimaan->no_kontrak_referensi ?: '—' }}</dd>

                        <dt class="col-5 text-secondary">Keterangan</dt>
                        <dd class="col-7">{{ $penerimaan->keterangan ?: '—' }}</dd>
                    </dl>
                </div>
                <div class="card-footer text-secondary small">
                    Dibuat {{ $penerimaan->created_at?->format('d/m/Y H:i') }}
                    oleh {{ $penerimaan->pembuat?->name ?? '—' }}.
                    @if ($penerimaan->posted_at)
                        <br>Diposting {{ $penerimaan->posted_at->format('d/m/Y H:i') }}
                        oleh {{ $penerimaan->poster?->name ?? '—' }}.
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Baris Barang</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Item</th>
                                <th class="text-end">Jumlah</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($penerimaan->detail as $detail)
                                <tr>
                                    <td><span class="badge bg-blue-lt">{{ $detail->item?->kode ?? '—' }}</span></td>
                                    <td>{{ $detail->item?->nama ?? 'Item terhapus' }}</td>
                                    <td class="text-end">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                    <td>{{ $detail->item?->satuan ?? '—' }}</td>
                                    <td class="text-secondary">{{ $detail->keterangan ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">
                                        Belum ada baris barang. Dokumen tidak bisa diposting sebelum diisi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end">{{ number_format($penerimaan->totalUnit(), 0, ',', '.') }}</th>
                                <th colspan="2" class="fw-normal text-secondary">unit</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Posting tidak bisa dibatalkan lewat tombol undo — konfirmasinya menyebut
        // akibatnya secara spesifik, bukan "Anda yakin?" ("07-panduan-frontend.md" §6).
        document.getElementById('form-posting')?.addEventListener('submit', function(e) {
            if (this.dataset.dikonfirmasi === '1') return;

            e.preventDefault();

            Swal.fire({
                title: 'Posting penerimaan ini?',
                html: 'Nomor dokumen resmi akan terbit dan <b>stok gudang bertambah ' +
                    {{ (int) $penerimaan->totalUnit() }} + ' unit</b>.<br>' +
                    'Setelah diposting dokumen tidak bisa diedit lagi.',
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
