<?php

namespace App\Console\Commands;

use App\Models\Gudang;
use App\Models\Item;
use App\Models\StokHarian;
use App\Models\StokMutasi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Snapshot stok akhir hari ke `stok_harian` — prasyarat teknis Modul Invoice
 * Sewa Gudang (kg-hari dihitung dari tabel ini, bukan dari `stok_mutasi`
 * langsung). Lihat "Modul Kartu Stok dan Stok Harian.md" §4.2.
 *
 * Idempoten: `updateOrCreate` pada unique key (gudang_id, item_id, tanggal),
 * jadi menjalankan ulang untuk tanggal yang sama meng-overwrite baris yang
 * sama, bukan menambah baris baru (§2 prinsip 2 dokumen yang sama).
 *
 * Tiap tanggal dihitung independen dari ledger (`StokMutasi::saldoAkhir()`),
 * bukan berantai dari snapshot hari sebelumnya (§2 prinsip 3) — supaya satu
 * hari yang salah/dilewat tidak menjalar ke hari-hari sesudahnya.
 */
class HitungStokHarian extends Command
{
    protected $signature = 'stok:hitung-harian
        {--gudang= : ID gudang tunggal. Kosongkan untuk semua gudang aktif}
        {--dari= : Tanggal mulai (Y-m-d). Default: hari ini}
        {--sampai= : Tanggal akhir (Y-m-d). Default: sama dengan --dari}';

    protected $description = 'Hitung ulang snapshot stok akhir hari (stok_harian) dari ledger stok_mutasi';

    public function handle(): int
    {
        $dari = $this->option('dari') ? Carbon::parse($this->option('dari'))->startOfDay() : Carbon::today();
        $sampai = $this->option('sampai') ? Carbon::parse($this->option('sampai'))->startOfDay() : $dari->copy();

        if ($sampai->lt($dari)) {
            $this->error('Tanggal --sampai tidak boleh sebelum --dari.');

            return self::FAILURE;
        }

        // Tanggal masa depan tidak berarti apa-apa untuk sebuah snapshot "akhir
        // hari" — dipangkas ke hari ini, bukan ditolak seluruhnya, supaya
        // command yang dijadwalkan tanpa opsi tetap aman dipanggil kapan saja.
        $hariIni = Carbon::today();
        if ($sampai->gt($hariIni)) {
            $sampai = $hariIni->copy();
        }
        if ($dari->gt($sampai)) {
            $this->info('Tidak ada tanggal yang perlu dihitung (rentang sepenuhnya di masa depan).');

            return self::SUCCESS;
        }

        $gudangId = $this->option('gudang');

        $daftarGudang = Gudang::query()
            ->where('is_active', true)
            ->when($gudangId !== null, fn ($q) => $q->whereKey((int) $gudangId))
            ->get(['id']);

        if ($daftarGudang->isEmpty()) {
            $this->error('Tidak ada gudang aktif yang cocok.');

            return self::FAILURE;
        }

        $daftarItem = Item::query()->where('is_active', true)->get(['id']);

        $jumlahBaris = 0;

        for ($tanggal = $dari->copy(); $tanggal->lte($sampai); $tanggal->addDay()) {
            foreach ($daftarGudang as $gudang) {
                foreach ($daftarItem as $item) {
                    $stokAkhir = StokMutasi::saldoAkhir($gudang->id, $item->id, $tanggal);

                    StokHarian::updateOrCreate(
                        [
                            'gudang_id' => $gudang->id,
                            'item_id' => $item->id,
                            'tanggal' => $tanggal->toDateString(),
                        ],
                        ['stok_akhir' => max(0, $stokAkhir)],
                    );

                    $jumlahBaris++;
                }
            }
        }

        $this->info("Snapshot stok harian selesai: {$jumlahBaris} baris ({$daftarGudang->count()} gudang × {$daftarItem->count()} item × ".
            ($dari->diffInDays($sampai) + 1).' hari).');

        return self::SUCCESS;
    }
}
