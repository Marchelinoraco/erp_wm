@extends('finance.layout')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

<table width="100%" style="border-collapse:collapse;">
<tr>
    <td style="width:49%; vertical-align:top; padding-right:8px;">
        <table class="rpt">
            <thead><tr><th colspan="2">ASET</th></tr></thead>
            <tbody>
                <tr class="group"><td colspan="2">Kas &amp; Bank</td></tr>
                @foreach($aset['cash'] as $c)
                <tr><td>{{ $c['name'] }}</td><td class="r">{{ $rp($c['balance']) }}</td></tr>
                @endforeach
                <tr class="group"><td colspan="2">Piutang</td></tr>
                <tr><td>Piutang Usaha (AR)</td><td class="r">{{ $rp($aset['ar']) }}</td></tr>
                @if(!empty($aset['fixed']) && count($aset['fixed']))
                <tr class="group"><td colspan="2">Aset Tetap</td></tr>
                @foreach($aset['fixed'] as $grp)
                <tr><td style="padding-left:18px; color:#475569;">{{ $grp['label'] }}</td><td></td></tr>
                @foreach($grp['items'] as $item)
                <tr><td style="padding-left:28px;">{{ $item['name'] }}</td><td class="r">{{ $rp($item['cost']) }}</td></tr>
                @endforeach
                <tr><td style="padding-left:28px; font-style:italic; color:#64748b;">Akumulasi Penyusutan</td><td class="r" style="color:#dc2626;">({{ $rp($grp['total_accum']) }})</td></tr>
                <tr class="alt"><td style="padding-left:18px; font-weight:bold;">Nilai Buku — {{ $grp['label'] }}</td><td class="r" style="color:#059669;">{{ $rp($grp['total_net']) }}</td></tr>
                @endforeach
                <tr><td style="font-weight:bold;">Total Aset Tetap (Neto)</td><td class="r" style="font-weight:bold; color:#059669;">{{ $rp($aset['fixed_net']) }}</td></tr>
                @endif
            </tbody>
            <tfoot><tr><td>TOTAL ASET</td><td class="r">{{ $rp($aset['total']) }}</td></tr></tfoot>
        </table>
    </td>
    <td style="width:51%; vertical-align:top;">
        <table class="rpt">
            <thead><tr><th colspan="2">KEWAJIBAN &amp; EKUITAS</th></tr></thead>
            <tbody>
                <tr class="group"><td colspan="2">Kewajiban Jangka Pendek</td></tr>
                <tr><td>Hutang Usaha (AP)</td><td class="r">{{ $rp($kewajiban['ap']) }}</td></tr>
                @if(!empty($kewajiban['loans']) && count($kewajiban['loans']))
                <tr class="group"><td colspan="2">Kewajiban Jangka Panjang</td></tr>
                @foreach($kewajiban['loans'] as $grp)
                <tr><td style="padding-left:18px; color:#475569;">{{ $grp['label'] }}</td><td></td></tr>
                @foreach($grp['items'] as $item)
                <tr><td style="padding-left:28px;">{{ $item['name'] }}@if($item['lender']) <span style="color:#94a3b8;"> — {{ $item['lender'] }}</span>@endif</td><td class="r">{{ $rp($item['outstanding']) }}</td></tr>
                @endforeach
                <tr class="alt"><td style="padding-left:18px; font-weight:bold;">Subtotal {{ $grp['label'] }}</td><td class="r" style="color:#dc2626;">{{ $rp($grp['total']) }}</td></tr>
                @endforeach
                <tr><td style="font-weight:bold;">Total Hutang Bank/Leasing</td><td class="r" style="font-weight:bold; color:#dc2626;">{{ $rp($kewajiban['loans_total']) }}</td></tr>
                @endif
                <tr class="group"><td colspan="2">Ekuitas</td></tr>
                <tr><td>Modal Disetor</td><td class="r">{{ $rp($ekuitas['modal']) }}</td></tr>
                <tr><td>Laba Ditahan</td><td class="r">{{ $rp($ekuitas['laba_ditahan']) }}</td></tr>
            </tbody>
            <tfoot><tr><td>TOTAL KEWAJIBAN + EKUITAS</td><td class="r">{{ $rp($kewajiban['total'] + $ekuitas['total']) }}</td></tr></tfoot>
        </table>
    </td>
</tr>
</table>

<p style="margin-top:6px;">
    <span class="ok-badge">{{ $balanced ? '✓ Seimbang — Aset = Kewajiban + Ekuitas' : '✗ Tidak seimbang' }}</span>
</p>
@endsection
