@extends('finance.layout')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

<table class="rpt">
    <thead>
        <tr>
            <th>{{ $mode === 'weekly' ? 'Minggu' : 'Bulan' }}</th>
            <th class="r">Pemasukan</th>
            <th class="r">Pengeluaran</th>
            <th class="r">Net</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $r)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            <td>{{ $r['label'] }}</td>
            <td class="r">{{ $rp($r['income']) }}</td>
            <td class="r">{{ $rp($r['expense']) }}</td>
            <td class="r"><b>{{ $rp($r['net']) }}</b></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>TOTAL</td>
            <td class="r">{{ $rp($totals['income']) }}</td>
            <td class="r">{{ $rp($totals['expense']) }}</td>
            <td class="r">{{ $rp($totals['net']) }}</td>
        </tr>
    </tfoot>
</table>
@endsection
