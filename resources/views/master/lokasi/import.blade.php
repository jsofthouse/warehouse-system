@extends('layouts.app')

@section('title', 'Import Lokasi')
@section('pretitle', 'Master Data')

@section('content')
<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Import dari CSV</h3>
      </div>
      <div class="card-body">
        <p class="text-secondary">
          Kolom wajib: <code>kode</code>, <code>nama_kodim</code>, <code>provinsi</code>.
          Kolom opsional: <code>kabupaten</code>, <code>kecamatan</code>, <code>desa</code>,
          <code>alamat</code>, <code>is_active</code> (1/0, kosongkan berarti aktif).
          Lokasi dengan <code>kode</code> yang sudah ada akan <strong>diperbarui</strong>,
          bukan diduplikasi.
        </p>

        <form method="POST" action="{{ route('master.lokasi.import') }}" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label class="form-label required" for="file">Berkas CSV</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv"
                   class="form-control @error('file') is-invalid @enderror" required>
            @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="form-hint">Maks 2 MB. Format .csv (bukan .xlsx) — lihat template di samping.</small>
          </div>

          <div class="form-footer">
            <a href="{{ route('master.lokasi.index') }}" class="btn btn-link">Batal</a>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-upload me-1"></i> Import
            </button>
          </div>
        </form>
      </div>
    </div>

    @if (session('import_errors') && count(session('import_errors')) > 0)
      <div class="card mt-3">
        <div class="card-header">
          <h3 class="card-title text-danger">Baris yang dilewati ({{ count(session('import_errors')) }})</h3>
        </div>
        <div class="card-body">
          <ul class="mb-0">
            @foreach (session('import_errors') as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Template</h3>
      </div>
      <div class="card-body">
        <p class="text-secondary">
          Unduh template kosong biar format kolomnya pasti cocok, lalu isi dan unggah lagi di sini.
        </p>
        <a href="{{ route('master.lokasi.import.template') }}" class="btn btn-outline-secondary w-100">
          <i class="ti ti-download me-1"></i> Unduh template CSV
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
