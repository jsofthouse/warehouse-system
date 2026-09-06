@extends('layouts.app')

@section('title', 'Batalkan ' . $penerimaan->nomor_penerimaan)
@section('pretitle', 'Transaksi · Penerimaan Barang')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-status-top bg-danger"></div>
                <div class="card-header">
                    <h3 class="card-title">Batalkan Penerimaan {{ $penerimaan->nomor_penerimaan }}</h3>
                </div>

                <div class="card-body">
                    <p>
                        Dokumen tidak dihapus. Sistem menulis <strong>mutasi balik (OUT)</strong> untuk tiap baris di
                        bawah, lalu menandai dokumen sebagai dibatalkan. Nomor dokumen tetap dan tidak dipakai ulang.
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Jumlah yang ditarik kembali</th>
                                    <th>Satuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($penerimaan->detail as $detail)
                                    <tr>
                                        <td>{{ $detail->item?->nama ?? "Item #{$detail->item_id}" }}</td>
                                        <td class="text-end">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                        <td>{{ $detail->item?->satuan ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning" role="alert">
                        Kalau sebagian barang sudah terlanjur keluar lewat surat jalan, pembatalan ini akan
                        <strong>ditolak</strong> karena stok jadi minus. Batalkan surat jalannya lebih dulu.
                    </div>

                    <form method="POST" action="{{ route('penerimaan.batalkan', $penerimaan) }}" id="form-batalkan">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label required" for="alasan_pembatalan">Alasan pembatalan</label>
                            <textarea id="alasan_pembatalan" name="alasan_pembatalan" rows="3" minlength="10" maxlength="1000"
                                class="form-control @error('alasan_pembatalan') is-invalid @enderror"
                                placeholder="Contoh: Barang dikembalikan ke vendor karena spesifikasi tidak sesuai kontrak." required>{{ old('alasan_pembatalan') }}</textarea>
                            @error('alasan_pembatalan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <p class="form-hint">Minimal 10 karakter. Alasan ini yang dibaca saat audit.</p>
                        </div>

                        <div class="form-footer">
                            <a href="{{ route('penerimaan.show', $penerimaan) }}" class="btn btn-link">Kembali</a>
                            <button type="submit" class="btn btn-danger">Batalkan Dokumen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-batalkan')?.addEventListener('submit', function(e) {
            if (this.dataset.dikonfirmasi === '1') return;

            e.preventDefault();

            Swal.fire({
                title: 'Batalkan {{ $penerimaan->nomor_penerimaan }}?',
                html: 'Sistem akan menulis <b>mutasi balik</b> dan stok gudang berkurang ' +
                    {{ (int) $penerimaan->totalUnit() }} + ' unit.<br>' +
                    'Aksi ini tidak bisa dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, batalkan dokumen',
                cancelButtonText: 'Jangan',
                confirmButtonColor: '#d63939',
            }).then((hasil) => {
                if (hasil.isConfirmed) {
                    this.dataset.dikonfirmasi = '1';
                    this.submit();
                }
            });
        });
    </script>
@endpush
