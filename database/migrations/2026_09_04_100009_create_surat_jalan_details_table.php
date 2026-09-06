<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_jalan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_jalan_id')->constrained('surat_jalans')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('jumlah_kirim');
            $table->timestamps();

            $table->unique(['surat_jalan_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_jalan_details');
    }
};
