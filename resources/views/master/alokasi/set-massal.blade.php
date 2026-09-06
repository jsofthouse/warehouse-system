@extends('layouts.app')

@section('title', 'Set Alokasi Massal Seragam')
@section('pretitle', 'Master Data · Alokasi')

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-set-massal');
    form?.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      Swal.fire({
        title: 'Timpa alokasi SEMUA lokasi?',
        html: 'Jumlah kebutuhan tiap item di bawah akan diterapkan sama rata ke ' +
              '<b>{{ $jumlahLokasi }} lokasi</b> yang sudah terdaftar, menimpa alokasi lama.<br>' +
              'Aksi ini tidak bisa dibatalkan otomatis — harus diubah manual lagi kalau salah.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, terapkan ke semua',
        cancelButtonText: 'Batal'
      }).then(r => {
        if (r.isConfirmed) {
          form.dataset.confirmed = '1';
          document.getElementById('konfirmasi').checked = true;
          form.submit();
        }
      });
    });
  });
</script>
@endpush

@section('content')
<div class="alert alert-warning">
  <i class="ti ti-alert-triangle me-1"></i>
  Aksi ini menimpa alokasi kebutuhan di <strong>semua {{ $jumlahLokasi }} lokasi</strong> yang sudah
  terdaftar dengan angka yang sama. Cocok untuk setup awal; kalau kebutuhan tiap lokasi ternyata
  tidak seragam, atur ulang per lokasi lewat menu Alokasi.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Jumlah kebutuhan seragam per item</h3>
  </div>

  <form method="POST" action="{{ route('master.alokasi.set-massal') }}" id="form-set-massal">
    @csrf

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Item</th>
            <th>Satuan</th>
            <th class="text-end">Jumlah / Lokasi</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $item)
            <tr>
              <td><span class="badge bg-blue-lt">{{ $item->kode }}</span></td>
              <td>{{ $item->nama }}</td>
              <td>{{ $item->satuan }}</td>
              <td class="text-end">
                <input type="number" min="0" max="100000" step="1"
                       name="jumlah[{{ $item->id }}]"
                       value="{{ old('jumlah.'.$item->id, 0) }}"
                       class="form-control form-control-sm text-end d-inline-block" style="width: 100px">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      <label class="form-check mb-3">
        <input id="konfirmasi" class="form-check-input" type="checkbox" name="konfirmasi" value="1"
               {{ old('konfirmasi') ? 'checked' : '' }}>
        <span class="form-check-label">Saya paham ini menimpa alokasi semua lokasi yang sudah ada.</span>
      </label>
      @error('konfirmasi') <div class="text-danger mb-2">{{ $message }}</div> @enderror

      <div class="d-flex justify-content-between">
        <a href="{{ route('master.alokasi.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-danger">Terapkan ke Semua Lokasi</button>
      </div>
    </div>
  </form>
</div>
@endsection
