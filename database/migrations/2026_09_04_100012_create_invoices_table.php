<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_invoice', 40)->nullable()->unique();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('periode_mulai');
            $table->date('periode_selesai');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('ppn_persen', 5, 2)->default(0);
            $table->decimal('ppn_nominal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // draft -> terbit -> terbayar. dibatalkan hanya dari terbit (lihat
            // "Modul Invoice Sewa Gudang" §6.4 — pembatalan wajib alasan & role Pusat+).
            $table->enum('status', ['draft', 'terbit', 'terbayar', 'dibatalkan'])->default('draft');
            $table->date('tanggal_bayar')->nullable();
            $table->text('keterangan')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();

            $table->timestamps();

            $table->index(['gudang_id', 'periode_mulai', 'periode_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};
