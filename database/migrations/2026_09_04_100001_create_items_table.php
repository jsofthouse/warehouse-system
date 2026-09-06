<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // item.berat_kg sengaja nullable — data berat dari klien belum tersedia
        // (lihat docs "Modul Invoice Sewa Gudang" §1). Sistem tetap bisa jalan
        // pakai basis volumetrik/m3 sampai data berat masuk.
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->string('satuan', 20)->default('unit');
            $table->decimal('berat_kg', 10, 3)->nullable();
            $table->decimal('panjang_cm', 10, 2)->nullable();
            $table->decimal('lebar_cm', 10, 2)->nullable();
            $table->decimal('tinggi_cm', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
