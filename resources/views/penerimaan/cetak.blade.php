{{--
    Template cetak dibuat terpisah dari layout aplikasi dan TIDAK memakai grid
    Bootstrap: DomPDF tidak mendukung flexbox/grid dan mengabaikannya diam-diam.
    Cuma tabel HTML sederhana dan CSS dasar ("07-panduan-frontend.md" §8).
--}}
@php
    $bulanIndonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $tanggalPanjang = fn($t) => $t
        ? $t->day . ' ' . $bulanIndonesia[(int) $t->month] . ' ' . $t->year
        : '—';

    $dibatalkan = $penerimaan->status === App\Enums\StatusPenerimaan::Dibatalkan;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Bukti Penerimaan Barang {{ $penerimaan->nomor_penerimaan }}</title>
    <style>
        @page {
            margin: 18mm 15mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000;
        }

        h1 {
            font-size: 13pt;
            margin: 0 0 2mm;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .subjudul {
            text-align: center;
            font-size: 9pt;
            margin: 0 0 6mm;
        }

        .kop {
            border-bottom: 1.5pt solid #000;
            padding-bottom: 3mm;
            margin-bottom: 5mm;
        }

        .kop .nama-gudang {
            font-size: 12pt;
            font-weight: bold;
        }

        .kop .alamat {
            font-size: 8.5pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.header td {
            padding: 0.8mm 0;
            vertical-align: top;
            font-size: 9.5pt;
        }

        table.header td.label {
            width: 32mm;
            color: #333;
        }

        table.header td.pemisah {
            width: 4mm;
        }

        table.barang {
            margin-top: 4mm;
            border: 0.5pt solid #000;
        }

        table.barang th,
        table.barang td {
            border: 0.5pt solid #000;
            padding: 1.6mm 2mm;
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
            margin-top: 4mm;
            font-size: 9pt;
        }

        .ttd {
            margin-top: 10mm;
            page-break-inside: avoid;
        }

        .ttd td {
            width: 33%;
            text-align: center;
            font-size: 9.5pt;
            vertical-align: top;
        }

        .ttd .ruang {
            height: 22mm;
        }

        .ttd .garis {
            border-top: 0.5pt solid #000;
            padding-top: 1mm;
        }

        .kaki {
            margin-top: 8mm;
            font-size: 8pt;
            color: #444;
            border-top: 0.5pt solid #999;
            padding-top: 2mm;
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
            margin-top: 4mm;
            font-size: 9pt;
        }
    </style>
</head>

<body>
    @if ($dibatalkan)
        <div class="cap-batal">DIBATALKAN</div>
    @endif

    <div class="kop">
        <div class="nama-gudang">{{ $penerimaan->gudang->nama }}</div>
        <div class="alamat">
            {{ $penerimaan->gudang->alamat ?: $penerimaan->gudang->kota }}
        </div>
    </div>

    <h1>Bukti Penerimaan Barang</h1>
    <div class="subjudul">Nomor: {{ $penerimaan->nomor_penerimaan }}</div>

    <table class="header">
        <tr>
            <td class="label">Tanggal Terima</td>
            <td class="pemisah">:</td>
            <td>{{ $tanggalPanjang($penerimaan->tanggal) }}</td>

            <td class="label">Vendor</td>
            <td class="pemisah">:</td>
            <td>{{ $penerimaan->vendor_nama }}</td>
        </tr>
        <tr>
            <td class="label">Gudang</td>
            <td class="pemisah">:</td>
            <td>{{ $penerimaan->gudang->nama }} ({{ $penerimaan->gudang->kode }})</td>

            <td class="label">Surat Jalan Vendor</td>
            <td class="pemisah">:</td>
            <td>{{ $penerimaan->nomor_dokumen_vendor ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="pemisah">:</td>
            <td>{{ $penerimaan->status->label() }}</td>

            <td class="label">Kontrak / Referensi</td>
            <td class="pemisah">:</td>
            <td>{{ $penerimaan->no_kontrak_referensi ?: '—' }}</td>
        </tr>
    </table>

    <table class="barang">
        <thead>
            <tr>
                <th class="tengah" style="width: 8mm">No</th>
                <th style="width: 22mm">Kode</th>
                <th>Nama Barang</th>
                <th class="angka" style="width: 20mm">Jumlah</th>
                <th style="width: 18mm">Satuan</th>
                <th style="width: 35mm">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($penerimaan->details as $baris => $detail)
                <tr>
                    <td class="tengah">{{ $baris + 1 }}</td>
                    <td>{{ $detail->item?->kode ?? '—' }}</td>
                    <td>{{ $detail->item?->nama ?? 'Item tidak ditemukan' }}</td>
                    <td class="angka">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                    <td>{{ $detail->item?->satuan ?? '—' }}</td>
                    <td>{{ $detail->keterangan ?: '' }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="3" class="angka">Total</th>
                <th class="angka">{{ number_format($penerimaan->totalUnit(), 0, ',', '.') }}</th>
                <th colspan="2">unit</th>
            </tr>
        </tbody>
    </table>

    @if ($penerimaan->keterangan)
        <div class="catatan"><strong>Keterangan:</strong> {{ $penerimaan->keterangan }}</div>
    @endif

    @if ($dibatalkan)
        <div class="kotak-batal">
            <strong>Dokumen ini dibatalkan</strong> pada
            {{ $tanggalPanjang($penerimaan->dibatalkan_at) }}
            oleh {{ $penerimaan->pembatal?->name ?? '—' }}.
            Alasan: {{ $penerimaan->alasan_pembatalan }}.
            Stok sudah dikembalikan lewat mutasi balik.
        </div>
    @endif

    <table class="ttd">
        <tr>
            <td>Diserahkan oleh,<br>Vendor</td>
            <td>Diterima oleh,<br>Petugas Gudang</td>
            <td>Mengetahui,<br>Kepala Gudang</td>
        </tr>
        <tr>
            <td class="ruang"></td>
            <td class="ruang"></td>
            <td class="ruang"></td>
        </tr>
        <tr>
            <td class="garis">{{ $penerimaan->vendor_nama }}</td>
            <td class="garis">{{ $penerimaan->poster?->name ?? $penerimaan->pembuat?->name ?? '' }}</td>
            <td class="garis">&nbsp;</td>
        </tr>
    </table>

    <div class="kaki">
        Dicetak {{ $tanggalPanjang(now()) }} dari Sistem Gudang &amp; Distribusi Alkap Pertanian.
        Dokumen diposting {{ $tanggalPanjang($penerimaan->posted_at) }}
        oleh {{ $penerimaan->poster?->name ?? '—' }}.
    </div>
</body>

</html>
