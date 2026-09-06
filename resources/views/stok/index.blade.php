@extends('layouts.app')

@section('title', 'Kartu Stok')
@section('pretitle', 'Transaksi')

@section('page-actions')
    @if ($lintasGudang)
        <a href="{{ route('stok.harian', $filter) }}" class="btn btn-outline-secondary">
            <i class="ti ti-calendar-stats me-1"></i> Stok Harian
        </a>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-2 w-100">
                @if ($lintasGudang)
                    <div class="col-md-3">
                        <select name="gudang_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($daftarGudang as $gudang)
                                <option value="{{ $gudang->id }}"
                                    {{ $gudangDipilih?->id === $gudang->id ? 'selected' : '' }}>
                                    {{ $gudang->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-auto">
                        <span class="form-control-plaintext fw-bold">{{ $gudangDipilih?->nama }}</span>
                    </div>
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Satuan</th>
                        <th class="text-end">Stok On-Hand</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ringkasan as $baris)
                        <tr>
                            <td>{{ $baris['item']->nama }}</td>
                            <td class="text-secondary">{{ $baris['item']->satuan }}</td>
                            <td class="text-end fw-bold {{ $baris['stok'] <= 0 ? 'text-danger' : '' }}">
                                {{ number_format($baris['stok'], 0, ',', '.') }}
                            </td>
                            <td>
                                <a href="{{ route('stok.riwayat', ['item' => $baris['item']->id, 'gudang_id' => $gudangDipilih?->id]) }}"
                                    class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-history me-1"></i> Riwayat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">Belum ada item aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
