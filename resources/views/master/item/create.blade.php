@extends('layouts.app')

@section('title', 'Tambah Item')
@section('pretitle', 'Master Data')

@section('content')
<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('master.item.store') }}">
      @csrf
      @include('master.item._form')

      <div class="form-footer">
        <a href="{{ route('master.item.index') }}" class="btn btn-link">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
