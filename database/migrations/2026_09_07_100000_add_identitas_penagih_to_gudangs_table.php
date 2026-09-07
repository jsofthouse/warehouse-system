<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas "pihak menagih" buat kop Invoice Sewa Gudang — nama, alamat, dan
 * NPWP badan hukum yang menerbitkan invoice. Bisa beda dari nama/alamat fisik
 * gudang (kolom `nama`/`alamat` yang sudah ada), makanya kolom terpisah, bukan
 * dipakai ulang.
 *
 * Data belum ada dari klien (lihat "06-pertanyaan-klien.md" B8), semua nullable
 * biar tidak memblokir pekerjaan lain ("04-invoice-sewa-gudang.md" §0 & §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang', function (Blueprint $table) {
            $table->string('nama_penagih')->nullable()->after('alamat');
            $table->text('alamat_penagih')->nullable()->after('nama_penagih');
            $table->string('npwp_penagih', 30)->nullable()->after('alamat_penagih');
        });
    }

    public function down(): void
    {
        Schema::table('gudang', function (Blueprint $table) {
            $table->dropColumn(['nama_penagih', 'alamat_penagih', 'npwp_penagih']);
        });
    }
};
