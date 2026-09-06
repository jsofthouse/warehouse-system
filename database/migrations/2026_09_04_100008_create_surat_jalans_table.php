<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_jalan', function (Blueprint $table) {
            $table->id();

            // Nomor resmi baru terbit saat posting — draft belum bernomor.
            $table->string('nomor_surat_jalan', 40)->nullable()->unique();

            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('lokasi_id')->constrained('lokasi')->restrictOnDelete();
            $table->date('tanggal');

            $table->string('ekspedisi')->nullable();
            $table->string('nomor_polisi', 20)->nullable();
            $table->string('nama_sopir')->nullable();

            // draft -> posted -> diterima. dibatalkan hanya dari posted (bukan dari diterima).
            $table->enum('status', ['draft', 'posted', 'diterima', 'dibatalkan'])->default('draft');

            $table->date('tanggal_terima')->nullable();
            $table->string('nomor_bast', 40)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();

            $table->timestamps();

            $table->index(['gudang_id', 'tanggal']);
            $table->index(['lokasi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_jalan');
    }
};
