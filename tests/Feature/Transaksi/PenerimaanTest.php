<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusPenerimaan;
use App\Enums\TipeMutasiStok;
use App\Enums\UserRole;
use App\Events\PenerimaanDibatalkan;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Penerimaan;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Uji yang wajib ada menurut "08-keamanan.md" §2.3 (akses lintas gudang di
 * detail, cetak, dan aksi) plus aturan stok & penomoran di
 * "03-aturan-bisnis.md" §2 dan §3.
 */
class PenerimaanTest extends TestCase
{
    use RefreshDatabase;

    // --- Otorisasi lintas gudang -------------------------------------------

    public function test_operator_gudang_tidak_bisa_menyentuh_dokumen_gudang_lain(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');

        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);
        $dokumenB = $this->draft($gudangB, $this->pengguna(UserRole::OperatorGudang, $gudangB));

        $this->actingAs($operatorA);

        $this->get(route('penerimaan.show', $dokumenB))->assertForbidden();
        $this->get(route('penerimaan.edit', $dokumenB))->assertForbidden();
        $this->put(route('penerimaan.update', $dokumenB), [
            'tanggal' => now()->toDateString(),
            'vendor_nama' => 'Vendor Nakal',
        ])->assertForbidden();
        $this->post(route('penerimaan.posting', $dokumenB))->assertForbidden();
        $this->get(route('penerimaan.cetak', $dokumenB))->assertForbidden();
    }

    public function test_daftar_penerimaan_operator_gudang_cuma_berisi_gudangnya(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');

        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);

        $this->draft($gudangA, $operatorA, ['vendor_nama' => 'Vendor Semarang']);
        $this->draft($gudangB, $this->pengguna(UserRole::OperatorGudang, $gudangB), ['vendor_nama' => 'Vendor Jakarta']);

        // Query string gudang_id milik gudang lain tidak boleh melebarkan akses.
        $this->actingAs($operatorA)
            ->get(route('penerimaan.index', ['gudang_id' => $gudangB->id]))
            ->assertOk()
            ->assertSee('Vendor Semarang')
            ->assertDontSee('Vendor Jakarta');
    }

    public function test_viewer_tidak_bisa_membuat_atau_memposting(): void
    {
        $gudang = $this->gudang();
        $viewer = $this->pengguna(UserRole::Viewer, $gudang);
        $dokumen = $this->draft($gudang, $this->pengguna(UserRole::OperatorGudang, $gudang));

        $this->actingAs($viewer);

        $this->get(route('penerimaan.index'))->assertOk();
        $this->get(route('penerimaan.show', $dokumen))->assertOk();
        $this->get(route('penerimaan.create'))->assertForbidden();
        $this->post(route('penerimaan.posting', $dokumen))->assertForbidden();
    }

    // --- Validasi form ------------------------------------------------------

    public function test_dua_baris_dengan_item_sama_ditolak_di_validasi_form(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->actingAs($operator)
            ->from(route('penerimaan.create'))
            ->post(route('penerimaan.store'), [
                'tanggal' => now()->toDateString(),
                'vendor_nama' => 'CV Sumber Tani',
                'detail' => [
                    ['item_id' => $item->id, 'jumlah' => 2],
                    ['item_id' => $item->id, 'jumlah' => 3],
                ],
            ])
            ->assertSessionHasErrors('detail.1.item_id');

        $this->assertSame(0, Penerimaan::count());
    }

    public function test_tanggal_yang_akan_datang_ditolak_tapi_backdate_diterima(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);

        $this->actingAs($operator)
            ->post(route('penerimaan.store'), [
                'tanggal' => now()->addDay()->toDateString(),
                'vendor_nama' => 'CV Sumber Tani',
            ])
            ->assertSessionHasErrors('tanggal');

        $this->actingAs($operator)
            ->post(route('penerimaan.store'), [
                'tanggal' => now()->subMonths(6)->toDateString(),
                'vendor_nama' => 'CV Sumber Tani',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Penerimaan::count());
    }

    public function test_operator_gudang_tidak_bisa_membuat_dokumen_untuk_gudang_lain(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);

        $this->actingAs($operatorA)
            ->post(route('penerimaan.store'), [
                'gudang_id' => $gudangB->id, // diabaikan server
                'tanggal' => now()->toDateString(),
                'vendor_nama' => 'CV Sumber Tani',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($gudangA->id, Penerimaan::first()->gudang_id);
    }

    // --- Posting ------------------------------------------------------------

    public function test_posting_draft_tanpa_baris_detail_ditolak(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $dokumen = $this->draft($gudang, $operator);

        $this->actingAs($operator)
            ->from(route('penerimaan.show', $dokumen))
            ->post(route('penerimaan.posting', $dokumen))
            ->assertRedirect(route('penerimaan.show', $dokumen))
            ->assertSessionHas('error');

        $dokumen->refresh();

        $this->assertSame(StatusPenerimaan::Draft, $dokumen->status);
        $this->assertNull($dokumen->nomor_penerimaan);
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_posting_ditolak_kalau_ada_item_yang_sudah_dinonaktifkan(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 4]);

        $item->update(['is_active' => false]);

        $this->actingAs($operator)
            ->from(route('penerimaan.show', $dokumen))
            ->post(route('penerimaan.posting', $dokumen))
            ->assertSessionHas('error');

        $this->assertSame(StatusPenerimaan::Draft, $dokumen->refresh()->status);
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_posting_menulis_mutasi_in_dan_menerbitkan_nomor_berformat(): void
    {
        $gudang = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator, ['tanggal' => now()->subDays(3)->toDateString()]);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 7]);

        $this->actingAs($operator)
            ->post(route('penerimaan.posting', $dokumen))
            ->assertRedirect(route('penerimaan.show', $dokumen));

        $dokumen->refresh();
        $tahun = $dokumen->tanggal->year;

        $this->assertSame(StatusPenerimaan::Posted, $dokumen->status);
        $this->assertSame("BM-SMG-{$tahun}-0001", $dokumen->nomor_penerimaan);
        $this->assertSame($operator->id, $dokumen->posted_by);
        $this->assertNotNull($dokumen->posted_at);

        $mutasi = StokMutasi::sole();

        $this->assertSame(TipeMutasiStok::In, $mutasi->tipe);
        $this->assertSame(7, $mutasi->jumlah);
        $this->assertSame($gudang->id, $mutasi->gudang_id);
        // Mutasi memakai tanggal dokumen, bukan tanggal input.
        $this->assertSame($dokumen->tanggal->toDateString(), $mutasi->tanggal->toDateString());
        $this->assertSame(Penerimaan::class, $mutasi->referensi_type);
        $this->assertSame($dokumen->id, $mutasi->referensi_id);

        $this->assertDatabaseHas('activity_log', [
            'aksi' => 'post',
            'subjek_type' => Penerimaan::class,
            'subjek_id' => $dokumen->id,
        ]);
    }

    public function test_nomor_dokumen_berurut_dan_unik_per_gudang_per_tahun(): void
    {
        $semarang = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $jakarta = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pusat = $this->pengguna(UserRole::OperatorPusat);

        $this->actingAs($pusat);

        $nomor = [];

        foreach ([$semarang, $semarang, $jakarta] as $gudang) {
            $dokumen = $this->draft($gudang, $pusat);
            $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 1]);

            $this->post(route('penerimaan.posting', $dokumen));

            $nomor[] = $dokumen->refresh()->nomor_penerimaan;
        }

        $tahun = now()->year;

        $this->assertSame([
            "BM-SMG-{$tahun}-0001",
            "BM-SMG-{$tahun}-0002",
            "BM-JKT-{$tahun}-0001",
        ], $nomor);

        $this->assertCount(3, array_unique($nomor));
    }

    public function test_dokumen_ter_posting_tidak_bisa_diedit_atau_diposting_ulang(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 2]);

        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        $this->actingAs($operator)->get(route('penerimaan.edit', $dokumen))->assertForbidden();
        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen))->assertForbidden();

        $this->assertSame(1, StokMutasi::count());
    }

    // --- Pembatalan ---------------------------------------------------------

    public function test_operator_gudang_tidak_boleh_membatalkan(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 2]);
        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        $this->actingAs($operator)
            ->post(route('penerimaan.batalkan', $dokumen), ['alasan_pembatalan' => 'Salah input dari vendor'])
            ->assertForbidden();

        $this->assertSame(StatusPenerimaan::Posted, $dokumen->refresh()->status);
    }

    public function test_pembatalan_ditolak_kalau_stok_jadi_negatif(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 5]);
        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        // 4 unit terlanjur keluar (anggap lewat surat jalan) — sisa 1.
        StokMutasi::create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => now()->toDateString(),
            'tipe' => TipeMutasiStok::Out,
            'jumlah' => 4,
            'keterangan' => 'Pengiriman ke lokasi',
            'created_by' => $operator->id,
        ]);

        $this->actingAs($pusat)
            ->from(route('penerimaan.batalkan.form', $dokumen))
            ->post(route('penerimaan.batalkan', $dokumen), [
                'alasan_pembatalan' => 'Vendor kirim barang salah spesifikasi',
            ])
            ->assertRedirect(route('penerimaan.batalkan.form', $dokumen))
            ->assertSessionHas('error');

        $pesan = session('error');

        $this->assertStringContainsString('Traktor Tangan', $pesan);
        $this->assertStringContainsString('kurang 4', $pesan);

        $this->assertSame(StatusPenerimaan::Posted, $dokumen->refresh()->status);
        // Tidak ada mutasi balik yang tertulis.
        $this->assertSame(2, StokMutasi::count());
    }

    public function test_pembatalan_yang_aman_menulis_mutasi_out_dan_melepas_event(): void
    {
        Event::fake([PenerimaanDibatalkan::class]);

        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator, ['tanggal' => now()->subDays(2)->toDateString()]);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 5]);
        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        $nomor = $dokumen->refresh()->nomor_penerimaan;

        $this->actingAs($pusat)
            ->post(route('penerimaan.batalkan', $dokumen), [
                'alasan_pembatalan' => 'Vendor kirim barang salah spesifikasi',
            ])
            ->assertRedirect(route('penerimaan.show', $dokumen));

        $dokumen->refresh();

        $this->assertSame(StatusPenerimaan::Dibatalkan, $dokumen->status);
        $this->assertSame($pusat->id, $dokumen->dibatalkan_by);
        $this->assertNotNull($dokumen->dibatalkan_at);
        $this->assertSame('Vendor kirim barang salah spesifikasi', $dokumen->alasan_pembatalan);
        // Nomor resmi tetap, tidak dipakai ulang.
        $this->assertSame($nomor, $dokumen->nomor_penerimaan);

        $balik = StokMutasi::where('tipe', TipeMutasiStok::Out->value)->sole();

        $this->assertSame(5, $balik->jumlah);
        $this->assertSame($item->id, $balik->item_id);
        $this->assertSame($gudang->id, $balik->gudang_id);
        $this->assertSame($dokumen->tanggal->toDateString(), $balik->tanggal->toDateString());
        $this->assertSame(Penerimaan::class, $balik->referensi_type);
        $this->assertSame($dokumen->id, $balik->referensi_id);
        $this->assertSame("Pembatalan penerimaan {$nomor}", $balik->keterangan);

        // Stok on-hand kembali nol.
        $this->assertSame(0, $this->stokOnHand($gudang, $item));

        Event::assertDispatched(PenerimaanDibatalkan::class);

        $this->assertDatabaseHas('activity_log', [
            'aksi' => 'cancel',
            'subjek_type' => Penerimaan::class,
            'subjek_id' => $dokumen->id,
        ]);
    }

    public function test_alasan_pembatalan_wajib_dan_tidak_boleh_asal(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 1]);
        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        $this->actingAs($pusat)
            ->from(route('penerimaan.batalkan.form', $dokumen))
            ->post(route('penerimaan.batalkan', $dokumen), ['alasan_pembatalan' => 'salah'])
            ->assertSessionHasErrors('alasan_pembatalan');

        $this->assertSame(StatusPenerimaan::Posted, $dokumen->refresh()->status);
    }

    // --- Cetak --------------------------------------------------------------

    public function test_draft_tidak_bisa_dicetak_tapi_dokumen_ter_posting_bisa(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 1]);

        $this->actingAs($operator)->get(route('penerimaan.cetak', $dokumen))->assertForbidden();

        $this->actingAs($operator)->post(route('penerimaan.posting', $dokumen));

        $this->actingAs($operator)
            ->get(route('penerimaan.cetak', $dokumen))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // --- Layar --------------------------------------------------------------

    public function test_semua_layar_penerimaan_bisa_dibuka(): void
    {
        $gudang = $this->gudang();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $dokumen = $this->draft($gudang, $operator);
        $dokumen->detail()->create(['item_id' => $item->id, 'jumlah' => 3]);

        $this->actingAs($operator);
        $this->get(route('penerimaan.index'))->assertOk();
        $this->get(route('penerimaan.create'))->assertOk()->assertSee('Baris Barang');
        $this->get(route('penerimaan.edit', $dokumen))->assertOk();
        $this->get(route('penerimaan.show', $dokumen))->assertOk()->assertSee('Traktor Tangan');

        $this->post(route('penerimaan.posting', $dokumen));

        $this->actingAs($pusat);
        $this->get(route('penerimaan.index'))->assertOk();
        $this->get(route('penerimaan.batalkan.form', $dokumen))->assertOk()->assertSee('Alasan pembatalan');
    }

    // --- Pendukung ----------------------------------------------------------

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

    /** @param  array<string, mixed>  $atribut */
    private function draft(Gudang $gudang, User $pembuat, array $atribut = []): Penerimaan
    {
        return Penerimaan::create(array_merge([
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'vendor_nama' => 'CV Sumber Tani',
            'created_by' => $pembuat->id,
        ], $atribut));
    }

    private function stokOnHand(Gudang $gudang, Item $item): int
    {
        $mutasi = StokMutasi::where('gudang_id', $gudang->id)->where('item_id', $item->id)->get();

        return (int) $mutasi->sum(fn ($m) => $m->tipe === TipeMutasiStok::In ? $m->jumlah : -$m->jumlah);
    }
}
