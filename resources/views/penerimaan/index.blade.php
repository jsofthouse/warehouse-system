@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('pretitle', 'Transaksi')

@section('page-actions')
    @can('create', App\Models\Penerimaan::class)
        <a href="{{ route('penerimaan.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Penerimaan
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-2 w-100">
                @if ($lintasGudang)
                    <div class="col-md-3">
                        <select name="gudang_id" class="form-select">
                            <option value="">Semua gudang</option>
                            @foreach ($daftarGudang as $gudang)
                                <option value="{{ $gudang->id }}"
                                    {{ (string) ($filter['gudang_id'] ?? '') === (string) $gudang->id ? 'selected' : '' }}>
                                    {{ $gudang->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (App\Enums\StatusPenerimaan::cases() as $status)
                            <option value="{{ $status->value }}"
                                {{ ($filter['status'] ?? '') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" name="dari" value="{{ $filter['dari'] ?? '' }}" class="form-control"
                        aria-label="Tanggal mulai">
                </div>

                <div class="col-md-2">
                    <input type="date" name="sampai" value="{{ $filter['sampai'] ?? '' }}" class="form-control"
                        aria-label="Tanggal sampai">
                </div>

                <div class="col">
                    <div class="input-group">
                        <input type="text" name="q" value="{{ $filter['q'] ?? '' }}" class="form-control"
                            placeholder="Cari vendor atau nomor dokumen…" maxlength="100">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
                        <a href="{{ route('penerimaan.index') }}" class="btn btn-outline-secondary" title="Reset filter">
                            <i class="ti ti-x"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Vendor</th>
                        @if ($lintasGudang)
                            <th>Gudang</th>
                        @endif
                        <th class="text-end">Total Unit</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarPenerimaan as $penerimaan)
                        <tr>
                            <td>
                                @if ($penerimaan->nomor_penerimaan)
                                    <span class="badge bg-blue-lt">{{ $penerimaan->nomor_penerimaan }}</span>
                                @else
                                    <span class="text-secondary">belum bernomor</span>
                                @endif
                            </td>
                            <td>{{ $penerimaan->tanggal->format('d/m/Y') }}</td>
                            <td>{{ $penerimaan->vendor_nama }}</td>
                            @if ($lintasGudang)
                                <td>{{ $penerimaan->gudang->nama }}</td>
                            @endif
                            <td class="text-end">{{ number_format((int) $penerimaan->total_unit, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $penerimaan->status->badge() }}">
                                    {{ $penerimaan->status->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <x-riwayat-link :subjek="$penerimaan" />
                                    <a href="{{ route('penerimaan.show', $penerimaan) }}" class="btn btn-icon btn-sm"
                                        title="Lihat detail">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    @can('update', $penerimaan)
                                        <a href="{{ route('penerimaan.edit', $penerimaan) }}" class="btn btn-icon btn-sm"
                                            title="Ubah draft">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $lintasGudang ? 7 : 6 }}" class="text-center text-secondary py-4">
                                Belum ada penerimaan yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center">
            {{ $daftarPenerimaan->links() }}
        </div>
    </div>
@endsection
