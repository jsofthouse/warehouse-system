@php
    // Isi kolom Kirim saat layar dibuka: hasil input yang gagal validasi kalau
    // ada, kalau tidak ya isi dokumen yang sedang diedit (kosong untuk dokumen
    // baru). Bentuknya map item_id => jumlah supaya gampang dipasangkan ke
    // baris yang datang dari endpoint pembanding.
    $prefill = collect(old('detail', []))
        ->filter(fn($b) => is_array($b) && ($b['item_id'] ?? '') !== '')
        ->mapWithKeys(fn($b) => [(int) $b['item_id'] => (int) ($b['jumlah_kirim'] ?? 0)])
        ->all();

    if ($prefill === []) {
        $prefill = $barisAwal;
    }

    $gudangTerpilih = old('gudang_id', $suratJalan->gudang_id ?? $daftarGudang->first()?->id);
    $lokasiTerpilih = old('lokasi_id', $suratJalan->lokasi_id);
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <h4 class="alert-title">Perbaiki dulu isian berikut</h4>
        <ul class="mb-0">
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div x-data="formSuratJalan({
        urlPembanding: @js(route('surat-jalan.pembanding')),
        gudangId: @js((string) ($gudangTerpilih ?? '')),
        lokasiId: @js((string) ($lokasiTerpilih ?? '')),
        prefill: @js((object) $prefill),
        adaDokumen: @js($suratJalan->exists),
    })" x-cloak>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label required" for="gudang_id">Gudang Asal</label>
            @if ($kunciGudang)
                {{-- Terkunci di tampilan; nilainya tetap ditentukan server di
                     SuratJalanRequest, bukan dari field ini. --}}
                <input type="text" class="form-control" readonly
                    value="{{ $suratJalan->gudang?->nama ?? $daftarGudang->first()?->nama ?? '—' }}">
            @else
                <select id="gudang_id" name="gudang_id" x-model="gudangId"
                    class="form-select @error('gudang_id') is-invalid @enderror">
                    <option value="">Pilih gudang…</option>
                    @foreach ($daftarGudang as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->nama }}</option>
                    @endforeach
                </select>
                @error('gudang_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            @endif
        </div>

        <div class="col-md-5 mb-3">
            <label class="form-label required" for="lokasi_id">Lokasi Tujuan</label>
            <select id="lokasi_id" name="lokasi_id" x-model="lokasiId"
                class="form-select @error('lokasi_id') is-invalid @enderror" required>
                <option value="">Pilih lokasi tujuan…</option>
                @foreach ($daftarLokasi as $lokasi)
                    <option value="{{ $lokasi->id }}">
                        {{ $lokasi->kode }} — {{ $lokasi->nama_kodim }}
                        ({{ $lokasi->desa ? $lokasi->desa . ', ' : '' }}{{ $lokasi->kabupaten }})
                    </option>
                @endforeach
            </select>
            @error('lokasi_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <p class="form-hint">Satu surat jalan = satu lokasi tujuan. Truk yang mampir ke beberapa desa tetap
                dibuatkan dokumen terpisah per lokasi.</p>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label required" for="tanggal">Tanggal Pengiriman</label>
            <input id="tanggal" name="tanggal" type="date" max="{{ now()->toDateString() }}"
                value="{{ old('tanggal', $suratJalan->tanggal?->toDateString()) }}"
                class="form-control @error('tanggal') is-invalid @enderror" required>
            @error('tanggal')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label" for="ekspedisi">Ekspedisi</label>
            <input id="ekspedisi" name="ekspedisi" type="text" maxlength="150"
                value="{{ old('ekspedisi', $suratJalan->ekspedisi) }}"
                class="form-control @error('ekspedisi') is-invalid @enderror" placeholder="Nama PT / perorangan">
            @error('ekspedisi')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4 mb-3">
            <label class="form-label" for="nomor_polisi">Nomor Polisi</label>
            <input id="nomor_polisi" name="nomor_polisi" type="text" maxlength="20"
                value="{{ old('nomor_polisi', $suratJalan->nomor_polisi) }}"
                class="form-control @error('nomor_polisi') is-invalid @enderror" placeholder="B 1234 XYZ">
            @error('nomor_polisi')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4 mb-3">
            <label class="form-label" for="nama_sopir">Nama Sopir</label>
            <input id="nama_sopir" name="nama_sopir" type="text" maxlength="100"
                value="{{ old('nama_sopir', $suratJalan->nama_sopir) }}"
                class="form-control @error('nama_sopir') is-invalid @enderror">
            @error('nama_sopir')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex align-items-center mb-2">
        <h3 class="card-title mb-0">Barang yang Dikirim</h3>
        <div class="ms-auto text-secondary small" x-show="memuat">Menghitung ketersediaan…</div>
    </div>

    <template x-if="!lokasiId">
        <div class="alert alert-info mb-0" role="alert">
            Pilih lokasi tujuan dulu — tabel alokasi, sisa, dan stok gudang muncul setelah itu.
        </div>
    </template>

    <template x-if="galat">
        <div class="alert alert-danger" role="alert" x-text="galat"></div>
    </template>

    <template x-if="lokasiId && baris.length">
        <div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Alokasi</th>
                            <th class="text-end">Sudah Kirim</th>
                            <th class="text-end">Sisa</th>
                            <th class="text-end">Stok Gudang</th>
                            <th class="text-end" style="width: 130px">Kirim</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, i) in baris" :key="row.item_id">
                            <tr :class="row.sisa === 0 || row.stok_gudang === 0 ? 'text-secondary' : ''">
                                <td>
                                    <span class="badge bg-blue-lt" x-text="row.kode"></span>
                                    <span x-text="row.nama"></span>
                                </td>
                                <td class="text-end" x-text="row.alokasi"></td>
                                <td class="text-end" x-text="row.sudah_kirim"></td>
                                <td class="text-end fw-bold" x-text="row.sisa"></td>
                                <td class="text-end" x-text="row.stok_gudang"></td>
                                <td>
                                    {{-- Input ini sengaja TANPA name: yang benar-benar
                                         terkirim adalah hidden field di bawah, dan cuma
                                         untuk baris berjumlah > 0. --}}
                                    <input type="number" class="form-control text-end" min="0" step="1"
                                        :max="row.batas" :disabled="row.batas === 0"
                                        :value="row.kirim" @input="setKirim(row, $event.target.value)"
                                        @blur="$event.target.value = row.kirim"
                                        :aria-label="`Jumlah kirim ${row.nama}`">

                                    <template x-if="row.kirim > 0">
                                        <div>
                                            <input type="hidden" :name="`detail[${i}][item_id]`" :value="row.item_id">
                                            <input type="hidden" :name="`detail[${i}][jumlah_kirim]`" :value="row.kirim">
                                        </div>
                                    </template>
                                </td>
                                <td class="small">
                                    <span x-show="row.sisa === 0" class="badge bg-green-lt">sudah lengkap</span>
                                    <span x-show="row.sisa > 0 && row.stok_gudang === 0"
                                        class="badge bg-red-lt">stok kosong</span>
                                    <span x-show="row.sisa > row.stok_gudang && row.stok_gudang > 0"
                                        class="badge bg-orange-lt">kiriman parsial</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-end">Total dikirim</th>
                            <th class="text-end" x-text="totalUnit"></th>
                            <th class="fw-normal text-secondary">unit</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="form-hint">
                Kolom Kirim dibatasi ke <strong>angka terkecil antara sisa alokasi dan stok gudang</strong>.
                Kalau lapangan memang perlu kirim lebih dari alokasi, ubah dulu alokasinya di menu
                Alokasi Kebutuhan — bukan dilonggarkan di sini. Draft boleh disimpan kosong; stok baru
                berkurang saat dokumen diposting.
            </p>
        </div>
    </template>

    <template x-if="lokasiId && !baris.length && !memuat">
        <div class="alert alert-warning mb-0" role="alert">
            Tidak ada item aktif di master data. Isi dulu master Item sebelum membuat surat jalan.
        </div>
    </template>
</div>

@push('scripts')
    <script>
        function formSuratJalan(config) {
            return {
                gudangId: config.gudangId || '',
                lokasiId: config.lokasiId || '',
                prefill: config.prefill || {},
                adaDokumen: config.adaDokumen,
                baris: [],
                memuat: false,
                galat: '',
                permintaanKe: 0,

                init() {
                    this.$watch('lokasiId', () => this.muat());
                    this.$watch('gudangId', () => this.muat());

                    if (this.lokasiId) this.muat();
                },

                get totalUnit() {
                    return this.baris.reduce((total, row) => total + (parseInt(row.kirim) || 0), 0);
                },

                /**
                 * Empat angka pembanding selalu diambil ulang dari server. Yang
                 * dipegang browser cuma hasil hitungan itu — server menghitung
                 * ulang lagi saat draft disimpan dan sekali lagi saat posting.
                 */
                async muat() {
                    if (!this.lokasiId || !this.gudangId) {
                        this.baris = [];
                        return;
                    }

                    // Balasan yang datang telat dari pilihan lokasi sebelumnya
                    // tidak boleh menimpa hasil pilihan terbaru.
                    const permintaan = ++this.permintaanKe;

                    this.memuat = true;
                    this.galat = '';

                    try {
                        const url = new URL(config.urlPembanding, window.location.origin);
                        url.searchParams.set('lokasi_id', this.lokasiId);
                        url.searchParams.set('gudang_id', this.gudangId);

                        const respons = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (permintaan !== this.permintaanKe) return;

                        if (!respons.ok) {
                            this.baris = [];
                            this.galat = 'Gagal mengambil data ketersediaan. Muat ulang halaman lalu coba lagi.';
                            return;
                        }

                        const data = await respons.json();

                        if (permintaan !== this.permintaanKe) return;

                        this.baris = (data.baris || []).map((row) => ({
                            ...row,
                            kirim: this.kirimAwal(row),
                        }));
                    } catch (e) {
                        if (permintaan === this.permintaanKe) {
                            this.baris = [];
                            this.galat = 'Koneksi ke server terputus saat mengambil data ketersediaan.';
                        }
                    } finally {
                        if (permintaan === this.permintaanKe) this.memuat = false;
                    }
                },

                /**
                 * Dokumen yang sedang diedit memakai angka yang sudah tersimpan;
                 * dokumen baru diisi otomatis LEAST(sisa, stok) sesuai
                 * "10-modul-surat-jalan.md" §4.4.
                 */
                kirimAwal(row) {
                    const tersimpan = this.prefill[row.item_id];

                    if (tersimpan !== undefined) {
                        return Math.min(Math.max(parseInt(tersimpan) || 0, 0), row.batas);
                    }

                    return this.adaDokumen ? 0 : row.batas;
                },

                setKirim(row, nilai) {
                    const angka = parseInt(nilai);

                    row.kirim = Number.isNaN(angka) ? 0 : Math.min(Math.max(angka, 0), row.batas);
                },
            };
        }
    </script>
@endpush
