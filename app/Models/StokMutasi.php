<?php

namespace App\Models;

use App\Enums\TipeMutasiStok;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StokMutasi extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'stok_mutasi';

    // Ledger append-only: nggak ada updated_at, dan lewat kode nggak pernah di-update/delete.
    const UPDATED_AT = null;

    protected $fillable = [
        'gudang_id',
        'item_id',
        'tanggal',
        'tipe',
        'jumlah',
        'referensi_type',
        'referensi_id',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tipe' => TipeMutasiStok::class,
        'jumlah' => 'integer',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function referensi()
    {
        return $this->morphTo();
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Stok on-hand = SUM(IN) - SUM(OUT), dihitung langsung dari ledger — tidak
     * pernah dari kolom stok yang di-cache (CLAUDE.md §5.1). Dipakai bareng oleh
     * Kartu Stok (tanpa $sampaiTanggal = posisi sekarang) dan command
     * `stok:hitung-harian` (dengan $sampaiTanggal = akhir hari yang sedang
     * dihitung) — lihat "Modul Kartu Stok dan Stok Harian.md" §3.3.
     *
     * Dihitung independen dari nol tiap kali dipanggil, bukan berantai dari
     * saldo hari sebelumnya, supaya satu hari yang salah/dilewat tidak
     * menjalar ke hari-hari sesudahnya (§2 prinsip 3 dokumen yang sama).
     */
    public static function saldoAkhir(int $gudangId, int $itemId, ?Carbon $sampaiTanggal = null): int
    {
        $query = static::query()
            ->where('gudang_id', $gudangId)
            ->where('item_id', $itemId);

        if ($sampaiTanggal !== null) {
            $query->whereDate('tanggal', '<=', $sampaiTanggal->toDateString());
        }

        $masuk = (clone $query)->where('tipe', TipeMutasiStok::In)->sum('jumlah');
        $keluar = (clone $query)->where('tipe', TipeMutasiStok::Out)->sum('jumlah');

        return (int) $masuk - (int) $keluar;
    }
}
