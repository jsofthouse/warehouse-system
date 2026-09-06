@extends('layouts.app')

@section('title', $title ?? 'Modul')
@section('pretitle', $pretitle ?? '')

@section('content')
<div class="card">
  <div class="card-body text-center py-6">
    <i class="ti ti-tool text-secondary" style="font-size: 3rem"></i>
    <h3 class="mt-3">{{ $title ?? 'Modul' }}</h3>
    <p class="text-secondary">
      Modul ini masih placeholder. Bikin controller, migration, dan view-nya sesuai
      <code>docs/02-model-data.md</code> &amp; <code>docs/03-aturan-bisnis.md</code>.
    </p>
  </div>
</div>
@endsection
