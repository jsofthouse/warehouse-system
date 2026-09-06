<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tarif itu data, bukan konstanta kode (lihat "Modul Invoice Sewa Gudang" §4).
        // Ganti tarif = tutup baris lama (berlaku_sampai) + baris baru, TIDAK PERNAH
        // mengubah baris lama, supaya invoice lama tetap bisa direproduksi persis.
        Schema::create('tarif_sewas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->restrictOnDelete();
            $table->enum('basis', ['KG_AKTUAL', 'KG_VOLUMETRIK', 'KG_TERBESAR', 'M3', 'UNIT']);
            $table->decimal('tarif_per_satuan_per_hari', 15, 2);
            $table->decimal('faktor_volumetrik_kg_per_m3', 10, 2)->default(250);
            $table->decimal('ppn_persen', 5, 2)->default(11);
            $table->unsignedInteger('min_hari_simpan')->default(0);
            $table->unsignedInteger('pembulatan_rupiah')->nullable()
                ->comment('Bulatkan subtotal per item ke kelipatan Rp ini, null = tanpa pembulatan');
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->timestamps();

            $table->index(['gudang_id', 'berlaku_mulai', 'berlaku_sampai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_sewas');
    }
};
