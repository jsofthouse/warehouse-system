<div class="mb-3">
  <label class="form-label required" for="kode">Kode Gudang</label>
  <input id="kode" name="kode" type="text" maxlength="20"
         value="{{ old('kode', $gudang->kode) }}"
         class="form-control @error('kode') is-invalid @enderror"
         placeholder="cth. GDG-SMG" required>
  @error('kode') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-label required" for="nama">Nama Gudang</label>
  <input id="nama" name="nama" type="text" maxlength="255"
         value="{{ old('nama', $gudang->nama) }}"
         class="form-control @error('nama') is-invalid @enderror" required>
  @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-label" for="kota">Kota</label>
  <input id="kota" name="kota" type="text" maxlength="255"
         value="{{ old('kota', $gudang->kota) }}"
         class="form-control @error('kota') is-invalid @enderror">
  @error('kota') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-label" for="alamat">Alamat</label>
  <textarea id="alamat" name="alamat" rows="3"
            class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $gudang->alamat) }}</textarea>
  @error('alamat') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-check form-switch">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1"
           {{ old('is_active', $gudang->is_active ?? true) ? 'checked' : '' }}>
    <span class="form-check-label">Aktif</span>
  </label>
</div>
