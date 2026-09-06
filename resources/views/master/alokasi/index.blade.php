@extends('layouts.app')

@section('title', 'Alokasi Kebutuhan')
@section('pretitle', 'Master Data')

@section('page-actions')
  @if (auth()->user()->role->bisaKelolaMasterData())
    <a href="{{ route('master.alokasi.set-massal.form') }}" class="btn btn-primary">
      <i class="ti ti-copy me-1"></i> Set Massal Seragam
    </a>
  @endif
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <form method="GET" class="d-flex gap-2 w-100">
      <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari kode atau nama kodim…">
      <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Lokasi</th>
          <th>Provinsi</th>
          <th class="text-end">Item Diatur</th>
          <th class="text-end">Total Alokasi (unit)</th>
          <th class="w-1"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($lokasis as $lokasi)
          <tr>
            <td><span class="badge bg-blue-lt">{{ $lokasi->kode }}</span></td>
            <td>{{ $lokasi->nama_kodim }}</td>
            <td>{{ $lokasi->provinsi }}</td>
            <td class="text-end">
              {{ $lokasi->jumlah_item_diatur }} / {{ $totalItemAktif }}
              @if ($lokasi->jumlah_item_diatur < $totalItemAktif)
                <span class="badge bg-yellow-lt ms-1" title="Belum semua item diatur alokasinya">belum lengkap</span>
              @endif
            </td>
            <td class="text-end">{{ $lokasi->total_alokasi ?? 0 }}</td>
            <td>
              <a href="{{ route('master.alokasi.show', $lokasi) }}" class="btn btn-sm">
                {{ auth()->user()->role->bisaKelolaMasterData() ? 'Atur' : 'Lihat' }}
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada data lokasi.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $lokasis->links() }}
  </div>
</div>
@endsection
