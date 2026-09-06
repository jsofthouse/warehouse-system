@extends('layouts.app')

@section('title', 'Surat Jalan')
@section('pretitle', 'Transaksi')

@section('page-actions')
    @can('create', App\Models\SuratJalan::class)
        <a href="{{ route('surat-jalan.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Surat Jalan
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-2 w-100">
                @if ($lintasGudang)
                    <div class="col-md-2">
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

                <div class="col-md-3">
                    <select name="lokasi_id" class="form-select">
                        <option value="">Semua lokasi tujuan</option>
                        @foreach ($daftarLokasi as $lokasi)
                            <option value="{{ $lokasi->id }}"
                                {{ (string) ($filter['lokasi_id'] ?? '') === (string) $lokasi->id ? 'selected' : '' }}>
                                {{ $lokasi->kode }} — {{ $lokasi->nama_kodim }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (App\Enums\StatusSuratJalan::cases() as $status)
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
                            placeholder="Cari nomor, ekspedisi, nopol, sopir…" maxlength="100">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
                        <a href="{{ route('surat-jalan.index') }}" class="btn btn-outline-secondary"
                            title="Reset filter">
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
                        <th>Lokasi Tujuan</th>
                        @if ($lintasGudang)
                            <th>Gudang</th>
                        @endif
                        <th>Kendaraan</th>
                        <th class="text-end">Total Unit</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarSuratJalan as $suratJalan)
                        <tr>
                            <td>
                                @if ($suratJalan->nomor_surat_jalan)
                                    <span class="badge bg-blue-lt">{{ $suratJalan->nomor_surat_jalan }}</span>
                                @else
                                    <span class="text-secondary">belum bernomor</span>
                                @endif
                            </td>
                            <td>{{ $suratJalan->tanggal->format('d/m/Y') }}</td>
                            <td>
                                {{ $suratJalan->lokasi?->nama_kodim ?? '—' }}
                                <div class="small text-secondary">
                                    {{ $suratJalan->lokasi?->kabupaten }}{{ $suratJalan->lokasi?->provinsi ? ', ' . $suratJalan->lokasi->provinsi : '' }}
                                </div>
                            </td>
                            @if ($lintasGudang)
                                <td>{{ $suratJalan->gudang->nama }}</td>
                            @endif
                            <td class="text-secondary">
                                {{ $suratJalan->nomor_polisi ?: '—' }}
                                @if ($suratJalan->nama_sopir)
                                    <div class="small">{{ $suratJalan->nama_sopir }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((int) $suratJalan->total_unit, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge {{ $suratJalan->status->badge() }}">
                                    {{ $suratJalan->status->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('surat-jalan.show', $suratJalan) }}" class="btn btn-icon btn-sm"
                                        title="Lihat detail">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    @can('update', $suratJalan)
                                        <a href="{{ route('surat-jalan.edit', $suratJalan) }}"
                                            class="btn btn-icon btn-sm" title="Ubah draft">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $lintasGudang ? 8 : 7 }}" class="text-center text-secondary py-4">
                                Belum ada surat jalan yang cocok dengan filter ini.
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
