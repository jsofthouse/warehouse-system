<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Basis tarif final KG_AKTUAL, dan sekarang ada DUA harga per baris tarif
 * ("04-invoice-sewa-gudang.md" §0 keputusan #2):
 * - harga_jual_per_satuan_per_hari — tampil di invoice ke klien. Ganti nama
 *   dari `tarif_per_satuan_per_hari`, bukan kolom baru.
 * - harga_beli_per_satuan_per_hari — cost internal buat laporan margin, TIDAK
 *   PERNAH tampil di invoice cetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarif_sewa', function (Blueprint $table) {
            $table->renameColumn('tarif_per_satuan_per_hari', 'harga_jual_per_satuan_per_hari');
        });

        Schema::table('tarif_sewa', function (Blueprint $table) {
            $table->decimal('harga_beli_per_satuan_per_hari', 15, 2)
                ->after('harga_jual_per_satuan_per_hari');
        });
    }

    public function down(): void
    {
        Schema::table('tarif_sewa', function (Blueprint $table) {
            $table->dropColumn('harga_beli_per_satuan_per_hari');
        });

        Schema::table('tarif_sewa', function (Blueprint $table) {
            $table->renameColumn('harga_jual_per_satuan_per_hari', 'tarif_per_satuan_per_hari');
        });
    }
};
