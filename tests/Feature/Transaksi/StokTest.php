<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusPenerimaan;
use App\Enums\TipeMutasiStok;
use App\Enums\UserRole;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Penerimaan;
use App\Models\StokHarian;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Uji yang wajib ada menurut "Modul Kartu Stok dan Stok Harian.md" §10.8.
 */
class StokTest extends TestCase
{
    use RefreshDatabase;

    // --- Kartu Stok -----------------------------------------------------

    public function test_stok_on_hand_sama_dengan_agregasi_manual_dari_ledger(): void
    {
        $gudang = $this->gudang();
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pengguna = $this->pengguna(UserRole::OperatorPusat, null);

        $this->mutasi($gudang, $item, TipeMutasiStok::In, 30, $pengguna);
        $this->mutasi($gudang, $item, TipeMutasiStok::Out, 6, $pengguna);
        $this->mutasi($gudang, $item, TipeMutasiStok::In, 12, $pengguna);
        // Simulasi pembatalan dokumen: mutasi asli + mutasi balik, keduanya tetap di ledger.
        $this->mutasi($gudang, $item, TipeMutasiStok::Out, 6, $pengguna);
        $this->mutasi($gudang, $item, TipeMutasiStok::In, 6, $pengguna);

        // 30 - 6 + 12 - 6 + 6 = 36
        $this->assertSame(36, StokMutasi::saldoAkhir($gudang->id, $item->id));

        $this->actingAs($pengguna)
            ->get(route('stok.index', ['gudang_id' => $gudang->id]))
            ->assertOk()
            ->assertSee('36');
    }

    public function test_riwayat_menampilkan_mutasi_balik_dokumen_dibatalkan_sebagai_baris_terpisah(): void
    {
        $gudang = $this->gudang();
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pengguna = $this->pengguna(UserRole::OperatorPusat, null);

        $penerimaan = Penerimaan::create([
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'vendor_nama' => 'CV Sumber Tani',
            'created_by' => $pengguna->id,
        ]);
        // 'status' sengaja tidak ada di $fillable Penerimaan (lihat komentar di
        // model) — disamakan dengan pola PenerimaanController yang pakai
        // forceFill() untuk kolom yang server-only.
        $penerimaan->forceFill(['status' => StatusPenerimaan::Dibatalkan])->save();

        $penerimaan->mutasi()->create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => now()->toDateString(),
            'tipe' => TipeMutasiStok::In,
            'jumlah' => 10,
            'created_by' => $pengguna->id,
        ]);
        $penerimaan->mutasi()->create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => now()->toDateString(),
            'tipe' => TipeMutasiStok::Out,
            'jumlah' => 10,
            'created_by' => $pengguna->id,
            'keterangan' => 'Pembalik pembatalan',
        ]);

        $respons = $this->actingAs($pengguna)
            ->get(route('stok.riwayat', ['item' => $item->id, 'gudang_id' => $gudang->id]));

        $respons->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Keluar')
            ->assertSee('Pembalik pembatalan');
    }

    public function test_operator_gudang_tidak_bisa_lihat_stok_gudang_lain(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);

        $this->mutasi($gudangB, $item, TipeMutasiStok::In, 99, $operatorA);

        // Manipulasi query string tidak boleh melebarkan akses (CLAUDE.md §5.2).
        $this->actingAs($operatorA)
            ->get(route('stok.index', ['gudang_id' => $gudangB->id]))
            ->assertOk()
            ->assertDontSee('99');

        $this->actingAs($operatorA)
            ->get(route('stok.riwayat', ['item' => $item->id, 'gudang_id' => $gudangB->id]))
            ->assertOk()
            ->assertDontSee('99');
    }

    public function test_operator_gudang_tidak_bisa_akses_stok_harian(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);

        $this->actingAs($operator)
            ->get(route('stok.harian'))
            ->assertForbidden();

        $this->actingAs($operator)
            ->post(route('stok.harian.hitung-ulang'), [
                'gudang_id' => $gudang->id,
                'dari' => now()->toDateString(),
                'sampai' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    // --- Stok Harian (command) ------------------------------------------

    public function test_command_hitung_stok_harian_idempoten(): void
    {
        $gudang = $this->gudang();
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pengguna = $this->pengguna(UserRole::OperatorPusat, null);
        $tanggal = Carbon::parse('2026-09-01');

        $this->mutasi($gudang, $item, TipeMutasiStok::In, 30, $pengguna, $tanggal);

        Artisan::call('stok:hitung-harian', ['--dari' => $tanggal->toDateString(), '--sampai' => $tanggal->toDateString()]);
        Artisan::call('stok:hitung-harian', ['--dari' => $tanggal->toDateString(), '--sampai' => $tanggal->toDateString()]);

        $this->assertSame(1, StokHarian::where('gudang_id', $gudang->id)
            ->where('item_id', $item->id)
            ->whereDate('tanggal', $tanggal)
            ->count());

        $this->assertSame(30, StokHarian::where('gudang_id', $gudang->id)
            ->where('item_id', $item->id)
            ->whereDate('tanggal', $tanggal)
            ->value('stok_akhir'));
    }

    public function test_command_hitung_ulang_memperbarui_snapshot_setelah_mutasi_baru(): void
    {
        $gudang = $this->gudang();
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pengguna = $this->pengguna(UserRole::OperatorPusat, null);
        $tanggal = Carbon::parse('2026-09-01');

        $this->mutasi($gudang, $item, TipeMutasiStok::In, 30, $pengguna, $tanggal);
        Artisan::call('stok:hitung-harian', ['--dari' => $tanggal->toDateString(), '--sampai' => $tanggal->toDateString()]);

        $this->assertSame(30, StokHarian::where('gudang_id', $gudang->id)
            ->where('item_id', $item->id)->whereDate('tanggal', $tanggal)->value('stok_akhir'));

        // Mutasi baru ditambahkan di tanggal yang sudah pernah di-snapshot.
        $this->mutasi($gudang, $item, TipeMutasiStok::In, 12, $pengguna, $tanggal);
        Artisan::call('stok:hitung-harian', ['--dari' => $tanggal->toDateString(), '--sampai' => $tanggal->toDateString()]);

        $this->assertSame(42, StokHarian::where('gudang_id', $gudang->id)
            ->where('item_id', $item->id)->whereDate('tanggal', $tanggal)->value('stok_akhir'));
    }

    // --- Pendukung --------------------------------------------------------

    private function gudang(string $kode = 'GDG-SMG', string $nama = 'Gudang Semarang'): Gudang
    {
        return Gudang::create([
            'kode' => $kode,
            'nama' => $nama,
            'kota' => $nama,
            'is_active' => true,
        ]);
    }

    private function item(string $kode, string $nama): Item
    {
        return Item::create([
            'kode' => $kode,
            'nama' => $nama,
            'satuan' => 'UNIT',
            'is_active' => true,
        ]);
    }

    private function pengguna(UserRole $role, ?Gudang $gudang = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'gudang_id' => $gudang?->id,
            'is_active' => true,
        ]);
    }

    private function mutasi(
        Gudang $gudang,
        Item $item,
        TipeMutasiStok $tipe,
        int $jumlah,
        User $pembuat,
        ?Carbon $tanggal = null,
    ): StokMutasi {
        return StokMutasi::create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => ($tanggal ?? now())->toDateString(),
            'tipe' => $tipe,
            'jumlah' => $jumlah,
            'created_by' => $pembuat->id,
        ]);
    }
}
