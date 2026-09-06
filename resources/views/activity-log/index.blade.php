@extends('layouts.app')

@section('title', 'Activity Log')
@section('pretitle', 'Sistem')

@section('content')
<div class="card" x-data="{ detailAktif: null }">
    <div class="card-header">
        <form method="GET" class="row g-2 w-100">
            <div class="col-md-2">
                <label class="form-label small mb-1">Dari tanggal</label>
                <input type="date" name="dari" value="{{ $filter['dari'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Sampai tanggal</label>
                <input type="date" name="sampai" value="{{ $filter['sampai'] }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" class="form-select">
                    <option value="">Semua user</option>
                    @foreach ($daftarUser as $u)
                        <option value="{{ $u->id }}" {{ (string) $filter['user_id'] === (string) $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Jenis dokumen</label>
                <select name="subjek_type" class="form-select">
                    <option value="">Semua jenis dokumen</option>
                    <option value="_tanpa_dokumen" {{ $filter['subjek_type'] === '_tanpa_dokumen' ? 'selected' : '' }}>
                        Tanpa dokumen tertentu
                    </option>
                    @foreach ($labelDokumen as $alias => $label)
                        <option value="{{ $alias }}" {{ $filter['subjek_type'] === $alias ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">IP address</label>
                <input type="text" name="ip" value="{{ $filter['ip'] }}" class="form-control" placeholder="mis. 10.0.0.">
            </div>

            <div class="col-12">
                <label class="form-label small mb-1">Jenis aksi</label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($labelAksi as $nilai => $label)
                        <label class="form-check">
                            <input type="checkbox" name="aksi[]" value="{{ $nilai }}" class="form-check-input"
                                {{ in_array($nilai, $filter['aksi'], true) ? 'checked' : '' }}>
                            <span class="form-check-label">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($filter['subjek_id'])
                <input type="hidden" name="subjek_id" value="{{ $filter['subjek_id'] }}">
            @endif

            <div class="col-12">
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="ti ti-filter me-1"></i> Terapkan Filter
                </button>
                <a href="{{ route('activity-log.index') }}" class="btn btn-link">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Aksi</th>
                    <th>Dokumen Terkait</th>
                    <th>Deskripsi</th>
                    <th>IP</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarActivityLog as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>
                            @if ($log->user)
                                {{ $log->user->name }}
                            @elseif ($log->aksi === 'login_gagal')
                                <span class="text-secondary">{{ $log->data_baru['email_dicoba'] ?? '—' }} (dicoba)</span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $badgeAksi[$log->aksi] ?? 'bg-secondary-lt' }}">
                                {{ $labelAksi[$log->aksi] ?? $log->aksi }}
                            </span>
                        </td>
                        <td>
                            @php
                                $labelJenis = $labelDokumen[$log->subjek_type] ?? null;
                            @endphp
                            @if ($log->subjek_type === null)
                                <span class="text-secondary">Tanpa dokumen tertentu</span>
                            @elseif ($log->subjek === null)
                                <span class="text-secondary">{{ $labelJenis ?? $log->subjek_type }} (sudah dihapus)</span>
                            @elseif ($log->subjek_type === 'penerimaan')
                                <a href="{{ route('penerimaan.show', $log->subjek) }}">
                                    {{ $labelJenis }}: {{ $log->subjek->nomor_penerimaan ?? 'Draft #'.$log->subjek->id }}
                                </a>
                            @elseif ($log->subjek_type === 'surat_jalan')
                                <a href="{{ route('surat-jalan.show', $log->subjek) }}">
                                    {{ $labelJenis }}: {{ $log->subjek->nomor_surat_jalan ?? 'Draft #'.$log->subjek->id }}
                                </a>
                            @elseif ($log->subjek_type === 'lokasi')
                                {{ $labelJenis }}: {{ $log->subjek->nama_kodim }}
                            @elseif ($log->subjek_type === 'gudang')
                                {{ $labelJenis }}: {{ $log->subjek->nama }}
                            @elseif ($log->subjek_type === 'item')
                                {{ $labelJenis }}: {{ $log->subjek->nama }}
                            @elseif ($log->subjek_type === 'tarif_sewa')
                                {{ $labelJenis }}: #{{ $log->subjek->id }}
                            @elseif ($log->subjek_type === 'user')
                                {{ $labelJenis }}: {{ $log->subjek->name }}
                            @else
                                <span class="text-secondary">{{ $labelJenis ?? $log->subjek_type }}</span>
                            @endif
                        </td>
                        <td>{{ $log->deskripsi ?? '—' }}</td>
                        <td class="text-secondary">{{ $log->ip_address ?? '—' }}</td>
                        <td>
                            @if ($log->data_lama || $log->data_baru)
                                <button type="button" class="btn btn-icon btn-sm" title="Detail perubahan"
                                    @click="detailAktif = {
                                        waktu: @js($log->created_at->format('d/m/Y H:i:s')),
                                        aksi: @js($labelAksi[$log->aksi] ?? $log->aksi),
                                        dataLama: @js($log->data_lama),
                                        dataBaru: @js($log->data_baru),
                                    }">
                                    <i class="ti ti-list-details"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            Tidak ada aktivitas yang cocok dengan filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer d-flex align-items-center">
        {{ $daftarActivityLog->links() }}
    </div>

    {{-- Detail data_lama/data_baru, JSON pretty-print, di-escape otomatis lewat
         x-text (bukan x-html) — docs/12-modul-activity-log.md §5.3.
         Wrapper x-show sengaja BUKAN class "modal" bawaan Bootstrap — CSS-nya
         mendeklarasikan display:none langsung di stylesheet (bukan cuma
         default browser), jadi begitu Alpine melepas override inline-nya
         balik ke "shown", stylesheet itu menang lagi dan modalnya tidak
         pernah kelihatan. Class modal-dialog/modal-content dst di dalam
         wrapper ini aman dipakai (tidak ada default display:none). --}}
    <div x-show="detailAktif" x-cloak
        class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3"
        style="z-index: 1055; background: rgba(0, 0, 0, .35)"
        @keydown.escape.window="detailAktif = null" @click.self="detailAktif = null">
        <div class="modal-dialog modal-lg m-0" style="max-height: 90vh" @click.stop>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="detailAktif ? detailAktif.aksi + ' — ' + detailAktif.waktu : ''"></h5>
                    <button type="button" class="btn-close" @click="detailAktif = null"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto">
                    <div class="mb-3">
                        <div class="form-label">Data Lama</div>
                        <pre class="bg-body-secondary p-2 rounded" x-text="detailAktif ? JSON.stringify(detailAktif.dataLama, null, 2) : ''"></pre>
                    </div>
                    <div>
                        <div class="form-label">Data Baru</div>
                        <pre class="bg-body-secondary p-2 rounded" x-text="detailAktif ? JSON.stringify(detailAktif.dataBaru, null, 2) : ''"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
