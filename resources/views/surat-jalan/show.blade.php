@extends('layouts.app')

@section('title', $suratJalan->nomor_surat_jalan ?? 'Draft Surat Jalan')
@section('pretitle', 'Transaksi · Surat Jalan')

@section('page-actions')
    <div class="btn-list">
        <a href="{{ route('surat-jalan.index') }}" class="btn btn-link">Kembali</a>

        <x-riwayat-link :subjek="$suratJalan" label="Riwayat" class="btn-outline-secondary" />

        @can('cetak', $suratJalan)
            <a href="{{ route('surat-jalan.cetak', $suratJalan) }}" class="btn btn-outline-secondary" target="_blank"
                rel="noopener">
                <i class="ti ti-printer me-1"></i> Cetak
            </a>
        @endcan

        @can('update', $suratJalan)
            <a href="{{ route('surat-jalan.edit', $suratJalan) }}" class="btn btn-outline-primary">
                <i class="ti ti-pencil me-1"></i> Ubah
            </a>
        @endcan

        @can('posting', $suratJalan)
            <form method="POST" action="{{ route('surat-jalan.posting', $suratJalan) }}" id="form-posting">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-checkbox me-1"></i> Posting
                </button>
            </form>
        @endcan

        @can('tandaiDiterima', $suratJalan)
            <a href="{{ route('surat-jalan.diterima.form', $suratJalan) }}" class="btn btn-success">
                <i class="ti ti-package-import me-1"></i> Tandai Diterima
            </a>
        @endcan

        @can('batalkan', $suratJalan)
            <a href="{{ route('surat-jalan.batalkan.form', $suratJalan) }}" class="btn btn-outline-danger">
                <i class="ti ti-ban me-1"></i> Batalkan
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @if ($suratJalan->status === App\Enums\StatusSuratJalan::Dibatalkan)
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-title">Dokumen dibatalkan</h4>
            <div class="text-secondary">
                Dibatalkan {{ $suratJalan->dibatalkan_at?->format('d/m/Y H:i') }}
                oleh {{ $suratJalan->pembatal?->name ?? '—' }}.
                Stok sudah dikembalikan lewat mutasi balik.
            </div>
            <div class="mt-2"><strong>Alasan:</strong> {{ $suratJalan->alasan_pembatalan }}</div>
        </div>
    @endif

    @if ($suratJalan->status === App\Enums\StatusSuratJalan::Diterima)
        <div class="alert alert-success" role="alert">
            <h4 class="alert-title">Barang sudah diterima di lokasi</h4>
            <div>
                Diterima {{ $suratJalan->tanggal_terima?->format('d/m/Y') }}
                oleh <strong>{{ $suratJalan->nama_penerima ?? '—' }}</strong>.
                @if ($suratJalan->nomor_bast)
                    Nomor BAST: {{ $suratJalan->nomor_bast }}.
                @endif
            </div>
            <div class="text-secondary mt-1">
                Dokumen yang sudah diterima tidak bisa dibatalkan lagi — koreksi lewat retur atau kiriman susulan.
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Header Dokumen</h3>
                    <div class="card-actions">
                        <span class="badge {{ $suratJalan->status->badge() }}">{{ $suratJalan->status->label() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nomor</dt>
                        <dd class="col-7">{{ $suratJalan->nomor_surat_jalan ?? 'belum terbit (draft)' }}</dd>

                        <dt class="col-5 text-secondary">Tanggal</dt>
                        <dd class="col-7">{{ $suratJalan->tanggal->format('d/m/Y') }}</dd>

                        <dt class="col-5 text-secondary">Gudang Asal</dt>
                        <dd class="col-7">{{ $suratJalan->gudang->nama }}</dd>

                        <dt class="col-5 text-secondary">Lokasi Tujuan</dt>
                        <dd class="col-7">
                            {{ $suratJalan->lokasi?->nama_kodim ?? '—' }}
                            <div class="small text-secondary">
                                {{ collect([$suratJalan->lokasi?->desa, $suratJalan->lokasi?->kecamatan, $suratJalan->lokasi?->kabupaten, $suratJalan->lokasi?->provinsi])->filter()->implode(', ') }}
                            </div>
                        </dd>

                        <dt class="col-5 text-secondary">Ekspedisi</dt>
                        <dd class="col-7">{{ $suratJalan->ekspedisi ?: '—' }}</dd>

                        <dt class="col-5 text-secondary">Kendaraan</dt>
                        <dd class="col-7">{{ $suratJalan->nomor_polisi ?: '—' }}</dd>

                        <dt class="col-5 text-secondary">Sopir</dt>
                        <dd class="col-7">{{ $suratJalan->nama_sopir ?: '—' }}</dd>

                        <dt class="col-5 text-secondary">Rit ke lokasi ini</dt>
                        <dd class="col-7">
                            @if ($pengiriman['ke'])
                                Pengiriman ke-{{ $pengiriman['ke'] }} dari {{ $pengiriman['dari'] }}
                            @else
                                <span class="text-secondary">belum terhitung (dokumen belum diposting)</span>
                            @endif
                        </dd>
                    </dl>
                </div>
                <div class="card-footer text-secondary small">
                    Dibuat {{ $suratJalan->created_at?->format('d/m/Y H:i') }}
                    oleh {{ $suratJalan->pembuat?->name ?? '—' }}.
                    @if ($suratJalan->posted_at)
                        <br>Diposting {{ $suratJalan->posted_at->format('d/m/Y H:i') }}
                        oleh {{ $suratJalan->poster?->name ?? '—' }}.
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Barang yang Dikirim</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Item</th>
                                <th class="text-end">Jumlah Kirim</th>
                                <th>Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suratJalan->detail as $detail)
                                <tr>
                                    <td><span class="badge bg-blue-lt">{{ $detail->item?->kode ?? '—' }}</span></td>
                                    <td>{{ $detail->item?->nama ?? 'Item terhapus' }}</td>
                                    <td class="text-end">{{ number_format($detail->jumlah_kirim, 0, ',', '.') }}</td>
                                    <td>{{ $detail->item?->satuan ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4">
                                        Belum ada barang yang diisi. Dokumen tidak bisa diposting sebelum diisi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end">{{ number_format($suratJalan->totalUnit(), 0, ',', '.') }}</th>
                                <th class="fw-normal text-secondary">unit</th>
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
                title: 'Posting surat jalan ini?',
                html: 'Nomor dokumen resmi akan terbit dan <b>stok gudang berkurang ' +
                    {{ (int) $suratJalan->totalUnit() }} + ' unit</b>.<br>' +
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
