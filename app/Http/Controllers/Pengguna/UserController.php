<?php

namespace App\Http\Controllers\Pengguna;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengguna\UserRequest;
use App\Models\Gudang;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    use LogsActivity;

    private const KOLOM_LOGGED = ['name', 'email', 'role', 'gudang_id', 'is_active'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('gudang')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'q'));
    }

    public function create(): View
    {
        $daftarGudang = Gudang::where('is_active', true)->orderBy('nama')->get();

        return view('users.create', ['user' => new User, 'daftarGudang' => $daftarGudang]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        $this->catatAktivitas('create', $user, null, $user->only(self::KOLOM_LOGGED), 'Tambah user baru');

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" berhasil ditambahkan.");
    }

    public function edit(User $user): View
    {
        $daftarGudang = Gudang::where('is_active', true)->orWhere('id', $user->gudang_id)->orderBy('nama')->get();

        return view('users.edit', compact('user', 'daftarGudang'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $dataLama = $user->only(self::KOLOM_LOGGED);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Anti self-lockout: jangan sampai satu-satunya akun yang bisa kelola
        // user malah kekunci sendiri.
        if ($user->id === auth()->id()) {
            if (($data['is_active'] ?? true) === false) {
                return back()->withInput()->with('error', 'Tidak bisa menonaktifkan akun sendiri.');
            }

            if ($data['role'] !== UserRole::SuperAdmin->value && $user->role === UserRole::SuperAdmin) {
                return back()->withInput()->with('error', 'Tidak bisa menurunkan role sendiri dari Super Admin. Minta Super Admin lain untuk mengubah ini.');
            }
        }

        if (
            $user->role === UserRole::SuperAdmin
            && ($data['role'] !== UserRole::SuperAdmin->value || ($data['is_active'] ?? true) === false)
            && ! $this->adaSuperAdminAktifLain($user->id)
        ) {
            return back()->withInput()->with('error', 'Ini Super Admin aktif terakhir — buat/aktifkan Super Admin lain dulu sebelum mengubah role atau menonaktifkan user ini.');
        }

        $user->update($data);

        $this->catatAktivitas('update', $user, $dataLama, $user->only(self::KOLOM_LOGGED), 'Ubah data user');

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" berhasil diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        if ($user->role === UserRole::SuperAdmin && ! $this->adaSuperAdminAktifLain($user->id)) {
            return back()->with('error', 'Ini satu-satunya akun Super Admin, tidak bisa dihapus.');
        }

        $nama = $user->name;
        $dataLama = $user->only(self::KOLOM_LOGGED);

        try {
            $user->delete();
        } catch (QueryException) {
            return redirect()->route('users.index')
                ->with('error', "User \"{$nama}\" masih tercatat di data lain (activity log/transaksi), tidak bisa dihapus. Nonaktifkan saja.");
        }

        $this->catatAktivitas('delete', $user, $dataLama, null, 'Hapus user');

        return redirect()->route('users.index')->with('success', "User \"{$nama}\" berhasil dihapus.");
    }

    private function adaSuperAdminAktifLain(int $kecualiUserId): bool
    {
        return User::where('role', UserRole::SuperAdmin)
            ->where('id', '!=', $kecualiUserId)
            ->where('is_active', true)
            ->exists();
    }
}
