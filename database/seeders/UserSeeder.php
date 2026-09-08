<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Bikin akun login awal buat testing RBAC (belum ada modul login/auth —
     * ini cuma nyiapin datanya). Password di-random tiap kali seeder jalan dan
     * DICETAK ke console, TIDAK PERNAH di-hardcode di kode — jangan diubah jadi
     * password tetap. Di production, seeder ini menolak jalan tanpa --force
     * karena tiap jalan generate ulang password 3 akun seed.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $dipaksa = $this->command && $this->command->hasOption('force') && $this->command->option('force');

            if (! $dipaksa) {
                throw new \RuntimeException(
                    'UserSeeder ditolak jalan di production tanpa flag --force. Seeder ini generate ULANG '.
                    'password random 3 akun seed setiap kali dijalankan — kalau tidak sengaja dijalankan ulang '.
                    'di hosting, password lama yang sudah dipakai bisa berubah tanpa peringatan. Jalankan '.
                    '"php artisan db:seed --class=UserSeeder --force" HANYA kalau memang sengaja mau reset '.
                    'password akun seed.'
                );
            }
        }

        $gudangSemarang = Gudang::where('kode', 'GDG-SMG')->first();

        $akun = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@gudang-alkap.local',
                'role' => UserRole::SuperAdmin,
                'gudang_id' => null,
            ],
            [
                'name' => 'Operator Pusat',
                'email' => 'operator.pusat@gudang-alkap.local',
                'role' => UserRole::OperatorPusat,
                'gudang_id' => null,
            ],
            [
                'name' => 'Operator Gudang Semarang',
                'email' => 'operator.semarang@gudang-alkap.local',
                'role' => UserRole::OperatorGudang,
                'gudang_id' => $gudangSemarang?->id,
            ],
        ];

        foreach ($akun as $data) {
            $password = Str::password(16);

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password, // di-cast 'hashed' otomatis oleh model
                    'role' => $data['role'],
                    'gudang_id' => $data['gudang_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $this->command?->warn("{$data['email']} / {$password}  (catat, tidak ditampilkan lagi)");
        }
    }
}
