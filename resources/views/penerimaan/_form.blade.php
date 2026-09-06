@php
    // Baris awal: hasil input yang gagal validasi kalau ada, kalau tidak ya isi
    // dokumen yang sedang diedit.
    $barisDokumen = $penerimaan->exists
        ? $penerimaan->details
            ->map(fn($d) => ['item_id' => $d->item_id, 'jumlah' => $d->jumlah, 'keterangan' => $d->keterangan])
            ->values()
            ->all()
        : [];

    $barisAwal = array_values(old('detail', $barisDokumen));

    $opsiItem = $items
        ->map(fn($i) => ['id' => $i->id, 'label' => $i->kode . ' — ' . $i->nama, 'satuan' => $i->satuan])
        ->values();
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

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label required" for="gudang_id">Gudang</label>
        @if ($kunciGudang)
            {{-- Terkunci di tampilan; nilainya tetap ditentukan server di
                 PenerimaanRequest, bukan dari field ini. --}}
            <input type="text" class="form-control" readonly
                value="{{ $penerimaan->gudang?->nama ?? $daftarGudang->first()?->nama ?? '—' }}">
        @else
            <select id="gudang_id" name="gudang_id" class="form-select @error('gudang_id') is-invalid @enderror">
                <option value="">Pilih gudang…</option>
                @foreach ($daftarGudang as $gudang)
                    <option value="{{ $gudang->id }}"
                        {{ (string) old('gudang_id', $penerimaan->gudang_id) === (string) $gudang->id ? 'selected' : '' }}>
                        {{ $gudang->nama }}
                    </option>
                @endforeach
            </select>
            @error('gudang_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label required" for="tanggal">Tanggal Penerimaan</label>
        <input id="tanggal" name="tanggal" type="date" max="{{ now()->toDateString() }}"
            value="{{ old('tanggal', $penerimaan->tanggal?->toDateString()) }}"
            class="form-control @error('tanggal') is-invalid @enderror" required>
        @error('tanggal')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <p class="form-hint">Boleh mundur kalau dokumennya telat diinput, tapi tidak boleh melewati hari ini.</p>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label required" for="vendor_nama">Vendor</label>
        <input id="vendor_nama" name="vendor_nama" type="text" maxlength="255"
            value="{{ old('vendor_nama', $penerimaan->vendor_nama) }}"
            class="form-control @error('vendor_nama') is-invalid @enderror" required>
        @error('vendor_nama')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label" for="nomor_dokumen_vendor">Nomor Surat Jalan Vendor</label>
        <input id="nomor_dokumen_vendor" name="nomor_dokumen_vendor" type="text" maxlength="255"
            value="{{ old('nomor_dokumen_vendor', $penerimaan->nomor_dokumen_vendor) }}"
            class="form-control @error('nomor_dokumen_vendor') is-invalid @enderror"
            placeholder="Opsional">
        @error('nomor_dokumen_vendor')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label" for="no_kontrak_referensi">Nomor Kontrak / Referensi</label>
        <input id="no_kontrak_referensi" name="no_kontrak_referensi" type="text" maxlength="255"
            value="{{ old('no_kontrak_referensi', $penerimaan->no_kontrak_referensi) }}"
            class="form-control @error('no_kontrak_referensi') is-invalid @enderror"
            placeholder="Opsional">
        @error('no_kontrak_referensi')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label" for="keterangan">Keterangan</label>
        <input id="keterangan" name="keterangan" type="text" maxlength="2000"
            value="{{ old('keterangan', $penerimaan->keterangan) }}"
            class="form-control @error('keterangan') is-invalid @enderror">
        @error('keterangan')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<hr class="my-4">

<div x-data="formPenerimaan(@js($opsiItem), @js($barisAwal))" x-cloak>
    <div class="d-flex align-items-center mb-2">
        <h3 class="card-title mb-0">Baris Barang</h3>
        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" @click="tambah()">
            <i class="ti ti-plus me-1"></i> Tambah baris
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter">
            <thead>
                <tr>
                    <th style="width: 45%">Item</th>
                    <th class="text-end" style="width: 15%">Jumlah</th>
                    <th style="width: 10%">Satuan</th>
                    <th style="width: 25%">Keterangan</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, i) in rows" :key="row.uid">
                    <tr>
                        <td>
                            {{-- Opsi diisi Tom Select dari JS, bukan dari markup:
                                 kalau options ditulis di HTML, Tom Select keburu
                                 jalan sebelum x-for sempat merendernya. --}}
                            <select class="form-select" :name="`detail[${i}][item_id]`"
                                x-init="pasangSelect($el, row)"></select>
                        </td>
                        <td>
                            <input type="number" min="1" step="1" class="form-control text-end"
                                :name="`detail[${i}][jumlah]`" x-model.number="row.jumlah">
                        </td>
                        <td class="text-secondary" x-text="satuan(row)"></td>
                        <td>
                            <input type="text" maxlength="255" class="form-control"
                                :name="`detail[${i}][keterangan]`" x-model="row.keterangan"
                                placeholder="Opsional">
                        </td>
                        <td>
                            <button type="button" class="btn btn-icon btn-sm text-danger" title="Hapus baris"
                                @click="hapus(i)">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-end">Total</th>
                    <th class="text-end" x-text="totalUnit"></th>
                    <th colspan="3" class="text-secondary fw-normal">unit</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="form-hint">
        Baris boleh dikosongkan dulu — draft tetap bisa disimpan. Stok baru berubah saat dokumen diposting.
    </p>
</div>

@push('scripts')
    <script>
        function formPenerimaan(items, awal) {
            let uid = 0;
            const buatBaris = (b = {}) => ({
                uid: ++uid,
                item_id: b.item_id ? String(b.item_id) : '',
                jumlah: b.jumlah ?? 1,
                keterangan: b.keterangan ?? '',
            });

            return {
                items: items,
                rows: (awal.length ? awal : [{}]).map(buatBaris),
                ts: {},

                tambah() {
                    this.rows.push(buatBaris());
                },

                hapus(index) {
                    this.lepasSelect(this.rows[index]);
                    this.rows.splice(index, 1);
                    if (!this.rows.length) this.tambah();
                    this.$nextTick(() => this.sinkron());
                },

                get totalUnit() {
                    return this.rows.reduce((total, row) => total + (parseInt(row.jumlah) || 0), 0);
                },

                satuan(row) {
                    const item = this.items.find(i => String(i.id) === String(row.item_id));
                    return item ? item.satuan : '—';
                },

                pasangSelect(el, row) {
                    const instance = new TomSelect(el, {
                        placeholder: 'Pilih item…',
                        maxOptions: null,
                        options: this.items.map(i => ({ value: String(i.id), text: i.label })),
                        items: row.item_id ? [String(row.item_id)] : [],
                        onChange: (value) => {
                            row.item_id = value || '';
                            this.sinkron();
                        },
                    });

                    this.ts[row.uid] = instance;
                    this.$nextTick(() => this.sinkron());
                },

                lepasSelect(row) {
                    if (row && this.ts[row.uid]) {
                        this.ts[row.uid].destroy();
                        delete this.ts[row.uid];
                    }
                },

                /**
                 * Item yang sudah dipakai di baris lain dibuang dari dropdown baris
                 * ini, jadi baris kembar tidak mungkin terbentuk dari layar. Server
                 * tetap mengecek ulang (rule `distinct`) — ini cuma kenyamanan.
                 */
                sinkron() {
                    Object.keys(this.ts).forEach((uid) => {
                        const instance = this.ts[uid];
                        const row = this.rows.find(r => String(r.uid) === String(uid));
                        if (!row) return;

                        const dipakaiLain = this.rows
                            .filter(r => r.uid !== row.uid)
                            .map(r => String(r.item_id))
                            .filter(Boolean);

                        this.items.forEach((item) => {
                            const nilai = String(item.id);
                            const terpasang = Boolean(instance.options[nilai]);

                            if (dipakaiLain.includes(nilai)) {
                                if (terpasang && instance.getValue() !== nilai) {
                                    instance.removeOption(nilai);
                                }
                            } else if (!terpasang) {
                                instance.addOption({ value: nilai, text: item.label });
                            }
                        });

                        instance.refreshOptions(false);
                    });
                },
            };
        }
    </script>
@endpush
