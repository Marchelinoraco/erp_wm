@extends('finance.layout')

@section('content')
@php
    $rp = fn ($v) => $v > 0 ? number_format((float) $v, 0, ',', '.') : '';
    $fd = fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M Y');
@endphp

<p class="muted">Bukti keseimbangan: Total Debit = Total Kredit = Rp {{ number_format($totals['debit'], 0, ',', '.') }} ({{ $totals['count'] }} transaksi)</p>

<table class="rpt">
    <thead>
        <tr>
            <th style="width:80px;">Tanggal</th>
            <th>Akun &amp; Keterangan</th>
            <th class="r" style="width:120px;">Debit</th>
            <th class="r" style="width:120px;">Kredit</th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $e)
            @foreach($e['lines'] as $li => $ln)
            <tr>
                <td>{{ $li === 0 ? $fd($e['date']) : '' }}</td>
                <td style="{{ $ln['credit'] > 0 ? 'padding-left:28px;color:#475569;' : 'font-weight:bold;' }}">{{ $ln['account'] }}</td>
                <td class="r">{{ $rp($ln['debit']) }}</td>
                <td class="r">{{ $rp($ln['credit']) }}</td>
            </tr>
            @endforeach
            <tr><td></td><td colspan="3" class="sub" style="border-top:none;">{{ $e['description'] }} · {{ $e['ref'] }}</td></tr>
        @empty
            <tr><td colspan="4" class="c">Belum ada jurnal pada bulan ini.</td></tr>
        @endforelse
    </tbody>
    @if(count($entries))
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td class="r">{{ number_format($totals['debit'], 0, ',', '.') }}</td>
            <td class="r">{{ number_format($totals['credit'], 0, ',', '.') }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
