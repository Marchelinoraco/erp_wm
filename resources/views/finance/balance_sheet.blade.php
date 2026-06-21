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
            </tbody>
            <tfoot><tr><td>TOTAL ASET</td><td class="r">{{ $rp($aset['total']) }}</td></tr></tfoot>
        </table>
    </td>
    <td style="width:51%; vertical-align:top;">
        <table class="rpt">
            <thead><tr><th colspan="2">KEWAJIBAN &amp; EKUITAS</th></tr></thead>
            <tbody>
                <tr class="group"><td colspan="2">Kewajiban</td></tr>
                <tr><td>Hutang Usaha (AP)</td><td class="r">{{ $rp($kewajiban['ap']) }}</td></tr>
                <tr class="group"><td colspan="2">Ekuitas</td></tr>
                <tr><td>Modal Awal</td><td class="r">{{ $rp($ekuitas['modal']) }}</td></tr>
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
