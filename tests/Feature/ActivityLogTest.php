<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Lokasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uji wajib menurut docs/12-modul-activity-log.md §10.8: pencatatan login &
 * login gagal (tanpa password bocor), gate Super Admin di halaman list, filter
 * jenis dokumen + rentang tanggal, dan gabungan riwayat Lokasi + Alokasi lewat
 * subjek yang sama.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    // --- Pencatatan login & login gagal (§4.1) -------------------------------

    public function test_login_sukses_menulis_activity_log(): void
    {
        $user = User::factory()->create([
            'email' => 'sukses@contoh.test',
            'password' => Hash::make('password-benar-1'),
            'role' => UserRole::Viewer,
            'gudang_id' => null,
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => 'sukses@contoh.test',
            'password' => 'password-benar-1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $log = ActivityLog::where('aksi', 'login')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
    }

    public function test_login_gagal_password_salah_mencatat_subjek_user_tanpa_user_id_dan_tanpa_password(): void
    {
        $user = User::factory()->create([
            'email' => 'passwordsalah@contoh.test',
            'password' => Hash::make('password-yang-benar'),
            'role' => UserRole::Viewer,
            'gudang_id' => null,
            'is_active' => true,
        ]);

        $passwordDicoba = 'tebakan-salah-rahasia-XYZ';

        $this->post(route('login'), [
            'email' => 'passwordsalah@contoh.test',
            'password' => $passwordDicoba,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $log = ActivityLog::where('aksi', 'login_gagal')->firstOrFail();

        $this->assertNull($log->user_id);
        $this->assertSame('user', $log->subjek_type);
        $this->assertSame($user->id, $log->subjek_id);
        $this->assertSame('passwordsalah@contoh.test', $log->data_baru['email_dicoba'] ?? null);

        // Password mentah tidak boleh nyangkut di kolom manapun (08-keamanan.md §12).
        $seluruhBaris = json_encode($log->toArray());
        $this->assertStringNotContainsString($passwordDicoba, $seluruhBaris);
        $this->assertStringNotContainsString('password-yang-benar', $seluruhBaris);
        $this->assertArrayNotHasKey('password', $log->data_baru ?? []);
    }

    public function test_login_gagal_email_tidak_ada_mencatat_subjek_null(): void
    {
        $this->post(route('login'), [
            'email' => 'tidak-terdaftar@contoh.test',
            'password' => 'apapun-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $log = ActivityLog::where('aksi', 'login_gagal')->firstOrFail();

        $this->assertNull($log->user_id);
        $this->assertNull($log->subjek_type);
        $this->assertNull($log->subjek_id);
        $this->assertSame('tidak-terdaftar@contoh.test', $log->data_baru['email_dicoba'] ?? null);
    }

    // --- Gate Super Admin (§4.2, §5.1) ---------------------------------------

    public function test_super_admin_bisa_akses_halaman_activity_log(): void
    {
        $this->actingAs($this->pengguna(UserRole::SuperAdmin));

        $this->get(route('activity-log.index'))->assertOk();
    }

    #[DataProvider('rolesBukanSuperAdmin')]
    public function test_non_super_admin_dapat_403_akses_langsung_activity_log(UserRole $role): void
    {
        $gudang = $role === UserRole::OperatorGudang ? $this->gudang() : null;

        $this->actingAs($this->pengguna($role, $gudang));

        $this->get(route('activity-log.index'))->assertForbidden();
    }

    public static function rolesBukanSuperAdmin(): array
    {
        return [
            'operator_pusat' => [UserRole::OperatorPusat],
            'operator_gudang' => [UserRole::OperatorGudang],
            'viewer' => [UserRole::Viewer],
        ];
    }

    // --- Filter jenis dokumen & rentang tanggal (§4.2) -----------------------

    public function test_filter_jenis_dokumen_dan_rentang_tanggal_menghasilkan_baris_yang_cocok(): void
    {
        $gudang = $this->gudang();
        $item = $this->item();

        $logGudangDalamRentang = $this->buatLog('update', $gudang, '2026-02-10 09:00:00');
        $this->buatLog('update', $item, '2026-02-10 10:00:00'); // jenis dokumen beda
        $this->buatLog('update', $gudang, '2026-01-01 09:00:00'); // di luar rentang tanggal
        $this->buatLog('hitung_ulang_stok_harian', null, '2026-02-10 11:00:00'); // tanpa dokumen

        $this->actingAs($this->pengguna(UserRole::SuperAdmin));

        $response = $this->get(route('activity-log.index', [
            'dari' => '2026-02-01',
            'sampai' => '2026-02-28',
            'subjek_type' => 'gudang',
        ]));

        $response->assertOk();
        $daftar = $response->viewData('daftarActivityLog');

        $this->assertCount(1, $daftar);
        $this->assertSame($logGudangDalamRentang->id, $daftar->first()->id);
    }

    public function test_filter_subjek_type_tidak_valid_diabaikan_bukan_dipakai_mentah(): void
    {
        $gudang = $this->gudang();
        $this->buatLog('update', $gudang, '2026-02-10 09:00:00');

        $this->actingAs($this->pengguna(UserRole::SuperAdmin));

        // Nilai subjek_type sembarangan (bukan alias morph map yang valid)
        // harus diabaikan whitelist-nya, bukan dipakai mentah di where().
        $response = $this->get(route('activity-log.index', [
            'dari' => '2026-02-01',
            'sampai' => '2026-02-28',
            'subjek_type' => 'App\\Models\\User', // bukan alias, harus ditolak whitelist
        ]));

        $response->assertOk();
        // Karena diabaikan, filter subjek_type tidak diterapkan -> baris gudang tetap ikut.
        $this->assertGreaterThanOrEqual(1, $response->viewData('daftarActivityLog')->total());
    }

    // --- Riwayat gabungan Lokasi + Alokasi (§4.3) ----------------------------

    public function test_riwayat_lokasi_menggabungkan_log_lokasi_dan_log_alokasi(): void
    {
        $lokasi = Lokasi::create([
            'kode' => 'KDM-99',
            'nama_kodim' => 'Kodim 0999 Contoh',
            'provinsi' => 'Jawa Tengah',
            'is_active' => true,
        ]);
        $item = $this->item();

        $superAdmin = $this->pengguna(UserRole::SuperAdmin);
        $this->actingAs($superAdmin);

        // Ubah data lokasi -> subjek = Lokasi.
        $this->put(route('master.lokasi.update', $lokasi), [
            'kode' => 'KDM-99',
            'nama_kodim' => 'Kodim 0999 Contoh (Diubah)',
            'provinsi' => 'Jawa Tengah',
            'is_active' => true,
        ])->assertRedirect();

        // Ubah alokasi kebutuhan lokasi -> subjek juga = Lokasi (bukan AlokasiKebutuhan).
        $this->put(route('master.alokasi.update', $lokasi), [
            'jumlah' => [$item->id => 5],
        ])->assertRedirect();

        $response = $this->get(route('activity-log.index', [
            'subjek_type' => 'lokasi',
            'subjek_id' => $lokasi->id,
        ]));

        $response->assertOk();
        $daftar = $response->viewData('daftarActivityLog');

        $deskripsi = $daftar->pluck('deskripsi')->all();
        $this->assertContains('Ubah data lokasi', $deskripsi);
        $this->assertContains('Ubah alokasi kebutuhan per item', $deskripsi);
    }

    // --- Pendukung ------------------------------------------------------------

    private function pengguna(UserRole $role, ?Gudang $gudang = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'gudang_id' => $gudang?->id,
            'is_active' => true,
        ]);
    }

    private function gudang(): Gudang
    {
        return Gudang::create([
            'kode' => 'GDG-01',
            'nama' => 'Gudang Contoh',
            'kota' => 'Semarang',
            'is_active' => true,
        ]);
    }

    private function item(): Item
    {
        return Item::create([
            'kode' => 'ITM-01',
            'nama' => 'Item Contoh',
            'satuan' => 'UNIT',
            'is_active' => true,
        ]);
    }

    private function buatLog(string $aksi, $subjek, string $createdAt): ActivityLog
    {
        $log = ActivityLog::create([
            'user_id' => null,
            'aksi' => $aksi,
            'subjek_type' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'deskripsi' => 'Baris uji filter',
            'ip_address' => '127.0.0.1',
        ]);

        $log->forceFill(['created_at' => $createdAt])->save();

        return $log->fresh();
    }
}
