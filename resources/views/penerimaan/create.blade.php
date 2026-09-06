@extends('layouts.app')

@section('title', 'Penerimaan Baru')
@section('pretitle', 'Transaksi')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('penerimaan.store') }}">
                @csrf
                @include('penerimaan._form')

                <div class="form-footer">
                    <a href="{{ route('penerimaan.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
@endsection
