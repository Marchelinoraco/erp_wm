@extends('finance.layout')

@section('content')
@php
    $rp  = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $regimeLabel = $regime === 'pp23' ? 'PP 23/2018 — 0,5% dari Omzet' : 'PPh Badan Umum — 22% dari PKP';
@endphp

{{-- Laba Komersial --}}
<p class="section-title">Laba Rugi Komersial</p>
<table class="rpt" style="width:60%; margin-left:40%;">
    <tbody>
        <tr><td>Penjualan (Invoice AR)</td><td class="r">{{ $rp($totalRevenue) }}</td></tr>
        <tr class="alt"><td>(–) HPP / COGS (Bill AP)</td><td class="r">({{ $rp($totalCogs) }})</td></tr>
        <tr style="font-weight:bold;"><td>= Laba Kotor</td><td class="r">{{ $rp($grossProfit) }}</td></tr>
        <tr><td>(–) Biaya Operasional</td><td class="r">({{ $rp($totalOpex) }})</td></tr>
        <tr><td>(–) Penyusutan Komersial</td><td class="r">({{ $rp($depKomersial) }})</td></tr>
        @if($otherIncome > 0)
        <tr><td>(+) Pendapatan Lain-lain</td><td class="r">{{ $rp($otherIncome) }}</td></tr>
        @endif
    </tbody>
    <tfoot><tr><td>LABA KOMERSIAL</td><td class="r">{{ $rp($labaKomersial) }}</td></tr></tfoot>
</table>

{{-- Penyusutan Fiskal --}}
@if(count($depAssets))
<p class="section-title" style="margin-top:14px;">Perbandingan Penyusutan</p>
<table class="rpt">
    <thead>
        <tr>
            <th>Aset</th>
            <th>Kelompok Fiskal</th>
            <th class="r">Komersial</th>
            <th class="r">Fiskal</th>
            <th class="r">Selisih</th>
        </tr>
    </thead>
    <tbody>
        @foreach($depAssets as $i => $d)
        <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
            <td>{{ $d['name'] }}</td>
            <td>{{ $d['fiscal_label'] }}</td>
            <td class="r">{{ $rp($d['dep_comm']) }}</td>
            <td class="r">{{ $rp($d['dep_fiscal']) }}</td>
            <td class="r">{{ $d['selisih'] >= 0 ? '+' : '–' }}{{ $rp(abs($d['selisih'])) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td class="r">{{ $rp($depKomersial) }}</td>
            <td class="r">{{ $rp($depFiskal) }}</td>
            <td class="r">{{ $selisihDep >= 0 ? '+' : '–' }}{{ $rp(abs($selisihDep)) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Koreksi Manual --}}
@if(count($corrections))
<p class="section-title" style="margin-top:14px;">Koreksi Fiskal Manual</p>
<table class="rpt">
    <thead>
        <tr>
            <th>Deskripsi</th>
            <th>Jenis</th>
            <th class="r">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach($corrections as $i => $c)
        <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
            <td>{{ $c['name'] }}</td>
            <td>{{ $c['type'] === 'positive' ? 'Positif' : 'Negatif' }}</td>
            <td class="r">{{ $c['type'] === 'positive' ? '+' : '–' }}{{ $rp($c['amount']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Rekonsiliasi PKP --}}
<p class="section-title" style="margin-top:14px;">Rekonsiliasi Fiskal — PKP</p>
<table class="rpt" style="width:60%; margin-left:40%;">
    <tbody>
        <tr><td>Laba Komersial</td><td class="r">{{ $rp($labaKomersial) }}</td></tr>
        <tr class="alt"><td>(+) Total Koreksi Positif</td><td class="r">+{{ $rp($korPositif) }}</td></tr>
        <tr><td>(–) Total Koreksi Negatif</td><td class="r">–{{ $rp($korNegatif) }}</td></tr>
    </tbody>
    <tfoot><tr><td>PENGHASILAN KENA PAJAK (PKP)</td><td class="r">{{ $rp($pkp) }}</td></tr></tfoot>
</table>

{{-- PPh Terutang --}}
<p class="section-title" style="margin-top:14px;">PPh Badan Terutang — {{ $regimeLabel }}</p>
<table class="rpt" style="width:60%; margin-left:40%;">
    <tbody>
        <tr><td>Dasar Pengenaan Pajak</td><td class="r">{{ $rp($taxBase) }}</td></tr>
        <tr class="alt"><td>Tarif PPh</td><td class="r">{{ $taxRatePct }}%</td></tr>
    </tbody>
    <tfoot><tr><td>PPH BADAN TERUTANG</td><td class="r">{{ $rp($pphTerutang) }}</td></tr></tfoot>
</table>

<p class="muted" style="margin-top:12px;">
    Estimasi koreksi fiskal — konsultasikan dengan konsultan pajak sebelum pelaporan SPT.
    Penyusutan fiskal: garis lurus tanpa nilai sisa (PMK 96/2009).
</p>
@endsection
