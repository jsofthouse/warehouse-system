<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot stok akhir hari, ditulis scheduler tiap malam dari stok_mutasis.
        // Idempoten: hitung ulang tanggal yang sama harus overwrite baris ini, bukan
        // menambah baris baru — makanya unique (gudang_id, item_id, tanggal).
        Schema::create('stok_harians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->date('tanggal');
            $table->unsignedInteger('stok_akhir');
            $table->timestamps();

            $table->unique(['gudang_id', 'item_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_harians');
    }
};
