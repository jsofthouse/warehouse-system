<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaans')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['penerimaan_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_details');
    }
};
