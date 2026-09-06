<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_penerimaan', 40)->nullable()->unique();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('vendor_nama');
            $table->string('nomor_dokumen_vendor')->nullable();
            $table->text('keterangan')->nullable();

            // draft = masih bisa diedit bebas, belum ada mutasi stok.
            // posted = mutasi stok IN sudah tertulis, dokumen terkunci.
            $table->enum('status', ['draft', 'posted'])->default('draft');

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->index(['gudang_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan');
    }
};
