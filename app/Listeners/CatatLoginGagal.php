<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * Catat percobaan login gagal ke activity_log (docs/12-modul-activity-log.md
 * §4.1). Laravel 11+ auto-discover listener ini lewat type-hint parameter
 * handle(), tidak perlu registrasi manual.
 *
 * $event->user terisi (User yang emailnya cocok tapi password salah) atau
 * null (email sama sekali tidak ada). user_id sengaja SELALU null di sini —
 * login gagal berarti tidak ada yang benar-benar terautentikasi, jadi tidak
 * ada "pelaku" yang sah untuk dicatat sebagai user_id. Identitas yang dicoba
 * tetap terlacak lewat subjek (kalau ada) dan data_baru.email_dicoba.
 *
 * PENTING (08-keamanan.md §12): password mentah TIDAK PERNAH disentuh di
 * sini — cuma key 'email' yang diambil dari $event->credentials.
 */
class CatatLoginGagal
{
    public function handle(Failed $event): void
    {
        /** @var ?User $subjek */
        $subjek = $event->user instanceof User ? $event->user : null;

        ActivityLog::catat(
            'login_gagal',
            $subjek,
            dataBaru: ['email_dicoba' => $event->credentials['email'] ?? null],
            deskripsi: 'Percobaan login gagal',
        );
    }
}
