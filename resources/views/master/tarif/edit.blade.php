@extends('layouts.app')

@section('title', 'Ubah Tarif Sewa')
@section('pretitle', 'Master Data')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('master.tarif.update', $tarif) }}">
      @csrf
      @method('PUT')
      @include('master.tarif._form')

      <div class="form-footer">
        <a href="{{ route('master.tarif.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection
