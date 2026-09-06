<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger append-only: sekali ditulis (saat posting), tidak pernah diubah.
        // Ini sumber kebenaran buat kartu stok DAN snapshot stok_harian, sekaligus
        // dasar rebuild FIFO nanti kalau suatu saat dibutuhkan (lihat "Modul
        // Invoice Sewa Gudang" §3).
        Schema::create('stok_mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->date('tanggal');
            $table->enum('tipe', ['IN', 'OUT']);
            $table->unsignedInteger('jumlah');

            // Polymorphic: referensi_type + referensi_id -> Penerimaan atau SuratJalan
            // yang jadi sumber mutasi ini.
            $table->nullableMorphs('referensi');

            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gudang_id', 'item_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mutasis');
    }
};
