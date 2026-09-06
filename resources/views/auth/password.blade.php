@extends('layouts.app')

@section('title', 'Ubah Password')
@section('pretitle', 'Akun Saya')

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Ubah Password</h3>
      </div>
      <div class="card-body">

        @if (session('status'))
          <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <div>
              @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
              @endforeach
            </div>
          </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" autocomplete="off">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label class="form-label" for="current_password">Password saat ini</label>
            <input
              id="current_password"
              type="password"
              name="current_password"
              class="form-control @error('current_password') is-invalid @enderror"
              autocomplete="current-password"
              required
            >
          </div>

          <div class="mb-3">
            <label class="form-label" for="password">Password baru</label>
            <input
              id="password"
              type="password"
              name="password"
              class="form-control @error('password') is-invalid @enderror"
              autocomplete="new-password"
              required
            >
            <small class="form-hint">Minimal 10 karakter, kombinasi huruf besar/kecil dan angka.</small>
          </div>

          <div class="mb-3">
            <label class="form-label" for="password_confirmation">Konfirmasi password baru</label>
            <input
              id="password_confirmation"
              type="password"
              name="password_confirmation"
              class="form-control"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="form-footer">
            <button type="submit" class="btn btn-primary">Simpan password baru</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
@endsection
