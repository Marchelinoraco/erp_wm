@extends('finance.layout')

@section('content')
@php
    $rp  = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => $v !== null ? number_format((float) $v, 1) . '%' : '—';
@endphp

{{-- Penjualan & HPP per Lini Bisnis --}}
<p class="section-title">Penjualan &amp; HPP per Lini Bisnis</p>
<table class="rpt">
    <thead>
        <tr>
            <th>Lini Bisnis</th>
            <th class="r">Penjualan</th>
            <th class="r">HPP / COGS</th>
            <th class="r">Laba Kotor</th>
            <th class="r" style="width:60px;">Margin</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $i => $l)
        <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
            <td>{{ $l['label'] }}</td>
            <td class="r">{{ $rp($l['revenue']) }}</td>
            <td class="r">{{ $rp($l['cogs']) }}</td>
            <td class="r">{{ $rp($l['gross']) }}</td>
            <td class="r">{{ $pct($l['gross_pct']) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;" class="sub">Belum ada data invoice / bill.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>TOTAL PENJUALAN</td>
            <td class="r">{{ $rp($totalRevenue) }}</td>
            <td class="r">{{ $rp($totalCogs) }}</td>
            <td class="r">{{ $rp($grossProfit) }}</td>
            <td class="r">{{ $pct($grossMargin) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Biaya Operasional --}}
<p class="section-title" style="margin-top:14px;">Biaya Operasional</p>
<table class="rpt">
    <thead>
        <tr>
            <th>Komponen Biaya</th>
            <th class="r" style="width:200px;">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @forelse($opex as $i => $item)
        <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
            <td>{{ $item['name'] }}</td>
            <td class="r">{{ $rp($item['total']) }}</td>
        </tr>
        @empty
        <tr><td colspan="2" style="text-align:center;" class="sub">Belum ada transaksi biaya operasional.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>TOTAL BIAYA OPERASIONAL</td>
            <td class="r">{{ $rp($totalOpex) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Beban Penyusutan --}}
@if(!empty($depreciation) && count($depreciation))
<p class="section-title" style="margin-top:14px;">Beban Penyusutan Aset Tetap</p>
<table class="rpt">
    <thead>
        <tr>
            <th>Nama Aset</th>
            <th class="r" style="width:200px;">Beban Penyusutan {{ $year }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($depreciation as $i => $item)
        <tr class="{{ $i % 2 === 1 ? 'alt' : '' }}">
            <td>{{ $item['name'] }}</td>
            <td class="r">{{ $rp($item['total']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>TOTAL BEBAN PENYUSUTAN</td>
            <td class="r">{{ $rp($totalDepreciation) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Ringkasan --}}
<p class="section-title" style="margin-top:14px;">Ringkasan Laba Rugi</p>
<table class="rpt" style="width:55%; margin-left:45%;">
    <tbody>
        <tr>
            <td>Laba Kotor Penjualan</td>
            <td class="r">{{ $rp($grossProfit) }}</td>
        </tr>
        <tr class="alt">
            <td>(–) Total Biaya Operasional</td>
            <td class="r">({{ $rp($totalOpex) }})</td>
        </tr>
        @if(!empty($totalDepreciation) && $totalDepreciation > 0)
        <tr>
            <td>(–) Beban Penyusutan</td>
            <td class="r">({{ $rp($totalDepreciation) }})</td>
        </tr>
        @endif
        @if($otherIncome > 0)
        <tr>
            <td>(+) Pendapatan Lain-lain</td>
            <td class="r">{{ $rp($otherIncome) }}</td>
        </tr>
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td>LABA BERSIH {{ $year }}</td>
            <td class="r">
                {{ $rp($netProfit) }}
                @if($netMargin !== null)
                <span class="muted"> &nbsp;({{ $pct($netMargin) }})</span>
                @endif
            </td>
        </tr>
    </tfoot>
</table>

<p class="muted" style="margin-top:12px;">
    Penjualan = Invoice AR &nbsp;·&nbsp; HPP = Bill AP &nbsp;·&nbsp;
    Biaya Operasional = transaksi kas manual (keluar) &nbsp;·&nbsp;
    Penyusutan = garis lurus, dihitung otomatis dari master Aset Tetap.
</p>
@endsection
