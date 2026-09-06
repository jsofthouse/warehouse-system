<div x-data="{ role: '{{ old('role', $user->role?->value ?? '') }}' }">

  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label required" for="name">Nama</label>
      <input id="name" name="name" type="text" maxlength="255"
             value="{{ old('name', $user->name) }}"
             class="form-control @error('name') is-invalid @enderror" required>
      @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label required" for="email">Email</label>
      <input id="email" name="email" type="email" maxlength="255"
             value="{{ old('email', $user->email) }}"
             class="form-control @error('email') is-invalid @enderror" required autocomplete="off">
      @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label {{ $user->exists ? '' : 'required' }}" for="password">
        Password {{ $user->exists ? '(kosongkan kalau tidak diubah)' : '' }}
      </label>
      <input id="password" name="password" type="password" autocomplete="new-password"
             class="form-control @error('password') is-invalid @enderror" {{ $user->exists ? '' : 'required' }}>
      @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label {{ $user->exists ? '' : 'required' }}" for="password_confirmation">Ulangi Password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
             class="form-control" {{ $user->exists ? '' : 'required' }}>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label required" for="role">Role</label>
      <select id="role" name="role" x-model="role"
              class="form-select @error('role') is-invalid @enderror" required>
        <option value="">— pilih role —</option>
        @foreach (\App\Enums\UserRole::cases() as $r)
          <option value="{{ $r->value }}">{{ $r->label() }}</option>
        @endforeach
      </select>
      @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3" x-show="role === 'operator_gudang'" x-cloak>
      <label class="form-label" for="gudang_id">Gudang</label>
      <select id="gudang_id" name="gudang_id" class="form-select @error('gudang_id') is-invalid @enderror">
        <option value="">— pilih gudang —</option>
        @foreach ($daftarGudang as $g)
          <option value="{{ $g->id }}" {{ (int) old('gudang_id', $user->gudang_id) === $g->id ? 'selected' : '' }}>
            {{ $g->nama }}
          </option>
        @endforeach
      </select>
      <small class="form-hint">Wajib diisi untuk role Operator Gudang — dipakai buat batasi akses cuma ke gudang ini.</small>
      @error('gudang_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
  </div>

  <div class="mb-3">
    @if ($user->id === auth()->id())
      <label class="form-check form-switch">
        <input class="form-check-input" type="checkbox" checked disabled>
        <span class="form-check-label">Aktif</span>
      </label>
      <input type="hidden" name="is_active" value="1">
      <small class="form-hint d-block">Tidak bisa menonaktifkan akun sendiri.</small>
    @else
      <label class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
        <span class="form-check-label">Aktif</span>
      </label>
    @endif
  </div>
</div>
