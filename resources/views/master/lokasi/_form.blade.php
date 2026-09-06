<div class="row">
  <div class="col-md-3 mb-3">
    <label class="form-label required" for="kode">Kode</label>
    <input id="kode" name="kode" type="text" maxlength="30"
           value="{{ old('kode', $lokasi->kode) }}"
           class="form-control @error('kode') is-invalid @enderror"
           placeholder="cth. LOK-33" required>
    @error('kode') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="col-md-9 mb-3">
    <label class="form-label required" for="nama_kodim">Nama Kodim</label>
    <input id="nama_kodim" name="nama_kodim" type="text" maxlength="255"
           value="{{ old('nama_kodim', $lokasi->nama_kodim) }}"
           class="form-control @error('nama_kodim') is-invalid @enderror" required>
    @error('nama_kodim') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label required" for="provinsi">Provinsi</label>
    <input id="provinsi" name="provinsi" type="text" maxlength="255"
           value="{{ old('provinsi', $lokasi->provinsi) }}"
           class="form-control @error('provinsi') is-invalid @enderror" required>
    @error('provinsi') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="kabupaten">Kabupaten</label>
    <input id="kabupaten" name="kabupaten" type="text" maxlength="255"
           value="{{ old('kabupaten', $lokasi->kabupaten) }}"
           class="form-control @error('kabupaten') is-invalid @enderror">
    @error('kabupaten') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="kecamatan">Kecamatan</label>
    <input id="kecamatan" name="kecamatan" type="text" maxlength="255"
           value="{{ old('kecamatan', $lokasi->kecamatan) }}"
           class="form-control @error('kecamatan') is-invalid @enderror">
    @error('kecamatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="mb-3">
  <label class="form-label" for="desa">Desa</label>
  <input id="desa" name="desa" type="text" maxlength="255"
         value="{{ old('desa', $lokasi->desa) }}"
         class="form-control @error('desa') is-invalid @enderror">
  @error('desa') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-label" for="alamat">Alamat Lengkap</label>
  <textarea id="alamat" name="alamat" rows="2"
            class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $lokasi->alamat) }}</textarea>
  @error('alamat') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
  <label class="form-check form-switch">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1"
           {{ old('is_active', $lokasi->is_active ?? true) ? 'checked' : '' }}>
    <span class="form-check-label">Aktif</span>
  </label>
</div>
