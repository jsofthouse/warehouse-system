<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ikut rename di `tarif_sewa` ("04-invoice-sewa-gudang.md" §0 keputusan #2) —
 * kolom ini snapshot dari sana di titik invoice dibuat. `subtotal_pokok` baru,
 * cost internal buat laporan margin dari `harga_beli_per_satuan_per_hari`,
 * TIDAK PERNAH tampil di invoice cetak (lihat resources/views/invoice/cetak.blade.php).
 *
 * Tabel `invoice_detail` masih kosong (mesin hitungnya baru dibangun sekarang),
 * jadi aman ditambah NOT NULL langsung tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_detail', function (Blueprint $table) {
            $table->renameColumn('tarif_per_satuan_per_hari', 'harga_jual_per_satuan_per_hari');
        });

        Schema::table('invoice_detail', function (Blueprint $table) {
            $table->decimal('harga_beli_per_satuan_per_hari', 15, 2)->after('harga_jual_per_satuan_per_hari');
            $table->decimal('subtotal_pokok', 15, 2)
                ->after('subtotal')
                ->comment('Biaya pokok internal (pakai harga_beli) — cuma buat laporan margin, tidak tampil di cetak');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_detail', function (Blueprint $table) {
            $table->dropColumn(['harga_beli_per_satuan_per_hari', 'subtotal_pokok']);
        });

        Schema::table('invoice_detail', function (Blueprint $table) {
            $table->renameColumn('harga_jual_per_satuan_per_hari', 'tarif_per_satuan_per_hari');
        });
    }
};
