<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RBAC generik berbasis UserRole. Dipasang per-route/group:
 * `->middleware('role:super_admin,operator_pusat')`.
 *
 * Ini lapisan pertama pertahanan (menolak akses ke halaman/route). Untuk
 * modul transaksi (penerimaan, surat jalan, dst.) scoping `gudang_id` WAJIB
 * tetap diterapkan lagi di level query saat modulnya dibangun — lihat
 * "Spesifikasi Sistem Gudang Alkap.md" §4, Data scoping. Middleware ini saja
 * tidak cukup untuk kasus itu.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->role || ! in_array($user->role, $this->resolveRoles($roles), true)) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, UserRole>
     */
    private function resolveRoles(array $roles): array
    {
        return array_filter(array_map(
            fn (string $role) => UserRole::tryFrom($role),
            $roles
        ));
    }
}
