<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label required" for="kode">Kode</label>
    <input id="kode" name="kode" type="text" maxlength="30"
           value="{{ old('kode', $item->kode) }}"
           class="form-control @error('kode') is-invalid @enderror"
           placeholder="cth. ALK-18" required>
    @error('kode') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-7 mb-3">
    <label class="form-label required" for="nama">Nama Item</label>
    <input id="nama" name="nama" type="text" maxlength="255"
           value="{{ old('nama', $item->nama) }}"
           class="form-control @error('nama') is-invalid @enderror" required>
    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-2 mb-3">
    <label class="form-label required" for="satuan">Satuan</label>
    <input id="satuan" name="satuan" type="text" maxlength="20"
           value="{{ old('satuan', $item->satuan ?? 'UNIT') }}"
           class="form-control @error('satuan') is-invalid @enderror" required>
    @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="mb-3">
  <label class="form-label" for="berat_kg">
    Berat (kg)
    <span class="form-help" title="Belum ada data dari klien untuk sebagian besar item — kosongkan kalau belum tahu, jangan diisi angka karangan.">?</span>
  </label>
  <input id="berat_kg" name="berat_kg" type="number" step="0.001" min="0"
         value="{{ old('berat_kg', $item->berat_kg) }}"
         class="form-control @error('berat_kg') is-invalid @enderror"
         placeholder="Kosongkan kalau belum ada datanya">
  @error('berat_kg') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="panjang_cm">Panjang (cm)</label>
    <input id="panjang_cm" name="panjang_cm" type="number" step="0.01" min="0"
           value="{{ old('panjang_cm', $item->panjang_cm) }}"
           class="form-control @error('panjang_cm') is-invalid @enderror">
    @error('panjang_cm') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="lebar_cm">Lebar (cm)</label>
    <input id="lebar_cm" name="lebar_cm" type="number" step="0.01" min="0"
           value="{{ old('lebar_cm', $item->lebar_cm) }}"
           class="form-control @error('lebar_cm') is-invalid @enderror">
    @error('lebar_cm') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="tinggi_cm">Tinggi (cm)</label>
    <input id="tinggi_cm" name="tinggi_cm" type="number" step="0.01" min="0"
           value="{{ old('tinggi_cm', $item->tinggi_cm) }}"
           class="form-control @error('tinggi_cm') is-invalid @enderror">
    @error('tinggi_cm') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>
<p class="form-hint">Volume (m³) dihitung otomatis dari Panjang × Lebar × Tinggi saat disimpan, tidak diinput manual.</p>

<div class="mb-3">
  <label class="form-check form-switch">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1"
           {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
    <span class="form-check-label">Aktif</span>
  </label>
</div>
