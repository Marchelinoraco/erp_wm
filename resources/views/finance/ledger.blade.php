@extends('finance.layout')

@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $groups = ['aset' => 'Kas & Bank (Aset)', 'pendapatan' => 'Pendapatan', 'beban' => 'Beban'];
    $byGroup = collect($accounts)->groupBy('group');
@endphp

<table class="rpt" style="width:60%;">
    <thead><tr><th colspan="2">Laba (Rugi) Akuntansi</th></tr></thead>
    <tbody>
        <tr><td>Total Pendapatan</td><td class="r">{{ $rp($profit['income']) }}</td></tr>
        <tr><td>Total Beban</td><td class="r">− {{ $rp($profit['expense']) }}</td></tr>
    </tbody>
    <tfoot><tr><td>{{ $profit['net'] >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}</td><td class="r">{{ $rp($profit['net']) }}</td></tr></tfoot>
</table>

<table class="rpt">
    <thead>
        <tr>
            <th>Akun</th>
            <th class="r" style="width:130px;">Debit</th>
            <th class="r" style="width:130px;">Kredit</th>
            <th class="r" style="width:140px;">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($groups as $gkey => $glabel)
            @if($byGroup->has($gkey))
            <tr class="group"><td colspan="4">{{ $glabel }}</td></tr>
            @foreach($byGroup[$gkey] as $a)
            <tr>
                <td>{{ $a['name'] }}</td>
                <td class="r">{{ $rp($a['debit']) }}</td>
                <td class="r">{{ $rp($a['credit']) }}</td>
                <td class="r"><b>{{ $rp($a['balance']) }}</b></td>
            </tr>
            @endforeach
            @endif
        @endforeach
    </tbody>
</table>
@endsection
