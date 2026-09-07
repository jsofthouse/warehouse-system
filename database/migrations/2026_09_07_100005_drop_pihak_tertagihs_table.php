<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pihak tertagih final jadi snapshot kolom langsung di `invoice` (lihat
 * migration tambah_surat_jalan_dan_snapshot_tertagih_ke_invoices_table), bukan
 * tabel master. Tabel ini dari skeleton awal tidak pernah punya relasi ke
 * `invoice` dan tidak pernah dipakai kode mana pun — aman dihapus.
 * "04-invoice-sewa-gudang.md" §10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pihak_tertagih');
    }

    public function down(): void
    {
        Schema::create('pihak_tertagih', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('npwp', 30)->nullable();
            $table->timestamps();
        });
    }
};
