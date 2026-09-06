@extends('layouts.app')

@section('title', 'Ubah Draft Penerimaan')
@section('pretitle', 'Transaksi')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('penerimaan.update', $penerimaan) }}">
                @csrf
                @method('PUT')
                @include('penerimaan._form')

                <div class="form-footer">
                    <a href="{{ route('penerimaan.show', $penerimaan) }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
