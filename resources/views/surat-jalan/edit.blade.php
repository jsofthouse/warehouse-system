@extends('layouts.app')

@section('title', 'Ubah Draft Surat Jalan')
@section('pretitle', 'Transaksi')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('surat-jalan.update', $suratJalan) }}">
                @csrf
                @method('PUT')
                @include('surat-jalan._form')

                <div class="form-footer">
                    <a href="{{ route('surat-jalan.show', $suratJalan) }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
