@extends('finance.layout')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

<table class="rpt">
    <thead>
        <tr>
            <th>Akun</th>
            <th class="c">Tipe</th>
            <th class="r">Saldo Awal</th>
            <th class="r">Masuk</th>
            <th class="r">Keluar</th>
            <th class="r">Saldo Akhir</th>
        </tr>
    </thead>
    <tbody>
        @foreach($accounts as $i => $a)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $a['name'] }}</td>
            <td class="c">{{ $a['type'] === 'cash' ? 'Kas' : 'Bank' }}</td>
            <td class="r">{{ $rp($a['opening']) }}</td>
            <td class="r">{{ $rp($a['masuk']) }}</td>
            <td class="r">{{ $rp($a['keluar']) }}</td>
            <td class="r"><b>{{ $rp($a['saldo']) }}</b></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAL KAS &amp; BANK</td>
            <td class="r">{{ $rp($cashTotal) }}</td>
        </tr>
    </tfoot>
</table>

<table class="rpt" style="width:55%;">
    <thead><tr><th colspan="2">Pos Lain</th></tr></thead>
    <tbody>
        <tr><td>Piutang Usaha (AR) — belum tertagih</td><td class="r">{{ $rp($ar) }}</td></tr>
        <tr class="alt"><td>Hutang Usaha (AP) — belum dibayar</td><td class="r">{{ $rp($ap) }}</td></tr>
    </tbody>
</table>
@endsection
