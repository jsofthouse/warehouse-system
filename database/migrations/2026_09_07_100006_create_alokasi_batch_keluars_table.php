<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak FIFO: "qty sekian dari batch (penerimaan_detail) ini dipakai buat
 * kirim surat jalan detail ini" ("04-invoice-sewa-gudang.md" §3a.2). Ditulis
 * otomatis saat surat jalan diposting.
 *
 * Ledger append-only sama seperti `stok_mutasi` — tidak pernah diubah atau
 * dihapus, termasuk saat dokumen sumbernya (penerimaan/surat jalan) belakangan
 * dibatalkan. Sengaja TIDAK menyimpan hari-simpan atau biaya di sini — keduanya
 * selalu dihitung ulang dari tanggal dokumen + tarif yang berlaku, supaya
 * angkanya otomatis ikut kalau item.berat_kg diperbaiki belakangan buat
 * invoice yang belum dibuat (lihat §3a.2 dokumen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_batch_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_detail_id')->constrained('penerimaan_detail')->restrictOnDelete();
            $table->foreignId('surat_jalan_detail_id')->constrained('surat_jalan_detail')->restrictOnDelete();
            $table->unsignedInteger('qty_dialokasikan');
            $table->timestamps();

            $table->index(['penerimaan_detail_id']);
            $table->index(['surat_jalan_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_batch_keluar');
    }
};
