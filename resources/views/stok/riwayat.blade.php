@extends('layouts.app')

@section('title', 'Riwayat Stok — '.$item->nama)
@section('pretitle', 'Kartu Stok')

@section('page-actions')
    <a href="{{ route('stok.index', ['gudang_id' => $gudang->id]) }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i> Kembali
    </a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Gudang</div>
                    <div class="h3 mb-0">{{ $gudang->nama }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary">Stok On-Hand Saat Ini</div>
                    <div class="h3 mb-0 {{ $stokSaatIni <= 0 ? 'text-danger' : '' }}">
                        {{ number_format($stokSaatIni, 0, ',', '.') }} {{ $item->satuan }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-2 w-100">
                <input type="hidden" name="gudang_id" value="{{ $gudang->id }}">

                <div class="col-md-2">
                    <select name="tipe" class="form-select">
                        <option value="">Semua tipe</option>
                        <option value="IN" {{ ($filter['tipe'] ?? '') === 'IN' ? 'selected' : '' }}>Masuk (IN)</option>
                        <option value="OUT" {{ ($filter['tipe'] ?? '') === 'OUT' ? 'selected' : '' }}>Keluar (OUT)</option>
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

                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-filter me-1"></i> Terapkan</button>
                    <a href="{{ route('stok.riwayat', ['item' => $item->id, 'gudang_id' => $gudang->id]) }}"
                        class="btn btn-outline-secondary" title="Reset filter">
                        <i class="ti ti-x"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-end">Saldo Berjalan</th>
                        <th>Sumber Dokumen</th>
                        <th>Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $mutasi)
                        <tr>
                            <td>{{ $mutasi->tanggal->translatedFormat('d M Y') }}</td>
                            <td>
                                @if ($mutasi->tipe->value === 'IN')
                                    <span class="badge bg-green-lt">Masuk</span>
                                @else
                                    <span class="badge bg-red-lt">Keluar</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($mutasi->jumlah, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">{{ number_format($mutasi->saldo_berjalan, 0, ',', '.') }}</td>
                            <td>
                                @if ($mutasi->referensi instanceof \App\Models\Penerimaan)
                                    <a href="{{ route('penerimaan.show', $mutasi->referensi) }}">
                                        {{ $mutasi->referensi->nomor_penerimaan ?? '(draft)' }}
                                    </a>
                                @elseif ($mutasi->referensi instanceof \App\Models\SuratJalan)
                                    <a href="{{ route('surat-jalan.show', $mutasi->referensi) }}">
                                        {{ $mutasi->referensi->nomor_surat_jalan ?? '(draft)' }}
                                    </a>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                                @if ($mutasi->keterangan)
                                    <div class="text-secondary small">{{ $mutasi->keterangan }}</div>
                                @endif
                            </td>
                            <td class="text-secondary">{{ $mutasi->pembuat?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">Tidak ada mutasi yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($riwayat->hasPages())
            <div class="card-footer d-flex align-items-center">
                {{ $riwayat->links() }}
            </div>
        @endif
    </div>
@endsection
