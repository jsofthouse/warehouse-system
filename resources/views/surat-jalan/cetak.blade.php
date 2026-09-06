{{--
    Template cetak dibuat terpisah dari layout aplikasi dan TIDAK memakai grid
    Bootstrap: DomPDF tidak mendukung flexbox/grid dan mengabaikannya diam-diam.
    Cuma tabel HTML sederhana dan CSS dasar ("07-panduan-frontend.md" §8).

    Layout mengikuti "05-format-dokumen.md" §1: kop, blok dikirim dari/kepada,
    tabel item, blok tanda tangan pengirim/sopir/penerima, dan baris
    "Pengiriman ke-n dari total" untuk lokasi tujuan.

    Tiga rangkap dicetak sebagai tiga halaman identik dengan penanda rangkap
    berbeda — pengirim, sopir, penerima.
--}}
@php
    $bulanIndonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $tanggalPanjang = fn($t) => $t
        ? $t->day . ' ' . $bulanIndonesia[(int) $t->month] . ' ' . $t->year
        : '—';

    $dibatalkan = $suratJalan->status === App\Enums\StatusSuratJalan::Dibatalkan;

    $alamatLokasi = collect([
        $suratJalan->lokasi?->desa ? 'Desa ' . $suratJalan->lokasi->desa : null,
        $suratJalan->lokasi?->kecamatan ? 'Kec. ' . $suratJalan->lokasi->kecamatan : null,
    ])->filter()->implode(', ');

    $wilayahLokasi = collect([
        $suratJalan->lokasi?->kabupaten ? 'Kab. ' . $suratJalan->lokasi->kabupaten : null,
        $suratJalan->lokasi?->provinsi,
    ])->filter()->implode(', ');

    $rangkapan = ['Pengirim (Gudang)', 'Sopir / Ekspedisi', 'Penerima (Lokasi)'];
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Jalan {{ $suratJalan->nomor_surat_jalan }}</title>
    <style>
        @page {
            margin: 15mm 14mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000;
        }

        h1 {
            font-size: 14pt;
            margin: 0 0 1mm;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }

        .subjudul {
            text-align: center;
            font-size: 10pt;
            margin: 0 0 5mm;
        }

        .kop {
            border-bottom: 1.5pt solid #000;
            padding-bottom: 3mm;
            margin-bottom: 4mm;
        }

        .kop .nama-gudang {
            font-size: 12pt;
            font-weight: bold;
        }

        .kop .alamat {
            font-size: 8.5pt;
        }

        .kop .rangkap {
            float: right;
            font-size: 8.5pt;
            border: 0.5pt solid #000;
            padding: 1mm 2.5mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.tujuan {
            border: 0.5pt solid #000;
            margin-bottom: 3mm;
        }

        table.tujuan td {
            border: 0.5pt solid #000;
            padding: 2mm 2.5mm;
            width: 50%;
            vertical-align: top;
            font-size: 9.5pt;
        }

        table.tujuan .judul-kolom {
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
            margin-bottom: 1mm;
        }

        table.header td {
            padding: 0.7mm 0;
            vertical-align: top;
            font-size: 9.5pt;
        }

        table.header td.label {
            width: 28mm;
            color: #333;
        }

        table.header td.pemisah {
            width: 4mm;
        }

        table.barang {
            margin-top: 3mm;
            border: 0.5pt solid #000;
        }

        table.barang th,
        table.barang td {
            border: 0.5pt solid #000;
            padding: 1.5mm 2mm;
            font-size: 9.5pt;
        }

        table.barang thead {
            /* Header tabel wajib ikut berulang tiap halaman. */
            display: table-header-group;
        }

        table.barang th {
            background-color: #eee;
            text-align: left;
        }

        .angka {
            text-align: right;
        }

        .tengah {
            text-align: center;
        }

        .catatan {
            margin-top: 3mm;
            font-size: 9pt;
            border: 0.5pt solid #999;
            padding: 2mm;
        }

        .ttd {
            margin-top: 6mm;
            page-break-inside: avoid;
        }

        .ttd td {
            width: 33%;
            text-align: center;
            font-size: 9.5pt;
            vertical-align: top;
        }

        .ttd .ruang {
            height: 20mm;
        }

        .ttd .garis {
            border-top: 0.5pt solid #000;
            padding-top: 1mm;
        }

        .ttd .keterangan {
            font-size: 8pt;
            color: #444;
        }

        .kaki {
            margin-top: 5mm;
            font-size: 8pt;
            color: #444;
            border-top: 0.5pt solid #999;
            padding-top: 1.5mm;
        }

        .cap-batal {
            position: fixed;
            top: 95mm;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 54pt;
            font-weight: bold;
            color: #d63939;
            opacity: 0.22;
            transform: rotate(-24deg);
            z-index: 1000;
        }

        .kotak-batal {
            border: 1pt solid #d63939;
            color: #d63939;
            padding: 2mm 3mm;
            margin-top: 3mm;
            font-size: 9pt;
        }

        .halaman-baru {
            page-break-before: always;
        }
    </style>
</head>

<body>
    @if ($dibatalkan)
        <div class="cap-batal">DIBATALKAN</div>
    @endif

    @foreach ($rangkapan as $nomorRangkap => $namaRangkap)
        <div class="{{ $nomorRangkap > 0 ? 'halaman-baru' : '' }}">

            <div class="kop">
                <div class="rangkap">Rangkap {{ $nomorRangkap + 1 }}/3 · {{ $namaRangkap }}</div>
                <div class="nama-gudang">{{ $suratJalan->gudang->nama }}</div>
                <div class="alamat">{{ $suratJalan->gudang->alamat ?: $suratJalan->gudang->kota }}</div>
            </div>

            <h1>Surat Jalan</h1>
            <div class="subjudul">No. {{ $suratJalan->nomor_surat_jalan }}</div>

            <table class="tujuan">
                <tr>
                    <td>
                        <div class="judul-kolom">Dikirim dari</div>
                        {{ $suratJalan->gudang->nama }}<br>
                        {{ $suratJalan->gudang->alamat ?: $suratJalan->gudang->kota }}
                    </td>
                    <td>
                        <div class="judul-kolom">Dikirim kepada</div>
                        {{ $suratJalan->lokasi?->nama_kodim ?? '—' }}<br>
                        {{ $alamatLokasi ?: '—' }}<br>
                        {{ $wilayahLokasi }}
                    </td>
                </tr>
            </table>

            <table class="header">
                <tr>
                    <td class="label">Tanggal</td>
                    <td class="pemisah">:</td>
                    <td>{{ $tanggalPanjang($suratJalan->tanggal) }}</td>
                </tr>
                <tr>
                    <td class="label">Ekspedisi</td>
                    <td class="pemisah">:</td>
                    <td>{{ $suratJalan->ekspedisi ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Kendaraan</td>
                    <td class="pemisah">:</td>
                    <td>{{ $suratJalan->nomor_polisi ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Sopir</td>
                    <td class="pemisah">:</td>
                    <td>{{ $suratJalan->nama_sopir ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Keterangan</td>
                    <td class="pemisah">:</td>
                    <td>
                        @if ($pengiriman['ke'])
                            Pengiriman ke-{{ $pengiriman['ke'] }} dari {{ $pengiriman['dari'] }} untuk lokasi ini
                        @else
                            Dokumen dibatalkan — tidak dihitung sebagai pengiriman ke lokasi ini
                        @endif
                    </td>
                </tr>
            </table>

            <table class="barang">
                <thead>
                    <tr>
                        <th class="tengah" style="width: 8mm">No</th>
                        <th>Nama Barang</th>
                        <th style="width: 18mm">Satuan</th>
                        <th class="angka" style="width: 20mm">Jumlah</th>
                        <th style="width: 32mm">Ket.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suratJalan->detail as $baris => $detail)
                        <tr>
                            <td class="tengah">{{ $baris + 1 }}</td>
                            <td>{{ $detail->item?->nama ?? 'Item tidak ditemukan' }}</td>
                            <td>{{ $detail->item?->satuan ?? '—' }}</td>
                            <td class="angka">{{ number_format($detail->jumlah_kirim, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    <tr>
                        <th colspan="3" class="angka">Total Unit</th>
                        <th class="angka">{{ number_format($suratJalan->totalUnit(), 0, ',', '.') }}</th>
                        <th></th>
                    </tr>
                </tbody>
            </table>

            <div class="catatan">
                <strong>Catatan:</strong> Barang diterima dalam keadaan baik dan sesuai jumlah tersebut di atas.
            </div>

            @if ($dibatalkan)
                <div class="kotak-batal">
                    <strong>Dokumen ini dibatalkan</strong> pada
                    {{ $tanggalPanjang($suratJalan->dibatalkan_at) }}
                    oleh {{ $suratJalan->pembatal?->name ?? '—' }}.
                    Alasan: {{ $suratJalan->alasan_pembatalan }}.
                    Stok sudah dikembalikan lewat mutasi balik.
                </div>
            @endif

            <table class="ttd">
                <tr>
                    <td>Pengirim,<br>Petugas Gudang</td>
                    <td>Sopir,</td>
                    <td>Penerima,</td>
                </tr>
                <tr>
                    <td class="ruang"></td>
                    <td class="ruang"></td>
                    <td class="ruang"></td>
                </tr>
                <tr>
                    <td class="garis">{{ $suratJalan->poster?->name ?? $suratJalan->pembuat?->name ?? '' }}</td>
                    <td class="garis">{{ $suratJalan->nama_sopir ?: '' }}</td>
                    <td class="garis">
                        {{ $suratJalan->nama_penerima ?: '' }}
                        <div class="keterangan">Nama jelas &amp; stempel</div>
                    </td>
                </tr>
            </table>

            <div class="kaki">
                {{ $suratJalan->nomor_surat_jalan }} ·
                Dicetak {{ now()->format('d/m/Y H:i') }} ·
                Rangkap {{ $nomorRangkap + 1 }} dari 3 ({{ $namaRangkap }})
                @if ($suratJalan->tanggal_terima)
                    · Diterima {{ $tanggalPanjang($suratJalan->tanggal_terima) }}
                    oleh {{ $suratJalan->nama_penerima ?? '—' }}
                @endif
            </div>
        </div>
    @endforeach
</body>

</html>
