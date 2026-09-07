<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak FIFO: "qty sekian dari batch (penerimaan_detail) ini dipakai buat
 * kirim surat jalan detail ini" ("04-invoice-sewa-gudang.md" §3a). Ditulis
 * otomatis saat surat jalan diposting (App\Support\AlokasiFifoBatch), dipakai
 * lagi buat menghitung draft invoice (App\Support\PerhitunganBiayaSewa).
 *
 * Ledger append-only sama seperti StokMutasi — tidak pernah diubah/dihapus
 * lewat kode.
 */
class AlokasiBatchKeluar extends Model
{
    // Nama tabel tunggal bahasa Indonesia, bukan jamak ala Laravel
    // (CLAUDE.md §9). Konvensi otomatis Eloquent tidak cocok, jadi
    // tabelnya dinyatakan eksplisit.
    protected $table = 'alokasi_batch_keluar';

    protected $fillable = [
        'penerimaan_detail_id',
        'surat_jalan_detail_id',
        'qty_dialokasikan',
    ];

    protected $casts = [
        'qty_dialokasikan' => 'integer',
    ];

    public function penerimaanDetail()
    {
        return $this->belongsTo(PenerimaanDetail::class);
    }

    public function suratJalanDetail()
    {
        return $this->belongsTo(SuratJalanDetail::class);
    }
}
