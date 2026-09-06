@extends('layouts.app')

@section('title', 'Ubah Gudang')
@section('pretitle', 'Master Data')

@section('content')
<div class="card card-md">
  <div class="card-body">
    <form method="POST" action="{{ route('master.gudang.update', $gudang) }}">
      @csrf
      @method('PUT')
      @include('master.gudang._form')

      <div class="form-footer">
        <a href="{{ route('master.gudang.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection
