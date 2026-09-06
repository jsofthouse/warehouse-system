@extends('layouts.app')

@section('title', 'Tarif Sewa Gudang')
@section('pretitle', 'Master Data')

@section('page-actions')
  @if (auth()->user()->role->bisaKelolaMasterData())
    <a href="{{ route('master.tarif.create') }}" class="btn btn-primary">
      <i class="ti ti-plus me-1"></i> Tarif
    </a>
  @endif
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <form method="GET" class="d-flex gap-2 w-100">
      <select name="gudang_id" class="form-select" style="max-width: 260px" onchange="this.form.submit()">
        <option value="">Semua gudang</option>
        @foreach ($daftarGudang as $g)
          <option value="{{ $g->id }}" {{ (string) $gudangId === (string) $g->id ? 'selected' : '' }}>{{ $g->nama }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Gudang</th>
          <th>Basis</th>
          <th class="text-end">Tarif / hari</th>
          <th class="text-end">PPN</th>
          <th>Berlaku</th>
          <th class="w-1"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($tarifs as $tarif)
          <tr>
            <td>{{ $tarif->gudang->nama }}</td>
            <td>{{ $tarif->basis->label() }}</td>
            <td class="text-end">Rp {{ number_format($tarif->tarif_per_satuan_per_hari, 2, ',', '.') }}</td>
            <td class="text-end">{{ rtrim(rtrim($tarif->ppn_persen, '0'), '.') }}%</td>
            <td>
              {{ $tarif->berlaku_mulai->format('d/m/Y') }}
              &ndash;
              {{ $tarif->berlaku_sampai?->format('d/m/Y') ?? 'sekarang' }}
            </td>
            @if (auth()->user()->role->bisaKelolaMasterData())
              <td>
                <div class="btn-list flex-nowrap">
                  <a href="{{ route('master.tarif.edit', $tarif) }}" class="btn btn-icon btn-sm" title="Ubah">
                    <i class="ti ti-pencil"></i>
                  </a>
                  <form method="POST" action="{{ route('master.tarif.destroy', $tarif) }}"
                        onsubmit="return confirm('Hapus tarif ini? Aksi ini tidak bisa dibatalkan.')">
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
          <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada data tarif sewa.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $tarifs->links() }}
  </div>
</div>
@endsection
