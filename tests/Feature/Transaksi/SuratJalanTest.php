<?php

namespace Tests\Feature\Transaksi;

use App\Enums\StatusPenerimaan;
use App\Enums\StatusSuratJalan;
use App\Enums\TipeMutasiStok;
use App\Enums\UserRole;
use App\Events\SuratJalanDibatalkan;
use App\Models\AlokasiKebutuhan;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\Lokasi;
use App\Models\Penerimaan;
use App\Models\StokMutasi;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Uji wajib menurut "10-modul-surat-jalan.md" §11.10, plus aturan otorisasi
 * lintas gudang di "08-keamanan.md" §2.3.
 */
class SuratJalanTest extends TestCase
{
    use RefreshDatabase;

    // --- Otorisasi lintas gudang -------------------------------------------

    public function test_operator_gudang_tidak_bisa_menyentuh_dokumen_gudang_lain(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $lokasi = $this->lokasi();

        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);
        $operatorB = $this->pengguna(UserRole::OperatorGudang, $gudangB);

        $dokumenB = $this->draft($gudangB, $lokasi, $operatorB);

        $this->actingAs($operatorA);

        $this->get(route('surat-jalan.show', $dokumenB))->assertForbidden();
        $this->get(route('surat-jalan.edit', $dokumenB))->assertForbidden();
        $this->put(route('surat-jalan.update', $dokumenB), [
            'lokasi_id' => $lokasi->id,
            'tanggal' => now()->toDateString(),
        ])->assertForbidden();
        $this->post(route('surat-jalan.posting', $dokumenB))->assertForbidden();
        $this->get(route('surat-jalan.cetak', $dokumenB))->assertForbidden();

        // Dokumen ter-posting milik gudang lain juga tertutup untuk aksi lanjutan.
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $this->stokMasuk($gudangB, $item, 10, $operatorB);
        $this->alokasi($lokasi, $item, 10);

        $terposting = $this->draft($gudangB, $lokasi, $operatorB, [], [$item->id => 3]);
        $this->actingAs($operatorB)->post(route('surat-jalan.posting', $terposting));

        $this->actingAs($operatorA);
        $this->get(route('surat-jalan.diterima.form', $terposting))->assertForbidden();
        $this->post(route('surat-jalan.diterima', $terposting), [
            'tanggal_terima' => now()->toDateString(),
            'nama_penerima' => 'Serka Nakal',
        ])->assertForbidden();

        $this->assertSame(StatusSuratJalan::Posted, $terposting->refresh()->status);
    }

    public function test_daftar_surat_jalan_operator_gudang_cuma_berisi_gudangnya(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');

        $lokasiA = $this->lokasi('KDM-01', 'Kodim Boyolali');
        $lokasiB = $this->lokasi('KDM-02', 'Kodim Bekasi');

        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);

        // Nama sopir dipakai sebagai penanda baris: nama lokasi tidak bisa
        // dipakai karena dropdown filter memang memuat seluruh master lokasi.
        $this->draft($gudangA, $lokasiA, $operatorA, ['nama_sopir' => 'Sopir Semarang']);
        $this->draft($gudangB, $lokasiB, $this->pengguna(UserRole::OperatorGudang, $gudangB), [
            'nama_sopir' => 'Sopir Jakarta',
        ]);

        // Query string gudang_id milik gudang lain tidak boleh melebarkan akses.
        $this->actingAs($operatorA)
            ->get(route('surat-jalan.index', ['gudang_id' => $gudangB->id]))
            ->assertOk()
            ->assertSee('Sopir Semarang')
            ->assertDontSee('Sopir Jakarta');
    }

    public function test_operator_gudang_tidak_bisa_membuat_dokumen_untuk_gudang_lain(): void
    {
        $gudangA = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $gudangB = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $lokasi = $this->lokasi();
        $operatorA = $this->pengguna(UserRole::OperatorGudang, $gudangA);

        $this->actingAs($operatorA)
            ->post(route('surat-jalan.store'), [
                'gudang_id' => $gudangB->id, // diabaikan server
                'lokasi_id' => $lokasi->id,
                'tanggal' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($gudangA->id, SuratJalan::first()->gudang_id);
    }

    public function test_viewer_tidak_bisa_membuat_memposting_atau_menandai_diterima(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $viewer = $this->pengguna(UserRole::Viewer, $gudang);
        $dokumen = $this->draft($gudang, $lokasi, $this->pengguna(UserRole::OperatorGudang, $gudang));

        $this->actingAs($viewer);

        $this->get(route('surat-jalan.index'))->assertOk();
        $this->get(route('surat-jalan.show', $dokumen))->assertOk();
        $this->get(route('surat-jalan.create'))->assertForbidden();
        $this->get(route('surat-jalan.pembanding', ['lokasi_id' => $lokasi->id]))->assertForbidden();
        $this->post(route('surat-jalan.posting', $dokumen))->assertForbidden();
    }

    // --- Validasi form ------------------------------------------------------

    public function test_dua_baris_dengan_item_sama_ditolak_di_validasi_form(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->stokMasuk($gudang, $item, 20, $operator);
        $this->alokasi($lokasi, $item, 20);

        $this->actingAs($operator)
            ->from(route('surat-jalan.create'))
            ->post(route('surat-jalan.store'), [
                'lokasi_id' => $lokasi->id,
                'tanggal' => now()->toDateString(),
                'detail' => [
                    ['item_id' => $item->id, 'jumlah_kirim' => 2],
                    ['item_id' => $item->id, 'jumlah_kirim' => 3],
                ],
            ])
            ->assertSessionHasErrors('detail.1.item_id');

        $this->assertSame(0, SuratJalan::count());
    }

    public function test_form_menolak_jumlah_kirim_di_atas_stok_maupun_di_atas_sisa_alokasi(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);

        $stokTipis = $this->item('ALK-01', 'Mesin Penanam Benih');
        $alokasiTipis = $this->item('ALK-02', 'Genset');

        // Stok cuma 3 padahal alokasinya 9.
        $this->stokMasuk($gudang, $stokTipis, 3, $operator);
        $this->alokasi($lokasi, $stokTipis, 9);

        // Stok berlimpah tapi alokasinya cuma 2.
        $this->stokMasuk($gudang, $alokasiTipis, 50, $operator);
        $this->alokasi($lokasi, $alokasiTipis, 2);

        $this->actingAs($operator)
            ->post(route('surat-jalan.store'), [
                'lokasi_id' => $lokasi->id,
                'tanggal' => now()->toDateString(),
                'detail' => [
                    ['item_id' => $stokTipis->id, 'jumlah_kirim' => 5],
                    ['item_id' => $alokasiTipis->id, 'jumlah_kirim' => 4],
                ],
            ])
            ->assertSessionHasErrors(['detail.0.jumlah_kirim', 'detail.1.jumlah_kirim']);

        $this->assertSame(0, SuratJalan::count());
    }

    // --- Posting ------------------------------------------------------------

    public function test_posting_ditolak_kalau_jumlah_kirim_melebihi_stok_gudang(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 4, $operator);

        // Draft dibuat langsung lewat model: yang diuji di sini adalah lapis
        // pemeriksaan di server saat posting, bukan yang di form request.
        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 6]);

        $this->actingAs($operator)
            ->from(route('surat-jalan.show', $dokumen))
            ->post(route('surat-jalan.posting', $dokumen))
            ->assertRedirect(route('surat-jalan.show', $dokumen))
            ->assertSessionHas('error');

        $this->assertStringContainsString('stok gudang tinggal 4', session('error'));

        $dokumen->refresh();

        $this->assertSame(StatusSuratJalan::Draft, $dokumen->status);
        $this->assertNull($dokumen->nomor_surat_jalan);
        // Cuma mutasi IN dari setup; tidak ada OUT yang tertulis.
        $this->assertSame(0, StokMutasi::where('tipe', TipeMutasiStok::Out->value)->count());
    }

    public function test_posting_ditolak_kalau_jumlah_kirim_melebihi_sisa_alokasi(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 5);
        $this->stokMasuk($gudang, $item, 100, $operator);

        // Kiriman pertama sudah menghabiskan 4 dari 5 alokasi.
        $pertama = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 4]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $pertama));
        $this->assertSame(StatusSuratJalan::Posted, $pertama->refresh()->status);

        // Sisa tinggal 1, tapi dokumen kedua mau kirim 3.
        $kedua = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 3]);

        $this->actingAs($operator)
            ->from(route('surat-jalan.show', $kedua))
            ->post(route('surat-jalan.posting', $kedua))
            ->assertSessionHas('error');

        $this->assertStringContainsString('sisa alokasi lokasi ini tinggal 1', session('error'));
        $this->assertSame(StatusSuratJalan::Draft, $kedua->refresh()->status);
        $this->assertSame(1, StokMutasi::where('tipe', TipeMutasiStok::Out->value)->count());
    }

    public function test_posting_menulis_mutasi_out_dan_menerbitkan_nomor_berformat_romawi(): void
    {
        $gudang = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 20);
        $this->stokMasuk($gudang, $item, 20, $operator);

        $tanggal = now()->setDate(2026, 9, 3)->startOfDay();
        $dokumen = $this->draft($gudang, $lokasi, $operator, ['tanggal' => $tanggal->toDateString()], [$item->id => 7]);

        $this->actingAs($operator)
            ->post(route('surat-jalan.posting', $dokumen))
            ->assertRedirect(route('surat-jalan.show', $dokumen));

        $dokumen->refresh();

        $this->assertSame(StatusSuratJalan::Posted, $dokumen->status);
        $this->assertSame('SJ/SMG/0001/IX/2026', $dokumen->nomor_surat_jalan);
        $this->assertSame($operator->id, $dokumen->posted_by);
        $this->assertNotNull($dokumen->posted_at);

        $keluar = StokMutasi::where('tipe', TipeMutasiStok::Out->value)->sole();

        $this->assertSame(7, $keluar->jumlah);
        $this->assertSame($gudang->id, $keluar->gudang_id);
        // Mutasi memakai tanggal dokumen, bukan tanggal input.
        $this->assertSame($dokumen->tanggal->toDateString(), $keluar->tanggal->toDateString());
        // Alias morph map pendek, bukan nama kelas penuh (AppServiceProvider::boot(),
        // docs/12-modul-activity-log.md §3.2 — berlaku ke semua morphTo/morphMany
        // yang memakai SuratJalan, termasuk StokMutasi::referensi ini).
        $this->assertSame('surat_jalan', $keluar->referensi_type);
        $this->assertSame($dokumen->id, $keluar->referensi_id);

        // Stok on-hand berkurang persis sebanyak yang dikirim.
        $this->assertSame(13, $this->stokOnHand($gudang, $item));

        $this->assertDatabaseHas('activity_log', [
            'aksi' => 'post',
            'subjek_type' => 'surat_jalan',
            'subjek_id' => $dokumen->id,
        ]);
    }

    public function test_posting_beruntun_untuk_gudang_sama_tidak_menghasilkan_nomor_kembar(): void
    {
        $semarang = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $jakarta = $this->gudang('GDG-JKT', 'Gudang Jakarta');
        $lokasi = $this->lokasi();
        $item = $this->item('ALK-01', 'Traktor Tangan');
        $pusat = $this->pengguna(UserRole::OperatorPusat);

        $this->alokasi($lokasi, $item, 100);
        $this->stokMasuk($semarang, $item, 100, $pusat);
        $this->stokMasuk($jakarta, $item, 100, $pusat);

        $this->actingAs($pusat);

        $nomor = [];

        // Bulan berbeda di gudang yang sama: deret urut TIDAK boleh ikut reset,
        // cuma segmen romawinya yang berganti ("10-modul-surat-jalan.md" §10.3).
        $rencana = [
            [$semarang, '2026-08-05'],
            [$semarang, '2026-09-03'],
            [$jakarta, '2026-09-04'],
        ];

        foreach ($rencana as [$gudang, $tanggal]) {
            $dokumen = $this->draft($gudang, $lokasi, $pusat, ['tanggal' => $tanggal], [$item->id => 1]);

            $this->post(route('surat-jalan.posting', $dokumen));

            $nomor[] = $dokumen->refresh()->nomor_surat_jalan;
        }

        $this->assertSame([
            'SJ/SMG/0001/VIII/2026',
            'SJ/SMG/0002/IX/2026',
            'SJ/JKT/0001/IX/2026',
        ], $nomor);

        $this->assertCount(3, array_unique($nomor));
    }

    /**
     * Dua request yang benar-benar berbarengan tidak bisa direproduksi di
     * SQLite in-memory (satu koneksi, satu writer), jadi yang diuji di sini
     * adalah jaring terakhirnya: unique index di kolom nomor menolak duplikat
     * kalau toh ada yang lolos dari row lock.
     *
     * Pengaman lapis pertamanya — GeneratesNomorDokumen melempar LogicException
     * kalau dipanggil di luar DB::transaction(), jadi lockForUpdate() pasti
     * memegang sesuatu — tidak bisa diuji lewat feature test karena
     * RefreshDatabase sendiri sudah membungkus tiap test dalam transaksi.
     */
    public function test_nomor_kembar_ditutup_oleh_transaksi_wajib_dan_unique_index(): void
    {
        $gudang = $this->gudang('GDG-SMG', 'Gudang Semarang');
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 100);
        $this->stokMasuk($gudang, $item, 100, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, ['tanggal' => '2026-09-03'], [$item->id => 1]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $nomor = $dokumen->refresh()->nomor_surat_jalan;

        $kembar = $this->draft($gudang, $lokasi, $operator, ['tanggal' => '2026-09-04'], [$item->id => 1]);

        $this->expectException(QueryException::class);

        DB::table('surat_jalan')->where('id', $kembar->id)->update(['nomor_surat_jalan' => $nomor]);
    }

    public function test_posting_draft_kosong_dan_dokumen_ter_posting_ditolak(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $kosong = $this->draft($gudang, $lokasi, $operator);

        $this->actingAs($operator)
            ->from(route('surat-jalan.show', $kosong))
            ->post(route('surat-jalan.posting', $kosong))
            ->assertSessionHas('error');

        $this->assertSame(StatusSuratJalan::Draft, $kosong->refresh()->status);

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $terisi = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 2]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $terisi));

        // Dokumen ter-posting terkunci dari edit dan posting ulang.
        $this->actingAs($operator)->get(route('surat-jalan.edit', $terisi))->assertForbidden();
        $this->actingAs($operator)->post(route('surat-jalan.posting', $terisi))->assertForbidden();

        $this->assertSame(1, StokMutasi::where('tipe', TipeMutasiStok::Out->value)->count());
    }

    // --- Tandai diterima ----------------------------------------------------

    public function test_tandai_diterima_mengubah_status_dan_mengunci_tombol_batalkan(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, ['tanggal' => now()->subDays(2)->toDateString()], [$item->id => 4]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        // Selagi masih posted, tombol batalkan tersedia buat Operator Pusat.
        $this->actingAs($pusat)->get(route('surat-jalan.batalkan.form', $dokumen))->assertOk();

        $this->actingAs($operator)
            ->post(route('surat-jalan.diterima', $dokumen), [
                'tanggal_terima' => now()->toDateString(),
                'nama_penerima' => 'Serma Bambang',
                'nomor_bast' => 'BAST/2026/09/001',
            ])
            ->assertRedirect(route('surat-jalan.show', $dokumen));

        $dokumen->refresh();

        $this->assertSame(StatusSuratJalan::Diterima, $dokumen->status);
        $this->assertSame('Serma Bambang', $dokumen->nama_penerima);
        $this->assertSame('BAST/2026/09/001', $dokumen->nomor_bast);
        $this->assertSame(now()->toDateString(), $dokumen->tanggal_terima->toDateString());

        // Tidak ada mutasi baru: stok sudah kepotong sejak posting.
        $this->assertSame(1, StokMutasi::where('tipe', TipeMutasiStok::Out->value)->count());

        // Tombol batalkan terkunci — di layar maupun di endpoint-nya.
        $this->actingAs($pusat)->get(route('surat-jalan.batalkan.form', $dokumen))->assertForbidden();
        $this->actingAs($pusat)
            ->get(route('surat-jalan.show', $dokumen))
            ->assertOk()
            ->assertDontSee(route('surat-jalan.batalkan.form', $dokumen));

        $this->assertDatabaseHas('activity_log', [
            'aksi' => 'terima',
            'subjek_type' => 'surat_jalan',
            'subjek_id' => $dokumen->id,
        ]);
    }

    public function test_tandai_diterima_wajib_isi_tanggal_dan_nama_penerima(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 2]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $this->actingAs($operator)
            ->from(route('surat-jalan.diterima.form', $dokumen))
            ->post(route('surat-jalan.diterima', $dokumen), [])
            ->assertSessionHasErrors(['tanggal_terima', 'nama_penerima']);

        $this->assertSame(StatusSuratJalan::Posted, $dokumen->refresh()->status);
    }

    // --- Pembatalan ---------------------------------------------------------

    public function test_pembatalan_hanya_dari_status_posted(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 20);
        $this->stokMasuk($gudang, $item, 20, $operator);

        // Draft belum bisa dibatalkan — belum ada yang perlu dibalik.
        $draft = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 2]);

        $this->actingAs($pusat)
            ->post(route('surat-jalan.batalkan', $draft), ['alasan_pembatalan' => 'Salah lokasi tujuan'])
            ->assertForbidden();

        // Dokumen yang sudah diterima juga tertutup ("10-modul-surat-jalan.md" §2.5).
        $diterima = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 3]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $diterima));
        $this->actingAs($operator)->post(route('surat-jalan.diterima', $diterima), [
            'tanggal_terima' => now()->toDateString(),
            'nama_penerima' => 'Serma Bambang',
        ]);

        $this->assertSame(StatusSuratJalan::Diterima, $diterima->refresh()->status);

        $this->actingAs($pusat)
            ->post(route('surat-jalan.batalkan', $diterima), [
                'alasan_pembatalan' => 'Ternyata barangnya salah spesifikasi',
            ])
            ->assertForbidden();

        $this->assertSame(StatusSuratJalan::Diterima, $diterima->refresh()->status);
        // Tidak ada mutasi balik IN selain yang dari setup stok awal.
        $this->assertSame(1, StokMutasi::where('tipe', TipeMutasiStok::In->value)->count());
    }

    public function test_operator_gudang_tidak_boleh_membatalkan(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 2]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $this->actingAs($operator)
            ->post(route('surat-jalan.batalkan', $dokumen), ['alasan_pembatalan' => 'Truk batal berangkat'])
            ->assertForbidden();

        $this->assertSame(StatusSuratJalan::Posted, $dokumen->refresh()->status);
    }

    public function test_pembatalan_menulis_mutasi_balik_in_dan_melepas_event(): void
    {
        Event::fake([SuratJalanDibatalkan::class]);

        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, ['tanggal' => now()->subDays(2)->toDateString()], [$item->id => 6]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $nomor = $dokumen->refresh()->nomor_surat_jalan;
        $this->assertSame(4, $this->stokOnHand($gudang, $item));

        $this->actingAs($pusat)
            ->post(route('surat-jalan.batalkan', $dokumen), [
                'alasan_pembatalan' => 'Truk batal berangkat, barang kembali ke gudang',
            ])
            ->assertRedirect(route('surat-jalan.show', $dokumen));

        $dokumen->refresh();

        $this->assertSame(StatusSuratJalan::Dibatalkan, $dokumen->status);
        $this->assertSame($pusat->id, $dokumen->dibatalkan_by);
        $this->assertNotNull($dokumen->dibatalkan_at);
        // Nomor resmi tetap, tidak dipakai ulang.
        $this->assertSame($nomor, $dokumen->nomor_surat_jalan);

        $balik = StokMutasi::where('referensi_type', 'surat_jalan')
            ->where('tipe', TipeMutasiStok::In->value)
            ->sole();

        $this->assertSame(6, $balik->jumlah);
        $this->assertSame($dokumen->tanggal->toDateString(), $balik->tanggal->toDateString());
        $this->assertSame("Pembatalan surat jalan {$nomor}", $balik->keterangan);

        // Stok kembali utuh.
        $this->assertSame(10, $this->stokOnHand($gudang, $item));

        // Sisa alokasi ikut pulih: dokumen batal tidak lagi dihitung terkirim.
        $this->actingAs($operator)
            ->getJson(route('surat-jalan.pembanding', ['lokasi_id' => $lokasi->id]))
            ->assertOk()
            ->assertJsonPath('baris.0.sudah_kirim', 0)
            ->assertJsonPath('baris.0.sisa', 10);

        Event::assertDispatched(SuratJalanDibatalkan::class);

        $this->assertDatabaseHas('activity_log', [
            'aksi' => 'cancel',
            'subjek_type' => 'surat_jalan',
            'subjek_id' => $dokumen->id,
        ]);
    }

    public function test_alasan_pembatalan_wajib_dan_tidak_boleh_asal(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 1]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $this->actingAs($pusat)
            ->from(route('surat-jalan.batalkan.form', $dokumen))
            ->post(route('surat-jalan.batalkan', $dokumen), ['alasan_pembatalan' => 'salah'])
            ->assertSessionHasErrors('alasan_pembatalan');

        $this->assertSame(StatusSuratJalan::Posted, $dokumen->refresh()->status);
    }

    // --- Empat angka pembanding & cetak -------------------------------------

    public function test_endpoint_pembanding_mengembalikan_empat_angka_dan_batas_kirim(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Mesin Penanam Benih');

        $this->alokasi($lokasi, $item, 9);
        $this->stokMasuk($gudang, $item, 3, $operator);

        $terkirim = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 2]);
        $this->actingAs($operator)->post(route('surat-jalan.posting', $terkirim));

        // Alokasi 9, sudah kirim 2, sisa 7, stok tinggal 1 -> batas 1.
        $this->actingAs($operator)
            ->getJson(route('surat-jalan.pembanding', ['lokasi_id' => $lokasi->id]))
            ->assertOk()
            ->assertJsonPath('baris.0.alokasi', 9)
            ->assertJsonPath('baris.0.sudah_kirim', 2)
            ->assertJsonPath('baris.0.sisa', 7)
            ->assertJsonPath('baris.0.stok_gudang', 1)
            ->assertJsonPath('baris.0.batas', 1);
    }

    public function test_draft_tidak_bisa_dicetak_tapi_dokumen_ter_posting_bisa(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 1]);

        $this->actingAs($operator)->get(route('surat-jalan.cetak', $dokumen))->assertForbidden();

        $this->actingAs($operator)->post(route('surat-jalan.posting', $dokumen));

        $this->actingAs($operator)
            ->get(route('surat-jalan.cetak', $dokumen))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // --- Layar --------------------------------------------------------------

    public function test_semua_layar_surat_jalan_bisa_dibuka(): void
    {
        $gudang = $this->gudang();
        $lokasi = $this->lokasi();
        $operator = $this->pengguna(UserRole::OperatorGudang, $gudang);
        $pusat = $this->pengguna(UserRole::OperatorPusat);
        $item = $this->item('ALK-01', 'Traktor Tangan');

        $this->alokasi($lokasi, $item, 10);
        $this->stokMasuk($gudang, $item, 10, $operator);

        $dokumen = $this->draft($gudang, $lokasi, $operator, [], [$item->id => 3]);

        $this->actingAs($operator);
        $this->get(route('surat-jalan.index'))->assertOk();
        $this->get(route('surat-jalan.create'))->assertOk()->assertSee('Barang yang Dikirim');
        $this->get(route('surat-jalan.edit', $dokumen))->assertOk();
        $this->get(route('surat-jalan.show', $dokumen))->assertOk()->assertSee('Traktor Tangan');

        $this->post(route('surat-jalan.posting', $dokumen));

        $this->get(route('surat-jalan.diterima.form', $dokumen))->assertOk()->assertSee('Nama penerima');

        $this->actingAs($pusat);
        $this->get(route('surat-jalan.batalkan.form', $dokumen))->assertOk()->assertSee('Alasan pembatalan');
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

    private function lokasi(string $kode = 'KDM-01', string $namaKodim = 'Kodim 0724 Boyolali'): Lokasi
    {
        return Lokasi::create([
            'kode' => $kode,
            'nama_kodim' => $namaKodim,
            'provinsi' => 'Jawa Tengah',
            'kabupaten' => 'Boyolali',
            'kecamatan' => 'Juwangi',
            'desa' => 'Pilangrejo',
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
     * Isi stok awal buat pengujian. Sejak alokasi FIFO ada
     * ("04-invoice-sewa-gudang.md" §3a), surat jalan cuma bisa menarik stok
     * dari batch `penerimaan_detail` yang beneran posted — jadi ini bikin
     * Penerimaan ter-posting beneran, bukan cuma nulis mutasi ledger telanjang
     * seperti sebelumnya.
     */
    private function stokMasuk(Gudang $gudang, Item $item, int $jumlah, User $oleh): StokMutasi
    {
        $tanggal = now()->subMonth()->toDateString();

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

        return StokMutasi::create([
            'gudang_id' => $gudang->id,
            'item_id' => $item->id,
            'tanggal' => $tanggal,
            'tipe' => TipeMutasiStok::In,
            'jumlah' => $jumlah,
            'referensi_type' => $penerimaan->getMorphClass(),
            'referensi_id' => $penerimaan->id,
            'keterangan' => 'Stok awal untuk pengujian',
            'created_by' => $oleh->id,
        ]);
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

    private function stokOnHand(Gudang $gudang, Item $item): int
    {
        $mutasi = StokMutasi::where('gudang_id', $gudang->id)->where('item_id', $item->id)->get();

        return (int) $mutasi->sum(fn ($m) => $m->tipe === TipeMutasiStok::In ? $m->jumlah : -$m->jumlah);
    }
}
