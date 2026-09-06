<?php

namespace Tests\Feature\Master;

use App\Enums\BasisTarif;
use App\Enums\UserRole;
use App\Models\AlokasiKebutuhan;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Lokasi;
use App\Models\TarifSewa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test layar master data dan form transaksi.
 *
 * Kenapa ada: layar-layar ini sebelumnya tidak punya test sama sekali, padahal
 * tiap kali nama variabel di controller diganti, nama yang sama harus ikut
 * berubah di Blade-nya. Kalau meleset sebelah, Blade melempar "Undefined
 * variable" dan halamannya mati — tidak ada yang menangkap sampai ada yang
 * membukanya manual. Ini yang hampir kejadian waktu penyeragaman nama
 * `$gudangs` -> `$daftarGudang` (CLAUDE.md §9).
 *
 * Sengaja dangkal: cuma memastikan halamannya render DAN isinya benar-benar
 * keluar. Aturan bisnis per modul diuji di test modulnya masing-masing.
 * assertSee dipakai supaya halaman yang render tapi tabelnya kosong (gejala
 * variabel yang salah nama tapi kebetulan tidak error) tetap ketahuan.
 */
class LayarMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_layar_master_render_beserta_isinya(): void
    {
        $this->actingAs($this->operatorPusat());
        [$gudang, $item, $lokasi] = $this->masterContoh();

        $this->get(route('master.gudang.index'))->assertOk()->assertSee($gudang->nama);
        $this->get(route('master.item.index'))->assertOk()->assertSee($item->nama);
        $this->get(route('master.lokasi.index'))->assertOk()->assertSee($lokasi->nama_kodim);
        $this->get(route('master.alokasi.index'))->assertOk()->assertSee($lokasi->nama_kodim);
        $this->get(route('master.alokasi.show', $lokasi))->assertOk()->assertSee($item->nama);
        $this->get(route('master.alokasi.set-massal.form'))->assertOk()->assertSee($item->nama);
        $this->get(route('master.tarif.index'))->assertOk()->assertSee($gudang->nama);
    }

    public function test_layar_form_transaksi_dan_dashboard_render(): void
    {
        $this->actingAs($this->operatorPusat());
        [$gudang, $item, $lokasi] = $this->masterContoh();

        // Form penerimaan merender daftar item sebagai baris yang bisa dipilih.
        $this->get(route('penerimaan.create'))->assertOk()->assertSee('Baris Barang');

        // Form surat jalan merender dropdown lokasi; tabel empat angka
        // pembandingnya baru diisi lewat AJAX setelah lokasi dipilih.
        $this->get(route('surat-jalan.create'))->assertOk()->assertSee($lokasi->nama_kodim);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_viewer_masih_bisa_membaca_master_tapi_tidak_tarif(): void
    {
        [$gudang, $item, $lokasi] = $this->masterContoh();

        $this->actingAs(User::factory()->create([
            'role' => UserRole::Viewer,
            'gudang_id' => $gudang->id,
            'is_active' => true,
        ]));

        $this->get(route('master.gudang.index'))->assertOk()->assertSee($gudang->nama);
        $this->get(route('master.item.index'))->assertOk()->assertSee($item->nama);
        $this->get(route('master.lokasi.index'))->assertOk()->assertSee($lokasi->nama_kodim);

        // Tarif sewa tertutup buat viewer — lihat matriks di routes/web.php.
        $this->get(route('master.tarif.index'))->assertForbidden();
    }

    // --- Pendukung ----------------------------------------------------------

    private function operatorPusat(): User
    {
        return User::factory()->create([
            'role' => UserRole::OperatorPusat,
            'gudang_id' => null,
            'is_active' => true,
        ]);
    }

    /** @return array{0: Gudang, 1: Item, 2: Lokasi} */
    private function masterContoh(): array
    {
        $gudang = Gudang::firstOrCreate(['kode' => 'GDG-SMG'], [
            'nama' => 'Gudang Semarang',
            'kota' => 'Semarang',
            'is_active' => true,
        ]);

        $item = Item::firstOrCreate(['kode' => 'ALK-01'], [
            'nama' => 'Traktor Tangan',
            'satuan' => 'UNIT',
            'is_active' => true,
        ]);

        $lokasi = Lokasi::firstOrCreate(['kode' => 'KDM-01'], [
            'nama_kodim' => 'Kodim 0724 Boyolali',
            'provinsi' => 'Jawa Tengah',
            'kabupaten' => 'Boyolali',
            'kecamatan' => 'Juwangi',
            'desa' => 'Pilangrejo',
            'is_active' => true,
        ]);

        AlokasiKebutuhan::firstOrCreate(
            ['lokasi_id' => $lokasi->id, 'item_id' => $item->id],
            ['jumlah_kebutuhan' => 4],
        );

        TarifSewa::firstOrCreate(['gudang_id' => $gudang->id, 'berlaku_mulai' => '2026-01-01'], [
            'basis' => BasisTarif::KgAktual,
            'tarif_per_satuan_per_hari' => 150,
            'faktor_volumetrik_kg_per_m3' => 250,
            'ppn_persen' => 11,
            'min_hari_simpan' => 0,
        ]);

        return [$gudang, $item, $lokasi];
    }
}
