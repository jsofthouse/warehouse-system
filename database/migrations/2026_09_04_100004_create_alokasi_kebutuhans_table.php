<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_kebutuhans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lokasi_id')->constrained('lokasis')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('jumlah_kebutuhan')->default(0);
            $table->timestamps();

            // Satu lokasi cuma boleh punya satu baris alokasi per item.
            $table->unique(['lokasi_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_kebutuhans');
    }
};
