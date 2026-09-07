{{--
    Template cetak dibuat terpisah dari layout aplikasi dan TIDAK memakai grid
    Bootstrap: DomPDF tidak mendukung flexbox/grid dan mengabaikannya diam-diam.
    Cuma tabel HTML sederhana dan CSS dasar ("07-panduan-frontend.md" §8).

    Layout mengikuti "05-format-dokumen.md" §2. Baris "Jatuh tempo" di layout
    aslinya SENGAJA tidak dipakai — tidak ada kolom untuk itu di skema
    (lihat "04-invoice-sewa-gudang.md" §0 keputusan #3, tidak ada status
    pembayaran di sistem ini).

    Identitas "Kepada" (pihak tertagih) masih placeholder murni — belum ada
    relasi ke tabel pihak_tertagih, lihat "04-invoice-sewa-gudang.md" §10.

    Rincian per item ($invoice->detail) kemungkinan besar masih kosong sampai
    mesin hitung invoice sungguhan dibangun — lihat §0 blocker (a)/(b). Template
    ini cuma menyiapkan layoutnya.
--}}
@php
    $bulanIndonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $tanggalPanjang = fn($t) => $t
        ? $t->day . ' ' . $bulanIndonesia[(int) $t->month] . ' ' . $t->year
        : '—';

    $dibatalkan = $invoice->status === App\Enums\StatusInvoice::Dibatalkan;

    $basisContoh = $invoice->detail->first()?->basis;

    $labelSatuan = match ($basisContoh) {
        App\Enums\BasisTarif::M3 => 'm³',
        App\Enums\BasisTarif::Unit => 'unit',
        default => 'kg',
    };

    $tarifContoh = $invoice->detail->first()?->tarif_per_satuan_per_hari;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->nomor_invoice ?? '(draft)' }}</title>
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

        .kop .nama-penagih {
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

        table.info {
            margin-bottom: 3mm;
        }

        table.info td {
            vertical-align: top;
            width: 50%;
            font-size: 9.5pt;
        }

        table.info .judul-kolom {
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

        .catatan-tarif {
            margin: 3mm 0;
            font-size: 9pt;
            border: 0.5pt solid #999;
            padding: 2mm;
        }

        table.barang {
            margin-top: 3mm;
            border: 0.5pt solid #000;
        }

        table.barang th,
        table.barang td {
            border: 0.5pt solid #000;
            padding: 1.5mm 2mm;
            font-size: 9pt;
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

        table.ringkasan {
            margin-top: 0;
        }

        table.ringkasan td {
            border: 0.5pt solid #000;
            border-top: none;
            padding: 1.5mm 2mm;
            font-size: 9.5pt;
        }

        table.ringkasan tr.total td {
            font-weight: bold;
        }

        .terbilang {
            margin-top: 3mm;
            font-size: 9pt;
            font-style: italic;
        }

        .ttd {
            margin-top: 8mm;
            page-break-inside: avoid;
        }

        .ttd td {
            width: 40%;
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
    </style>
</head>

<body>
    @if ($dibatalkan)
        <div class="cap-batal">DIBATALKAN</div>
    @endif

    <div class="kop">
        <div class="nama-penagih">{{ $invoice->gudang->nama_penagih ?? $invoice->gudang->nama }}</div>
        <div class="alamat">
            {{ $invoice->gudang->alamat_penagih ?? $invoice->gudang->alamat ?: $invoice->gudang->kota }}
            @if ($invoice->gudang->npwp_penagih)
                · NPWP {{ $invoice->gudang->npwp_penagih }}
            @endif
        </div>
    </div>

    <h1>Invoice</h1>
    <div class="subjudul">No. {{ $invoice->nomor_invoice ?? '(belum terbit)' }}</div>

    <table class="info">
        <tr>
            <td>
                <div class="judul-kolom">Kepada</div>
                [Nama penerima tagihan]<br>
                [Alamat]<br>
                NPWP: [nomor NPWP]
            </td>
            <td>
                <table class="header">
                    <tr>
                        <td class="label">Tanggal terbit</td>
                        <td class="pemisah">:</td>
                        <td>{{ $invoice->posted_at ? $tanggalPanjang($invoice->posted_at) : '(belum terbit)' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Periode</td>
                        <td class="pemisah">:</td>
                        <td>{{ $tanggalPanjang($invoice->periode_mulai) }} &ndash; {{ $tanggalPanjang($invoice->periode_selesai) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Gudang</td>
                        <td class="pemisah">:</td>
                        <td>{{ $invoice->gudang->nama }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="catatan-tarif">
        Biaya penyimpanan barang · Basis: {{ $basisContoh?->label() ?? '—' }}<br>
        Tarif: {{ $tarifContoh ? 'Rp '.number_format($tarifContoh, 2, ',', '.').' per '.$labelSatuan.' per hari' : '—' }}
    </div>

    <table class="barang">
        <thead>
            <tr>
                <th class="tengah" style="width: 8mm">No</th>
                <th>Nama Barang</th>
                <th class="angka">Unit-hari</th>
                <th class="angka">{{ $labelSatuan }} per unit</th>
                <th class="angka">{{ $labelSatuan }}-hari</th>
                <th class="angka">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->detail as $baris => $detail)
                <tr>
                    <td class="tengah">{{ $baris + 1 }}</td>
                    <td>{{ $detail->item?->nama ?? 'Item tidak ditemukan' }}</td>
                    <td class="angka">{{ number_format($detail->total_unit_hari, 2, ',', '.') }}</td>
                    <td class="angka">{{ number_format($detail->satuan_per_unit, 2, ',', '.') }}</td>
                    <td class="angka">{{ number_format($detail->total_satuan_hari, 2, ',', '.') }}</td>
                    <td class="angka">{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="tengah" style="padding: 4mm;">
                        Belum ada rincian item — mesin hitung invoice belum dibangun.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="ringkasan">
        <tr>
            <td class="angka" colspan="5">SUBTOTAL</td>
            <td class="angka" style="width: 30mm">{{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="angka" colspan="5">PPN {{ rtrim(rtrim((string) $invoice->ppn_persen, '0'), '.') }}%</td>
            <td class="angka">{{ number_format($invoice->ppn_nominal, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td class="angka" colspan="5">TOTAL</td>
            <td class="angka">{{ number_format($invoice->total, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if ($dibatalkan)
        <div class="kotak-batal">
            <strong>Dokumen ini dibatalkan</strong> pada
            {{ $tanggalPanjang($invoice->dibatalkan_at) }}
            oleh {{ $invoice->pembatal?->name ?? '—' }}.
            Alasan: {{ $invoice->alasan_pembatalan }}.
        </div>
    @endif

    <table class="ttd">
        <tr>
            <td></td>
            <td>Hormat kami,</td>
        </tr>
        <tr>
            <td></td>
            <td class="ruang"></td>
        </tr>
        <tr>
            <td></td>
            <td class="garis">
                {{ $invoice->poster?->name ?? $invoice->pembuat?->name ?? '' }}
            </td>
        </tr>
    </table>

    <div class="kaki">
        {{ $invoice->nomor_invoice ?? '(draft)' }} ·
        Dicetak {{ now()->format('d/m/Y H:i') }} ·
        Halaman 1 dari 1
    </div>
</body>

</html>
