@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="card card-md">
  <div class="card-body">
    <h2 class="h2 text-center mb-4">Masuk ke akun Anda</h2>

    @if ($errors->any())
      <div class="alert alert-danger" role="alert">
        <div class="d-flex">
          <div><i class="ti ti-alert-circle icon alert-icon"></i></div>
          <div>
            @foreach ($errors->all() as $error)
              <div>{{ $error }}</div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    @if (session('status'))
      <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" autocomplete="off" novalidate>
      @csrf

      <div class="mb-3">
        <label class="form-label" for="email">Alamat email</label>
        <input
          id="email"
          type="email"
          name="email"
          value="{{ old('email') }}"
          class="form-control @error('email') is-invalid @enderror"
          placeholder="nama@gudang-alkap.local"
          autocomplete="username"
          autofocus
          required
        >
      </div>

      <div class="mb-2">
        <label class="form-label" for="password">
          Password
        </label>
        <div class="input-group input-group-flat">
          <input
            id="password"
            type="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            placeholder="Password"
            autocomplete="current-password"
            required
          >
        </div>
      </div>

      <div class="mb-3">
        <label class="form-check">
          <input type="checkbox" class="form-check-input" name="remember">
          <span class="form-check-label">Ingat saya di perangkat ini</span>
        </label>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn btn-primary w-100">Masuk</button>
      </div>
    </form>
  </div>
</div>
@endsection
