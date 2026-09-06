<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();

            // Snapshot di titik posting — supaya invoice lama tetap bisa direproduksi
            // persis walau tarif/basis master berubah belakangan.
            $table->string('basis', 20);
            $table->decimal('total_unit_hari', 12, 2);
            $table->decimal('satuan_per_unit', 12, 4)
                ->comment('berat_kg atau volume_m3 (atau 1 utk basis UNIT) yang dipakai saat posting');
            $table->decimal('total_satuan_hari', 15, 4);
            $table->decimal('tarif_per_satuan_per_hari', 15, 2);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->unique(['invoice_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
