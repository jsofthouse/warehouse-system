@extends('layouts.app')

@section('title', 'Ubah Lokasi')
@section('pretitle', 'Master Data')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('master.lokasi.update', $lokasi) }}">
      @csrf
      @method('PUT')
      @include('master.lokasi._form')

      <div class="form-footer">
        <a href="{{ route('master.lokasi.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection
