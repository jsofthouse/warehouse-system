<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice = 1 per surat jalan, final ("04-invoice-sewa-gudang.md" §0 keputusan
 * #5). Kolom ini tidak ada di skeleton awal — ditambah sekarang karena tanpa
 * ini tidak ada cara mengecek "surat jalan ini sudah ada invoicenya belum".
 * `unique()` menjamin satu surat jalan cuma bisa py satu invoice di level
 * database, bukan cuma di level aplikasi.
 *
 * Tabel `invoice` sendiri masih kosong (belum ada mesin buat isinya sebelum
 * eksekusi ini), jadi aman ditambah NOT NULL langsung tanpa migrasi data.
 *
 * Pihak tertagih pindah dari rencana tabel master `pihak_tertagih` (lihat
 * migration drop-nya) jadi snapshot kolom langsung di sini, diisi manual per
 * invoice oleh operator — pola yang sama dengan snapshot di `invoice_detail`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->foreignId('surat_jalan_id')->after('gudang_id')
                ->unique()
                ->constrained('surat_jalan')
                ->restrictOnDelete();

            $table->string('nama_tertagih')->nullable()->after('keterangan');
            $table->text('alamat_tertagih')->nullable()->after('nama_tertagih');
            $table->string('npwp_tertagih', 30)->nullable()->after('alamat_tertagih');
        });
    }

    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropForeign(['surat_jalan_id']);
            $table->dropColumn(['surat_jalan_id', 'nama_tertagih', 'alamat_tertagih', 'npwp_tertagih']);
        });
    }
};
