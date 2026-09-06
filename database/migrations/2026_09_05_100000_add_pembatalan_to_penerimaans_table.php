<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penerimaan menyusul pola pembatalan yang sudah dipakai `surat_jalan`:
 * dokumen ter-posting tidak pernah dihapus, cuma ditandai `dibatalkan` dan
 * dikoreksi lewat mutasi balik ("03-aturan-bisnis.md" §1.3).
 */
return new class extends Migration
{
    /** Nilai enum status setelah migrasi ini. */
    private const STATUS_BARU = ['draft', 'posted', 'dibatalkan'];

    private const STATUS_LAMA = ['draft', 'posted'];

    public function up(): void
    {
        Schema::table('penerimaan', function (Blueprint $table) {
            // Nomor kontrak/PO dari pihak pemberi kerja — beda dari nomor surat
            // jalan vendor yang sudah ada di `nomor_dokumen_vendor`.
            $table->string('no_kontrak_referensi')->nullable();

            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
        });

        $this->ubahEnumStatus(self::STATUS_BARU);
    }

    public function down(): void
    {
        // Balikin enum dulu, baru buang kolomnya — baris yang terlanjur
        // 'dibatalkan' dikembalikan ke 'posted' supaya tidak menabrak enum lama.
        DB::table('penerimaan')->where('status', 'dibatalkan')->update(['status' => 'posted']);

        $this->ubahEnumStatus(self::STATUS_LAMA);

        Schema::table('penerimaan', function (Blueprint $table) {
            $table->dropForeign(['dibatalkan_by']);
            $table->dropColumn(['no_kontrak_referensi', 'dibatalkan_by', 'dibatalkan_at', 'alasan_pembatalan']);
        });
    }

    /**
     * Cara memperlebar enum beda per driver, jadi jangan diasumsikan MySQL.
     * Driver aktif diambil dari koneksi yang benar-benar dipakai (lihat
     * config/database.php: MySQL di dev/produksi, SQLite in-memory di test).
     *
     * @param  array<int, string>  $nilai
     */
    private function ubahEnumStatus(array $nilai): void
    {
        $koneksi = Schema::getConnection();
        $daftar = "'".implode("','", $nilai)."'";

        match ($koneksi->getDriverName()) {
            'mysql', 'mariadb' => $koneksi->statement(
                "ALTER TABLE `penerimaan` MODIFY `status` ENUM({$daftar}) NOT NULL DEFAULT 'draft'"
            ),

            // Laravel bikin enum di Postgres sebagai CHECK constraint bernama
            // <tabel>_<kolom>_check, bukan tipe ENUM native.
            'pgsql' => tap($koneksi, function ($c) use ($daftar) {
                $c->statement('ALTER TABLE "penerimaan" DROP CONSTRAINT IF EXISTS "penerimaan_status_check"');
                $c->statement("ALTER TABLE \"penerimaan\" ADD CONSTRAINT \"penerimaan_status_check\" CHECK (\"status\"::text = ANY (ARRAY[{$daftar}]::text[]))");
            }),

            // SQLite tidak bisa mengubah CHECK constraint lewat ALTER TABLE.
            // ->change() di sini yang bikin Laravel menyusun ulang tabelnya
            // (buat tabel sementara, salin isi, rename) — itu satu-satunya jalan
            // yang benar di SQLite.
            default => Schema::table('penerimaan', function (Blueprint $table) use ($nilai) {
                $table->enum('status', $nilai)->default('draft')->change();
            }),
        };
    }
};
