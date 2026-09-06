@extends('layouts.app')

@section('title', 'Tandai Diterima ' . $suratJalan->nomor_surat_jalan)
@section('pretitle', 'Transaksi · Surat Jalan')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-status-top bg-success"></div>
                <div class="card-header">
                    <h3 class="card-title">Tandai Diterima — {{ $suratJalan->nomor_surat_jalan }}</h3>
                </div>

                <div class="card-body">
                    <p>
                        Tujuan: <strong>{{ $suratJalan->lokasi?->nama_kodim }}</strong>
                        ({{ collect([$suratJalan->lokasi?->desa, $suratJalan->lokasi?->kabupaten])->filter()->implode(', ') }}),
                        dikirim {{ $suratJalan->tanggal->format('d/m/Y') }} sebanyak
                        {{ number_format($suratJalan->totalUnit(), 0, ',', '.') }} unit.
                    </p>

                    <div class="alert alert-info" role="alert">
                        Aksi ini <strong>tidak mengubah stok</strong> — stok sudah berkurang sejak dokumen diposting.
                        Yang berubah cuma statusnya. Setelah ditandai diterima, dokumen
                        <strong>tidak bisa dibatalkan lagi</strong>.
                    </div>

                    <form method="POST" action="{{ route('surat-jalan.diterima', $suratJalan) }}"
                        id="form-diterima">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label required" for="tanggal_terima">Tanggal terima</label>
                                <input id="tanggal_terima" name="tanggal_terima" type="date"
                                    min="{{ $suratJalan->tanggal->toDateString() }}" max="{{ now()->toDateString() }}"
                                    value="{{ old('tanggal_terima', now()->toDateString()) }}"
                                    class="form-control @error('tanggal_terima') is-invalid @enderror" required>
                                @error('tanggal_terima')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="form-label required" for="nama_penerima">Nama penerima</label>
                                <input id="nama_penerima" name="nama_penerima" type="text" maxlength="100"
                                    value="{{ old('nama_penerima') }}"
                                    class="form-control @error('nama_penerima') is-invalid @enderror"
                                    placeholder="Nama orang yang menerima di lokasi" required>
                                @error('nama_penerima')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <p class="form-hint">Wajib — penerimaan barang ke instansi negara harus tercatat di
                                    sistem, bukan cuma di kertas yang dibawa sopir.</p>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="nomor_bast">Nomor BAST</label>
                                <input id="nomor_bast" name="nomor_bast" type="text" maxlength="40"
                                    value="{{ old('nomor_bast') }}"
                                    class="form-control @error('nomor_bast') is-invalid @enderror"
                                    placeholder="Opsional">
                                @error('nomor_bast')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-footer">
                            <a href="{{ route('surat-jalan.show', $suratJalan) }}" class="btn btn-link">Kembali</a>
                            <button type="submit" class="btn btn-success">Tandai Diterima</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-diterima')?.addEventListener('submit', function(e) {
            if (this.dataset.dikonfirmasi === '1') return;

            e.preventDefault();

            Swal.fire({
                title: 'Tandai {{ $suratJalan->nomor_surat_jalan }} diterima?',
                html: 'Status berubah jadi <b>Diterima</b> dan tombol Batalkan hilang permanen untuk dokumen ini.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, tandai diterima',
                cancelButtonText: 'Batal',
            }).then((hasil) => {
                if (hasil.isConfirmed) {
                    this.dataset.dikonfirmasi = '1';
                    this.submit();
                }
            });
        });
    </script>
@endpush
