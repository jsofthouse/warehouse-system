<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barang ini disalurkan ke instansi negara, jadi siapa yang menerima wajib
 * tercatat di sistem — tanda tangan di kertas surat jalan ikut sopir/lokasi,
 * bukan ke pusat yang dipakai buat pelaporan ("10-modul-surat-jalan.md" §10.7).
 *
 * Nullable di DB karena kolomnya baru terisi saat status -> diterima; yang
 * mewajibkan pengisiannya adalah TandaiDiterimaSuratJalanRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->string('nama_penerima', 100)->nullable()->after('nomor_bast');
        });
    }

    public function down(): void
    {
        Schema::table('surat_jalans', function (Blueprint $table) {
            $table->dropColumn('nama_penerima');
        });
    }
};
