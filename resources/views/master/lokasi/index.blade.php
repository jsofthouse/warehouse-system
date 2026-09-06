@extends('layouts.app')

@section('title', 'Lokasi Kodim')
@section('pretitle', 'Master Data')

@section('page-actions')
  @if (auth()->user()->role->bisaKelolaMasterData())
    <div class="btn-list">
      <a href="{{ route('master.lokasi.import.form') }}" class="btn btn-outline-secondary">
        <i class="ti ti-file-import me-1"></i> Import
      </a>
      <a href="{{ route('master.lokasi.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Lokasi
      </a>
    </div>
  @endif
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <form method="GET" class="row g-2 w-100">
      <div class="col">
        <input type="text" name="q" value="{{ $q }}" class="form-control"
               placeholder="Cari kode, kodim, kabupaten, kecamatan, atau desa…">
      </div>
      <div class="col-auto">
        <select name="provinsi" class="form-select" onchange="this.form.submit()">
          <option value="">Semua provinsi</option>
          @foreach ($daftarProvinsi as $p)
            <option value="{{ $p }}" {{ $provinsi === $p ? 'selected' : '' }}>{{ $p }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Kodim</th>
          <th>Provinsi</th>
          <th>Kabupaten</th>
          <th>Kecamatan</th>
          <th>Desa</th>
          <th>Status</th>
          @if (auth()->user()->role->bisaKelolaMasterData())
            <th class="w-1"></th>
          @endif
        </tr>
      </thead>
      <tbody>
        @forelse ($daftarLokasi as $lokasi)
          <tr>
            <td><span class="badge bg-blue-lt">{{ $lokasi->kode }}</span></td>
            <td>{{ $lokasi->nama_kodim }}</td>
            <td>{{ $lokasi->provinsi }}</td>
            <td>{{ $lokasi->kabupaten ?? '—' }}</td>
            <td>{{ $lokasi->kecamatan ?? '—' }}</td>
            <td>{{ $lokasi->desa ?? '—' }}</td>
            <td>
              @if ($lokasi->is_active)
                <span class="badge bg-green-lt">Aktif</span>
              @else
                <span class="badge bg-secondary-lt">Nonaktif</span>
              @endif
            </td>
            @if (auth()->user()->role->bisaKelolaMasterData())
              <td>
                <div class="btn-list flex-nowrap">
                  <x-riwayat-link :subjek="$lokasi" />
                  <a href="{{ route('master.alokasi.show', $lokasi) }}" class="btn btn-icon btn-sm" title="Lihat alokasi">
                    <i class="ti ti-list-details"></i>
                  </a>
                  <a href="{{ route('master.lokasi.edit', $lokasi) }}" class="btn btn-icon btn-sm" title="Ubah">
                    <i class="ti ti-pencil"></i>
                  </a>
                  <form method="POST" action="{{ route('master.lokasi.destroy', $lokasi) }}"
                        onsubmit="return confirm('Hapus lokasi &quot;{{ $lokasi->nama_kodim }}&quot;? Alokasi kebutuhannya ikut terhapus dan aksi ini tidak bisa dibatalkan.')">
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
          <tr><td colspan="8" class="text-center text-secondary py-4">Belum ada data lokasi.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $daftarLokasi->links() }}
  </div>
</div>
@endsection
