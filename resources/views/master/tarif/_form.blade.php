<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label required" for="gudang_id">Gudang</label>
    <select id="gudang_id" name="gudang_id"
            class="form-select @error('gudang_id') is-invalid @enderror" required>
      <option value="">— pilih gudang —</option>
      @foreach ($daftarGudang as $g)
        <option value="{{ $g->id }}" {{ (int) old('gudang_id', $tarif->gudang_id) === $g->id ? 'selected' : '' }}>
          {{ $g->nama }}
        </option>
      @endforeach
    </select>
    @error('gudang_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6 mb-3">
    <label class="form-label required" for="basis">Basis Perhitungan</label>
    <select id="basis" name="basis" class="form-select @error('basis') is-invalid @enderror" required>
      <option value="">— pilih basis —</option>
      @foreach (\App\Enums\BasisTarif::cases() as $b)
        <option value="{{ $b->value }}" {{ old('basis', $tarif->basis?->value) === $b->value ? 'selected' : '' }}>
          {{ $b->label() }}
        </option>
      @endforeach
    </select>
    @error('basis') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

{{-- harga_beli TIDAK PERNAH tampil di invoice cetak — cuma cost internal buat
     laporan margin ("04-invoice-sewa-gudang.md" §0 keputusan #2). --}}
<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label required" for="harga_jual_per_satuan_per_hari">Harga Jual per Satuan per Hari (Rp)</label>
    <input id="harga_jual_per_satuan_per_hari" name="harga_jual_per_satuan_per_hari" type="number" step="0.01" min="0"
           value="{{ old('harga_jual_per_satuan_per_hari', $tarif->harga_jual_per_satuan_per_hari) }}"
           class="form-control @error('harga_jual_per_satuan_per_hari') is-invalid @enderror" required>
    <small class="form-hint">Ini yang tampil di invoice ke klien.</small>
    @error('harga_jual_per_satuan_per_hari') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label required" for="harga_beli_per_satuan_per_hari">Harga Beli per Satuan per Hari (Rp)</label>
    <input id="harga_beli_per_satuan_per_hari" name="harga_beli_per_satuan_per_hari" type="number" step="0.01" min="0"
           value="{{ old('harga_beli_per_satuan_per_hari', $tarif->harga_beli_per_satuan_per_hari) }}"
           class="form-control @error('harga_beli_per_satuan_per_hari') is-invalid @enderror" required>
    <small class="form-hint">Cost internal buat laporan margin — tidak tampil di invoice.</small>
    @error('harga_beli_per_satuan_per_hari') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="faktor_volumetrik_kg_per_m3">Faktor Volumetrik (kg/m³)</label>
    <input id="faktor_volumetrik_kg_per_m3" name="faktor_volumetrik_kg_per_m3" type="number" step="0.01" min="0.01"
           value="{{ old('faktor_volumetrik_kg_per_m3', $tarif->faktor_volumetrik_kg_per_m3 ?? 250) }}"
           class="form-control @error('faktor_volumetrik_kg_per_m3') is-invalid @enderror">
    <small class="form-hint">Cuma dipakai kalau basis KG_VOLUMETRIK / KG_TERBESAR.</small>
    @error('faktor_volumetrik_kg_per_m3') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label" for="ppn_persen">PPN (%)</label>
    <input id="ppn_persen" name="ppn_persen" type="number" step="0.01" min="0" max="100"
           value="{{ old('ppn_persen', $tarif->ppn_persen ?? 11) }}"
           class="form-control @error('ppn_persen') is-invalid @enderror">
    @error('ppn_persen') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="min_hari_simpan">Minimum Hari Simpan</label>
    <input id="min_hari_simpan" name="min_hari_simpan" type="number" step="1" min="0"
           value="{{ old('min_hari_simpan', $tarif->min_hari_simpan ?? 0) }}"
           class="form-control @error('min_hari_simpan') is-invalid @enderror">
    @error('min_hari_simpan') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label" for="pembulatan_rupiah">Pembulatan Subtotal (Rp)</label>
    <input id="pembulatan_rupiah" name="pembulatan_rupiah" type="number" step="1" min="1"
           value="{{ old('pembulatan_rupiah', $tarif->pembulatan_rupiah) }}"
           class="form-control @error('pembulatan_rupiah') is-invalid @enderror"
           placeholder="Kosongkan = tanpa pembulatan">
    @error('pembulatan_rupiah') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label required" for="berlaku_mulai">Berlaku Mulai</label>
    <input id="berlaku_mulai" name="berlaku_mulai" type="date"
           value="{{ old('berlaku_mulai', optional($tarif->berlaku_mulai)->format('Y-m-d')) }}"
           class="form-control @error('berlaku_mulai') is-invalid @enderror" required>
    @error('berlaku_mulai') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4 mb-3">
    <label class="form-label" for="berlaku_sampai">Berlaku Sampai</label>
    <input id="berlaku_sampai" name="berlaku_sampai" type="date"
           value="{{ old('berlaku_sampai', optional($tarif->berlaku_sampai)->format('Y-m-d')) }}"
           class="form-control @error('berlaku_sampai') is-invalid @enderror">
    <small class="form-hint">Kosongkan kalau masih berlaku sampai ada tarif baru.</small>
    @error('berlaku_sampai') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>
