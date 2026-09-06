<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaan')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('item')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['penerimaan_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_detail');
    }
};
