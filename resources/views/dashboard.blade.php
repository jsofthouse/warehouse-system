@extends('layouts.app')

@section('title', 'Dashboard')
@section('pretitle', 'Overview')

@section('content')
<div class="row row-deck row-cards">

  <div class="col-sm-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-primary text-white avatar"><i class="ti ti-truck-loading"></i></span>
          </div>
          <div class="col">
            <div class="font-weight-medium">— kg</div>
            <div class="text-secondary">Stok tersimpan hari ini</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-green text-white avatar"><i class="ti ti-file-text"></i></span>
          </div>
          <div class="col">
            <div class="font-weight-medium">0</div>
            <div class="text-secondary">Surat jalan bulan ini</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-yellow text-white avatar"><i class="ti ti-receipt"></i></span>
          </div>
          <div class="col">
            <div class="font-weight-medium">Rp 0</div>
            <div class="text-secondary">Invoice belum lunas</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-red text-white avatar"><i class="ti ti-map-pin"></i></span>
          </div>
          <div class="col">
            <div class="font-weight-medium">32</div>
            <div class="text-secondary">Lokasi Kodim terdaftar</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Selamat datang</h3>
      </div>
      <div class="card-body">
        <p class="text-secondary mb-0">
          Layout ini pakai template Tabler (vendored lokal di <code>public/vendor</code>, tanpa CDN) yang sudah
          dipasang ke Laravel. Menu di sidebar kiri masih halaman placeholder — tinggal dibikinin controller +
          view + tabel sesuai <code>docs/02-model-data.md</code> dan aturan bisnis di <code>docs/03-aturan-bisnis.md</code>.
        </p>
      </div>
    </div>
  </div>

</div>
@endsection
