@extends('layouts.app')

@section('title', 'Item / Alkap Pertanian')
@section('pretitle', 'Master Data')

@section('page-actions')
    @if (auth()->user()->role->bisaKelolaMasterData())
        <a href="{{ route('master.item.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Item
        </a>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex gap-2 w-100">
                <input type="text" name="q" value="{{ $q }}" class="form-control"
                    placeholder="Cari kode atau nama item…">
                <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <th class="text-end">Berat</th>
                        <th class="text-end">Dimensi P×L×T (cm)</th>
                        <th class="text-end">Volume (m³)</th>
                        <th>Status</th>
                        @if (auth()->user()->role->bisaKelolaMasterData())
                            <th class="w-1"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarItem as $item)
                        <tr>
                            <td><span class="badge bg-blue-lt">{{ $item->kode }}</span></td>
                            <td>{{ $item->nama }}</td>
                            <td>{{ $item->satuan }}</td>
                            <td class="text-end">
                                @if (is_null($item->berat_kg))
                                    <span class="badge bg-yellow-lt" title="Belum ada data dari klien">belum ada</span>
                                @else
                                    {{ number_format($item->berat_kg, 2, ',', '.') }} kg
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($item->panjang_cm && $item->lebar_cm && $item->tinggi_cm)
                                    {{ rtrim(rtrim($item->panjang_cm, '0'), '.') }}×{{ rtrim(rtrim($item->lebar_cm, '0'), '.') }}×{{ rtrim(rtrim($item->tinggi_cm, '0'), '.') }}
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $item->volume_m3 ? number_format($item->volume_m3, 4) : '—' }}</td>
                            <td>
                                @if ($item->is_active)
                                    <span class="badge bg-green-lt">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-lt">Nonaktif</span>
                                @endif
                            </td>
                            @if (auth()->user()->role->bisaKelolaMasterData())
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <x-riwayat-link :subjek="$item" />
                                        <a href="{{ route('master.item.edit', $item) }}" class="btn btn-icon btn-sm"
                                            title="Ubah">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('master.item.destroy', $item) }}"
                                            onsubmit="return confirm('Hapus item &quot;{{ $item->nama }}&quot;? Aksi ini tidak bisa dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-sm text-danger" title="Hapus">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">Belum ada data item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center">
            {{ $daftarItem->links() }}
        </div>
    </div>
@endsection
