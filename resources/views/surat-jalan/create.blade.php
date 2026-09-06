@extends('layouts.app')

@section('title', 'Surat Jalan Baru')
@section('pretitle', 'Transaksi')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('surat-jalan.store') }}">
                @csrf
                @include('surat-jalan._form')

                <div class="form-footer">
                    <a href="{{ route('surat-jalan.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
@endsection
