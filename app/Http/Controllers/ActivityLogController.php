<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Layar Activity Log — khusus Super Admin (route digerbang middleware
 * role:super_admin, lihat routes/web.php). Tabel activity_log lintas gudang
 * (tidak ada kolom gudang_id), jadi tidak perlu scoping per gudang — cukup
 * satu gate role di route (docs/12-modul-activity-log.md §2 keputusan #1).
 */
class ActivityLogController extends Controller
{
    /**
     * Aksi yang sudah dipakai di seluruh controller (lihat
     * docs/12-modul-activity-log.md §3.3) — dipakai buat whitelist checkbox
     * filter "Jenis Aksi", bukan buat validasi tulis (penulisan log tidak
     * dibatasi ke daftar ini).
     */
    private const DAFTAR_AKSI = [
        'create', 'update', 'delete', 'post', 'cancel', 'terima',
        'hitung_ulang_stok_harian', 'login', 'login_gagal',
    ];

    /** Sentinel query-string buat filter "Tanpa dokumen tertentu" (subjek_type null). */
    private const TANPA_DOKUMEN = '_tanpa_dokumen';

    public function index(Request $request): View
    {
        // Alias morph map yang valid saat ini (docs/12 §3.2) — whitelist ini
        // dipakai supaya subjek_type dari query string tidak pernah dipakai
        // mentah di where() (docs/12 §5.2 / CLAUDE.md §10 poin 2).
        $aliasDokumenValid = array_keys(Relation::morphMap());

        $dari = $request->query('dari');
        $sampai = $request->query('sampai');
        $userId = $request->query('user_id');
        $daftarAksiDipilih = array_values(array_intersect(
            (array) $request->query('aksi', []),
            self::DAFTAR_AKSI
        ));
        $subjekTypeMentah = $request->query('subjek_type');
        $subjekType = in_array($subjekTypeMentah, [...$aliasDokumenValid, self::TANPA_DOKUMEN], true)
            ? $subjekTypeMentah
            : null;
        $subjekId = $request->query('subjek_id');
        $ip = trim((string) $request->query('ip', ''));

        // Default rentang tanggal "hari ini" cuma buat kunjungan polos ke
        // halaman ini (tanpa filter apapun) — bukan sewaktu datang lewat link
        // "Riwayat" (subjek_type+subjek_id terisi tapi dari/sampai kosong),
        // supaya link itu tetap menampilkan seluruh histori dokumennya, bukan
        // cuma aktivitas hari ini. Detail kecil, dicatat di "Catatan eksekusi".
        if ($request->query() === []) {
            $dari = $sampai = today()->toDateString();
        }

        $daftarActivityLog = ActivityLog::query()
            ->with(['user', 'subjek'])
            ->when($dari, fn ($q) => $q->whereDate('created_at', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('created_at', '<=', $sampai))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($daftarAksiDipilih !== [], fn ($q) => $q->whereIn('aksi', $daftarAksiDipilih))
            ->when($subjekType === self::TANPA_DOKUMEN, fn ($q) => $q->whereNull('subjek_type'))
            ->when($subjekType !== null && $subjekType !== self::TANPA_DOKUMEN, fn ($q) => $q->where('subjek_type', $subjekType))
            ->when($subjekId, fn ($q) => $q->where('subjek_id', $subjekId))
            ->when($ip !== '', function ($q) use ($ip) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $ip).'%';
                $q->where('ip_address', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $daftarUser = User::orderBy('name')->get(['id', 'name', 'email']);

        $labelDokumen = $this->labelDokumenPerAlias();
        $labelAksi = $this->labelAksi();
        $badgeAksi = $this->badgeAksi();

        $filter = [
            'dari' => $dari,
            'sampai' => $sampai,
            'user_id' => $userId,
            'aksi' => $daftarAksiDipilih,
            'subjek_type' => $subjekType,
            'subjek_id' => $subjekId,
            'ip' => $ip,
        ];

        return view('activity-log.index', compact(
            'daftarActivityLog', 'daftarUser', 'labelDokumen', 'labelAksi', 'badgeAksi', 'filter'
        ));
    }

    /** Label Indonesia per alias morph map, buat dropdown filter "Jenis Dokumen". */
    private function labelDokumenPerAlias(): array
    {
        return [
            'gudang' => 'Gudang',
            'item' => 'Item',
            'lokasi' => 'Lokasi',
            'tarif_sewa' => 'Tarif Sewa',
            'user' => 'User',
            'penerimaan' => 'Penerimaan',
            'surat_jalan' => 'Surat Jalan',
        ];
    }

    /** Label Indonesia per nilai kolom aksi, buat checkbox filter dan kolom tabel. */
    private function labelAksi(): array
    {
        return [
            'create' => 'Buat',
            'update' => 'Ubah',
            'delete' => 'Hapus',
            'post' => 'Posting',
            'cancel' => 'Batalkan',
            'terima' => 'Terima',
            'hitung_ulang_stok_harian' => 'Hitung Ulang Stok Harian',
            'login' => 'Login',
            'login_gagal' => 'Login Gagal',
        ];
    }

    /** Warna badge Tabler per nilai kolom aksi (docs/12 §4.2 poin 3). */
    private function badgeAksi(): array
    {
        return [
            'create' => 'bg-green-lt',
            'update' => 'bg-azure-lt',
            'delete' => 'bg-red-lt',
            'post' => 'bg-blue-lt',
            'cancel' => 'bg-orange-lt',
            'terima' => 'bg-teal-lt',
            'hitung_ulang_stok_harian' => 'bg-secondary-lt',
            'login' => 'bg-lime-lt',
            'login_gagal' => 'bg-red-lt',
        ];
    }
}
