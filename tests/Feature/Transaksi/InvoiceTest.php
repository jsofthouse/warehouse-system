<?php

namespace Tests\Feature\Transaksi;

use App\Enums\BasisTarif;
use App\Enums\StatusInvoice;
use App\Enums\StatusPenerimaan;
use App\Enums\TipeMutasiStok;
use App\Enums\UserRole;
use App\Models\AlokasiKebutuhan;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Lokasi;
use App\Models\Penerimaan;
use App\Models\StokMutasi;
use App\Models\SuratJalan;
use App\Models\TarifSewa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mesin hitung invoice sewa gudang — final, tanpa alokasi per-batch
 * ("04-invoice-sewa-gudang.md" §0 & §3b; skema FIFO §3a sudah dorman, tidak
 * dipanggil dari alur ini). Skenario dasar mengikuti pola contoh Genset di §5
 * dokumen, dengan dua dokumen Penerimaan bertanggal beda buat item yang sama
 * supaya aturan "pakai tanggal masuk PALING AWAL (MIN)" ikut teruji.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_invoice_pakai_tanggal_masuk_paling_awal_dari_dua_dokumen_penerimaan(): void
    {
        [$gudang, $lokasi, $item, $operator] = $this->siapkanData();

        // Dua dokumen Penerimaan posted buat item yang sama, tanggal beda.
        // (Tanggal sengaja di masa lalu relatif ke tanggal berjalan proyek ini,
        // 7 September 2026 — posting menolak tanggal surat jalan yang di masa depan.)
        $this->batchMasuk($gudang, $item, 10, '2026-08-20', $operator);
        $this->batchMasuk($gudang, $item, 10, '2026-08-24', $operator);
        $this->alokasi($lokasi, $item, 12);

        // Kirim 12 unit tanggal 29 Agustus. Sesuai §3b.1: tanggal_masuk_item
        // = MIN(20 Agu, 24 Agu) = 20 Agu, dipakai buat SEMUA unit yang
        // dikirim — bukan dipecah FIFO per dokumen penerimaan.
        $suratJalan = $this->draft($gudang, $lokasi, $operator, ['tanggal' => '2026-08-29'], [$item->id => 12]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $suratJalan));
        $suratJalan->refresh();

        // Hitungan manual:
        // tanggal_masuk_item = MIN(20 Agu, 24 Agu) = 20 Agu.
        // hari_simpan = 29 Agu - 20 Agu = 9 hari, berlaku utuh buat 12 unit.
        // unit-hari = 12 * 9 = 108
        // berat_kg item = 100 kg -> kg-hari = 108 * 100 = 10.800
        // harga_jual = Rp 10/kg/hari -> subtotal = 108.000
        // harga_beli = Rp 7/kg/hari -> subtotal_pokok = 75.600
        // PPN 10% -> 10.800, total = 118.800
        $pusat = $this->pengguna(UserRole::OperatorPusat);

        $this->actingAs($pusat)
            ->get(route('invoice.buat.draft', $suratJalan))
            ->assertOk()
            ->assertSee('Genset');

        $this->actingAs($pusat)
            ->post(route('invoice.store'), [
                'surat_jalan_id' => $suratJalan->id,
                'nama_tertagih' => 'Kodim 0724 Boyolali',
            ])
            ->assertSessionHasNoErrors()
            // assertSessionHasNoErrors() cuma soal validation error bag —
            // dicek juga flash 'success' supaya request yang meledak di
            // tengah jalan (exception SETELAH transaksi commit tapi sebelum
            // return) ikut ketahuan, bukan cuma dianggap lolos.
            ->assertSessionHas('success');

        $invoice = $suratJalan->fresh()->invoice;

        $this->assertNotNull($invoice);
        $this->assertSame(StatusInvoice::Draft, $invoice->status);
        $this->assertSame('Kodim 0724 Boyolali', $invoice->nama_tertagih);
        $this->assertSame('2026-08-20', $invoice->periode_mulai->toDateString());
        $this->assertSame('2026-08-29', $invoice->periode_selesai->toDateString());
        $this->assertEqualsWithDelta(108000.0, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(10800.0, (float) $invoice->ppn_nominal, 0.01);
        $this->assertEqualsWithDelta(118800.0, (float) $invoice->total, 0.01);

        $detail = $invoice->detail->sole();
        $this->assertEqualsWithDelta(108.0, (float) $detail->total_unit_hari, 0.01);
        $this->assertEqualsWithDelta(10800.0, (float) $detail->total_satuan_hari, 0.01);
        $this->assertEqualsWithDelta(108000.0, (float) $detail->subtotal, 0.01);
        $this->assertEqualsWithDelta(75600.0, (float) $detail->subtotal_pokok, 0.01);

        // Posting: nomor terbit, status jadi terbit.
        $this->actingAs($pusat)
            ->post(route('invoice.posting', $invoice))
            ->assertRedirect(route('invoice.show', $invoice));

        $invoice->refresh();
        $this->assertSame(StatusInvoice::Terbit, $invoice->status);
        $this->assertNotNull($invoice->nomor_invoice);
        $this->assertStringStartsWith('INV/', $invoice->nomor_invoice);

        // Surat jalan yang sama tidak boleh dapat invoice kedua.
        $this->actingAs($pusat)
            ->get(route('invoice.buat.draft', $suratJalan))
            ->assertRedirect(route('invoice.pilih'));
    }

    public function test_invoice_ditolak_kalau_item_belum_punya_berat_kg(): void
    {
        [$gudang, $lokasi, , $operator] = $this->siapkanData();

        // Item baru tanpa berat_kg — meniru kasus RAN Traktor.
        $itemTanpaBerat = Item::create(['kode' => 'ALK-99', 'nama' => 'RAN Traktor', 'satuan' => 'UNIT', 'is_active' => true]);

        $this->batchMasuk($gudang, $itemTanpaBerat, 5, '2026-09-01', $operator);
        $this->alokasi($lokasi, $itemTanpaBerat, 5);

        $suratJalan = $this->draft($gudang, $lokasi, $operator, ['tanggal' => '2026-09-05'], [$itemTanpaBerat->id => 5]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $suratJalan));

        $pusat = $this->pengguna(UserRole::OperatorPusat);

        $this->actingAs($pusat)
            ->get(route('invoice.buat.draft', $suratJalan))
            ->assertRedirect(route('invoice.pilih'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('berat_kg', session('error'));
        // §7 aturan 2 "04-invoice-sewa-gudang.md": pesan HARUS menyebutkan
        // item mana yang bermasalah, bukan cuma kata "berat_kg" generik.
        $this->assertStringContainsString('RAN Traktor', session('error'));
    }

    // --- Pendukung ----------------------------------------------------------

    /** @return array{0: Gudang, 1: Lokasi, 2: Item, 3: User} */
    private function siapkanData(): array
    {
        $gudang = Gudang::create([
            'kode' => 'GDG-SMG',
            'nama' => 'Gudang Semarang',
            'kota' => 'Semarang',
            'is_active' => true,
        ]);

        $lokasi = Lokasi::create([
            'kode' => 'KDM-01',
            'nama_kodim' => 'Kodim 0724 Boyolali',
            'provinsi' => 'Jawa Tengah',
            'kabupaten' => 'Boyolali',
            'kecamatan' => 'Juwangi',
            'desa' => 'Pilangrejo',
            'is_active' => true,
        ]);

        $item = Item::create([
            'kode' => 'ALK-16',
            'nama' => 'Genset',
            'satuan' => 'UNIT',
            'berat_kg' => 100,
            'is_active' => true,
        ]);

        TarifSewa::create([
            'gudang_id' => $gudang->id,
            'basis' => BasisTarif::KgAktual,
            'harga_jual_per_satuan_per_hari' => 10,
            'harga_beli_per_satuan_per_hari' => 7,
            'faktor_volumetrik_kg_per_m3' => 250,
            'ppn_persen' => 10,
            'min_hari_simpan' => 0,
            'berlaku_mulai' => '2026-01-01',
        ]);

        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);

        return [$gudang, $lokasi, $item, $operator];
    }

    private function alokasi(Lokasi $lokasi, Item $item, int $jumlah): AlokasiKebutuhan
    {
        return AlokasiKebutuhan::create([
            'lokasi_id' => $lokasi->id,
            'item_id' => $item->id,
            'jumlah_kebutuhan' => $jumlah,
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

    /**
     * Bikin satu dokumen Penerimaan ter-posting + detail-nya (dasar
     * `tanggal_masuk_item` §3b.1), plus mutasi IN yang senilai (buat lolos
     * validasi stok saat surat jalan diposting — dua-duanya perlu konsisten,
     * sama seperti alur asli lewat PenerimaanController::posting()).
     */
    private function batchMasuk(Gudang $gudang, Item $item, int $jumlah, string $tanggal, User $oleh): Penerimaan
    {
        $penerimaan = Penerimaan::create([
            'gudang_id' => $gudang->id,
            'tanggal' => $tanggal,
            'vendor_nama' => 'Vendor Uji',
            'created_by' => $oleh->id,
        ]);

        $penerimaan->detail()->create(['item_id' => $item->id, 'jumlah' => $jumlah]);

        $penerimaan->forceFill([
            'nomor_penerimaan' => 'BM-TEST-'.$penerimaan->id,
            'status' => StatusPenerimaan::Posted,
            'posted_by' => $oleh->id,
            'posted_at' => now(),
        ])->save();

        StokMutasi::create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => $tanggal,
            'tipe' => TipeMutasiStok::In,
            'jumlah' => $jumlah,
            'referensi_type' => $penerimaan->getMorphClass(),
            'referensi_id' => $penerimaan->id,
            'keterangan' => 'Setup pengujian',
            'created_by' => $oleh->id,
        ]);

        return $penerimaan->load('detail');
    }

    /**
     * @param  array<string, mixed>  $atribut
     * @param  array<int, int>  $detail  item_id => jumlah_kirim
     */
    private function draft(
        Gudang $gudang,
        Lokasi $lokasi,
        User $pembuat,
        array $atribut = [],
        array $detail = [],
    ): SuratJalan {
        $suratJalan = SuratJalan::create(array_merge([
            'gudang_id' => $gudang->id,
            'lokasi_id' => $lokasi->id,
            'tanggal' => now()->toDateString(),
            'created_by' => $pembuat->id,
        ], $atribut));

        foreach ($detail as $itemId => $jumlah) {
            $suratJalan->detail()->create(['item_id' => $itemId, 'jumlah_kirim' => $jumlah]);
        }

        return $suratJalan;
    }
}
