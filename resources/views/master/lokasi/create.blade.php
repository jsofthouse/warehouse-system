@extends('layouts.app')

@section('title', 'Tambah Lokasi')
@section('pretitle', 'Master Data')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('master.lokasi.store') }}">
      @csrf
      @include('master.lokasi._form')

      <div class="form-footer">
        <a href="{{ route('master.lokasi.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
