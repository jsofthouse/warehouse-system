@extends('layouts.app')

@section('title', 'Draft Invoice')
@section('pretitle', 'Transaksi · Invoice Sewa Gudang')

@section('page-actions')
    <a href="{{ route('invoice.pilih') }}" class="btn btn-link">Kembali</a>
@endsection

@section('content')
    <div class="alert alert-warning">
        <strong>Ini pratinjau, belum tersimpan.</strong> Angka final dihitung ulang
        di server saat Anda menekan Simpan — bukan angka yang tampil di layar ini.
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Surat Jalan</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nomor</dt>
                        <dd class="col-7">{{ $suratJalan->nomor_surat_jalan }}</dd>

                        <dt class="col-5 text-secondary">Tanggal</dt>
                        <dd class="col-7">{{ $suratJalan->tanggal->format('d/m/Y') }}</dd>

                        <dt class="col-5 text-secondary">Gudang</dt>
                        <dd class="col-7">{{ $suratJalan->gudang->nama }}</dd>

                        <dt class="col-5 text-secondary">Lokasi Tujuan</dt>
                        <dd class="col-7">{{ $suratJalan->lokasi?->nama_kodim ?? '—' }}</dd>

                        <dt class="col-5 text-secondary">Periode Simpan</dt>
                        <dd class="col-7">
                            {{ $hitung['periode_mulai']->format('d/m/Y') }}
                            &ndash;
                            {{ $hitung['periode_selesai']->format('d/m/Y') }}
                        </dd>
                    </dl>
                </div>
            </div>

            <form method="POST" action="{{ route('invoice.store') }}">
                @csrf
                <input type="hidden" name="surat_jalan_id" value="{{ $suratJalan->id }}">

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pihak Tertagih</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="nama_tertagih">Nama</label>
                            <input id="nama_tertagih" name="nama_tertagih" type="text" maxlength="255"
                                value="{{ old('nama_tertagih') }}" class="form-control @error('nama_tertagih') is-invalid @enderror">
                            @error('nama_tertagih') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="alamat_tertagih">Alamat</label>
                            <textarea id="alamat_tertagih" name="alamat_tertagih" rows="2" maxlength="2000"
                                class="form-control @error('alamat_tertagih') is-invalid @enderror">{{ old('alamat_tertagih') }}</textarea>
                            @error('alamat_tertagih') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="npwp_tertagih">NPWP</label>
                            <input id="npwp_tertagih" name="npwp_tertagih" type="text" maxlength="30"
                                value="{{ old('npwp_tertagih') }}" class="form-control @error('npwp_tertagih') is-invalid @enderror">
                            <small class="form-hint">Kosongkan kalau belum ada — masih bisa dilengkapi lewat data internal.</small>
                            @error('npwp_tertagih') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Draft Invoice
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rincian per Item (basis berat aktual)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Unit-hari</th>
                                <th class="text-end">kg / unit</th>
                                <th class="text-end">kg-hari</th>
                                <th class="text-end">Harga Jual / hari</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hitung['baris'] as $baris)
                                @php $item = $suratJalan->detail->firstWhere('item_id', $baris['item_id'])?->item @endphp
                                <tr>
                                    <td>{{ $item?->nama ?? "item #{$baris['item_id']}" }}</td>
                                    <td class="text-end">{{ number_format($baris['total_unit_hari'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($baris['satuan_per_unit'], 2, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($baris['total_satuan_hari'], 2, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format($baris['harga_jual_per_satuan_per_hari'], 2, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format($baris['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <dl class="row mb-0 justify-content-end text-end">
                        <dt class="col-3">Subtotal</dt>
                        <dd class="col-3">Rp {{ number_format($hitung['subtotal'], 0, ',', '.') }}</dd>

                        <dt class="col-3">PPN {{ rtrim(rtrim((string) $hitung['ppn_persen'], '0'), '.') }}%</dt>
                        <dd class="col-3">Rp {{ number_format($hitung['ppn_nominal'], 0, ',', '.') }}</dd>

                        <dt class="col-3 fw-bold">Total</dt>
                        <dd class="col-3 fw-bold">Rp {{ number_format($hitung['total'], 0, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
