<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;

/**
 * Catat login sukses ke activity_log (docs/12-modul-activity-log.md §4.1).
 * Laravel 11+ auto-discover listener ini lewat type-hint parameter handle(),
 * tidak perlu registrasi manual di provider manapun.
 */
class CatatLoginBerhasil
{
    public function handle(Login $event): void
    {
        // user_id diisi lewat auth()->id() di dalam ActivityLog::catat() —
        // sudah terisi karena event ini terpicu setelah sesi login jadi.
        ActivityLog::catat('login', $event->user, deskripsi: 'Login berhasil');
    }
}
