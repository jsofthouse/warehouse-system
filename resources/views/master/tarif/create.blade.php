@extends('layouts.app')

@section('title', 'Tambah Tarif Sewa')
@section('pretitle', 'Master Data')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('master.tarif.store') }}">
      @csrf
      @include('master.tarif._form')

      <div class="form-footer">
        <a href="{{ route('master.tarif.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
