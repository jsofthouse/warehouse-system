@extends('layouts.app')

@section('title', 'Ubah User')
@section('pretitle', 'Sistem')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('users.update', $user) }}">
      @csrf
      @method('PUT')
      @include('users._form')

      <div class="form-footer">
        <a href="{{ route('users.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection
