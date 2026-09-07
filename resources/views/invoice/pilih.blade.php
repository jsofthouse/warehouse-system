@extends('layouts.app')

@section('title', 'Buat Invoice')
@section('pretitle', 'Transaksi · Invoice Sewa Gudang')

@section('page-actions')
    <a href="{{ route('invoice.index') }}" class="btn btn-link">Kembali</a>
@endsection

@section('content')
    <div class="alert alert-info">
        Pilih surat jalan yang sudah diposting dan belum py invoice. Biaya sewanya
        dihitung otomatis dari alokasi batch yang tercatat saat surat jalan itu
        diposting (FIFO — barang yang masuk lebih dulu, dihitung keluar lebih dulu).
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Gudang</th>
                        <th>Lokasi Tujuan</th>
                        <th class="text-end">Total Unit</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarSuratJalan as $suratJalan)
                        <tr>
                            <td><span class="badge bg-blue-lt">{{ $suratJalan->nomor_surat_jalan }}</span></td>
                            <td>{{ $suratJalan->tanggal->format('d/m/Y') }}</td>
                            <td>{{ $suratJalan->gudang->nama }}</td>
                            <td>{{ $suratJalan->lokasi?->nama_kodim ?? '—' }}</td>
                            <td class="text-end">{{ number_format((int) $suratJalan->total_unit, 0, ',', '.') }}</td>
                            <td>
                                <a href="{{ route('invoice.buat.draft', $suratJalan) }}" class="btn btn-primary btn-sm">
                                    Buat Invoice
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">
                                Tidak ada surat jalan yang siap ditagih — semua yang posted/diterima sudah py invoice.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center">
            {{ $daftarSuratJalan->links() }}
        </div>
    </div>
@endsection
