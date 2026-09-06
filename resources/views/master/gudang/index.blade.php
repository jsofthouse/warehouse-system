@extends('layouts.app')

@section('title', 'Gudang')
@section('pretitle', 'Master Data')

@section('page-actions')
  @if (auth()->user()->role->bisaKelolaMasterData())
    <a href="{{ route('master.gudang.create') }}" class="btn btn-primary">
      <i class="ti ti-plus me-1"></i> Gudang
    </a>
  @endif
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <form method="GET" class="d-flex gap-2 w-100">
      <input type="text" name="q" value="{{ $q }}" class="form-control"
             placeholder="Cari kode, nama, atau kota…">
      <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama</th>
          <th>Kota</th>
          <th>Status</th>
          @if (auth()->user()->role->bisaKelolaMasterData())
            <th class="w-1"></th>
          @endif
        </tr>
      </thead>
      <tbody>
        @forelse ($daftarGudang as $gudang)
          <tr>
            <td><span class="badge bg-blue-lt">{{ $gudang->kode }}</span></td>
            <td>{{ $gudang->nama }}</td>
            <td>{{ $gudang->kota ?? '—' }}</td>
            <td>
              @if ($gudang->is_active)
                <span class="badge bg-green-lt">Aktif</span>
              @else
                <span class="badge bg-secondary-lt">Nonaktif</span>
              @endif
            </td>
            @if (auth()->user()->role->bisaKelolaMasterData())
              <td>
                <div class="btn-list flex-nowrap">
                  <x-riwayat-link :subjek="$gudang" />
                  <a href="{{ route('master.gudang.edit', $gudang) }}" class="btn btn-icon btn-sm" title="Ubah">
                    <i class="ti ti-pencil"></i>
                  </a>
                  <form method="POST" action="{{ route('master.gudang.destroy', $gudang) }}"
                        onsubmit="return confirm('Hapus gudang &quot;{{ $gudang->nama }}&quot;? Aksi ini tidak bisa dibatalkan.')">
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
          <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada data gudang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $daftarGudang->links() }}
  </div>
</div>
@endsection
