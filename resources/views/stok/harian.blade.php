@extends('layouts.app')

@section('title', 'Stok Harian')
@section('pretitle', 'Transaksi')

@section('page-actions')
    <a href="{{ route('stok.index', ['gudang_id' => $gudang->id]) }}" class="btn btn-outline-secondary">
        <i class="ti ti-clipboard-list me-1"></i> Kartu Stok
    </a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <form method="GET" class="row g-2 w-100">
                @if ($lintasGudang)
                    <div class="col-md-3">
                        <select name="gudang_id" class="form-select">
                            @foreach ($daftarGudang as $g)
                                <option value="{{ $g->id }}" {{ $gudang->id === $g->id ? 'selected' : '' }}>
                                    {{ $g->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-2">
                    <input type="date" name="dari" value="{{ $filter['dari'] }}" class="form-control" aria-label="Tanggal mulai">
                </div>
                <div class="col-md-2">
                    <input type="date" name="sampai" value="{{ $filter['sampai'] }}" class="form-control" aria-label="Tanggal sampai">
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-filter me-1"></i> Tampilkan</button>
                </div>

                <div class="col-auto ms-auto">
                    <button type="button" id="btn-hitung-ulang" class="btn btn-primary">
                        <i class="ti ti-refresh me-1"></i> Hitung Ulang
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-vcenter table-bordered card-table">
                    <thead>
                        <tr>
                            <th class="w-1">Tanggal</th>
                            @foreach ($daftarItem as $item)
                                <th class="text-end">{{ $item->nama }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarTanggal as $tanggal)
                            @php $baris = $snapshot->get($tanggal->toDateString()); @endphp
                            <tr>
                                <td class="text-nowrap">{{ $tanggal->translatedFormat('d M Y') }}</td>
                                @foreach ($daftarItem as $item)
                                    @php $sel = $baris?->get($item->id); @endphp
                                    <td class="text-end">
                                        @if ($sel)
                                            {{ number_format($sel->stok_akhir, 0, ',', '.') }}
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $daftarItem->count() + 1 }}" class="text-center text-secondary py-4">
                                    Tidak ada rentang tanggal untuk ditampilkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-secondary small mb-0">
                Tanda "—" berarti snapshot belum pernah dihitung untuk tanggal itu, bukan berarti stoknya nol.
                Klik <b>Hitung Ulang</b> untuk menghitung rentang yang sedang ditampilkan dari mutasi stok aktual.
            </p>
        </div>
    </div>

    <form id="form-hitung-ulang" method="POST" action="{{ route('stok.harian.hitung-ulang') }}" class="d-none">
        @csrf
        <input type="hidden" name="gudang_id" value="{{ $gudang->id }}">
        <input type="hidden" name="dari" value="{{ $filter['dari'] }}">
        <input type="hidden" name="sampai" value="{{ $filter['sampai'] }}">
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('btn-hitung-ulang')?.addEventListener('click', function() {
            Swal.fire({
                title: 'Hitung ulang stok harian?',
                html: 'Snapshot untuk <b>{{ $gudang->nama }}</b>, {{ $filter['dari'] }} s/d {{ $filter['sampai'] }} ' +
                    'akan dihitung ulang dari mutasi stok aktual. Ini bisa memakan waktu beberapa detik.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, hitung ulang',
                cancelButtonText: 'Batal',
            }).then((hasil) => {
                if (hasil.isConfirmed) {
                    document.getElementById('form-hitung-ulang').submit();
                }
            });
        });
    </script>
@endpush
