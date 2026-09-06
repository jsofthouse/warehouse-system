<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'gudang_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    /**
     * super_admin & operator_pusat lihat semua gudang; operator_gudang & viewer
     * cuma boleh disentuh/dilihat untuk gudangnya sendiri. Wajib dipakai di
     * setiap query transaksi — lihat "Spesifikasi Sistem Gudang Alkap.md" §4.
     */
    public function bisaAksesSemuaGudang(): bool
    {
        return $this->role?->isLintasGudang() ?? false;
    }
}
