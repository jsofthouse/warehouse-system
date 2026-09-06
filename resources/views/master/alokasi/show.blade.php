@extends('layouts.app')

@section('title', 'Alokasi — '.$lokasi->nama_kodim)
@section('pretitle', 'Master Data · Alokasi')

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      {{ $lokasi->nama_kodim }}
      <span class="text-secondary fw-normal">— {{ $lokasi->provinsi }}@if($lokasi->kabupaten), {{ $lokasi->kabupaten }}@endif</span>
    </h3>
  </div>

  <form method="POST" action="{{ route('master.alokasi.update', $lokasi) }}">
    @csrf
    @method('PUT')

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Item</th>
            <th>Satuan</th>
            <th class="text-end" style="max-width: 160px">Jumlah Kebutuhan</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $item)
            <tr>
              <td><span class="badge bg-blue-lt">{{ $item->kode }}</span></td>
              <td>{{ $item->nama }}</td>
              <td>{{ $item->satuan }}</td>
              <td class="text-end">
                @if (auth()->user()->role->bisaKelolaMasterData())
                  <input type="number" min="0" max="100000" step="1"
                         name="jumlah[{{ $item->id }}]"
                         value="{{ old('jumlah.'.$item->id, $alokasi[$item->id] ?? 0) }}"
                         class="form-control form-control-sm text-end d-inline-block" style="width: 100px">
                @else
                  {{ $alokasi[$item->id] ?? 0 }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="text-end">Total</th>
            <th class="text-end">{{ $alokasi->sum() }} unit</th>
          </tr>
        </tfoot>
      </table>
    </div>

    @if (auth()->user()->role->bisaKelolaMasterData())
      <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('master.alokasi.index') }}" class="btn btn-link">Kembali</a>
        <button type="submit" class="btn btn-primary">Simpan Alokasi</button>
      </div>
    @endif
  </form>
</div>
@endsection
