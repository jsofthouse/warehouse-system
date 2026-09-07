<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas "pihak tertagih" buat kop Invoice Sewa Gudang — nama, alamat, dan
 * NPWP penerima tagihan. Belum ada tabel yang cocok buat data ini, jadi skema
 * baru ("04-invoice-sewa-gudang.md" §10).
 *
 * Data belum ada dari klien ("06-pertanyaan-klien.md" B8), semua nullable.
 * Belum di-relasikan ke `invoice` — cara invoice merujuk ke sini (foreign key
 * vs snapshot kolom, mengikuti pola `invoice_detail`) masih terbuka, menyusul
 * bareng pembangunan mesin hitung invoice yang sesungguhnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pihak_tertagih', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pihak_tertagih');
    }
};
