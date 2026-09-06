@extends('layouts.app')

@section('title', 'Pengguna')
@section('pretitle', 'Sistem')

@section('page-actions')
  <a href="{{ route('users.create') }}" class="btn btn-primary">
    <i class="ti ti-plus me-1"></i> User
  </a>
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <form method="GET" class="d-flex gap-2 w-100">
      <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Cari nama atau email…">
      <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table card-table table-vcenter">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Role</th>
          <th>Gudang</th>
          <th>Status</th>
          <th class="w-1"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($users as $u)
          <tr>
            <td>
              {{ $u->name }}
              @if ($u->id === auth()->id())
                <span class="badge bg-blue-lt ms-1">Anda</span>
              @endif
            </td>
            <td>{{ $u->email }}</td>
            <td><span class="badge bg-azure-lt">{{ $u->role->label() }}</span></td>
            <td>{{ $u->gudang->nama ?? '—' }}</td>
            <td>
              @if ($u->is_active)
                <span class="badge bg-green-lt">Aktif</span>
              @else
                <span class="badge bg-secondary-lt">Nonaktif</span>
              @endif
            </td>
            <td>
              <div class="btn-list flex-nowrap">
                <a href="{{ route('users.edit', $u) }}" class="btn btn-icon btn-sm" title="Ubah">
                  <i class="ti ti-pencil"></i>
                </a>
                @unless ($u->id === auth()->id())
                  <form method="POST" action="{{ route('users.destroy', $u) }}"
                        onsubmit="return confirm('Hapus user &quot;{{ $u->name }}&quot;? Aksi ini tidak bisa dibatalkan.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-icon btn-sm text-danger" title="Hapus">
                      <i class="ti ti-trash"></i>
                    </button>
                  </form>
                @endunless
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada user.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex align-items-center">
    {{ $users->links() }}
  </div>
</div>
@endsection
