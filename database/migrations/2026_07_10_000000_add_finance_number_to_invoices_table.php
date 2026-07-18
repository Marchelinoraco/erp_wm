<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Nomor keuangan gapless (INV-<tahun>-NNNN), ditetapkan saat approve.
            // Nomor lama (turunan kode tour) tetap dipakai di PDF customer.
            $table->string('finance_number')->nullable()->unique()->after('number');
        });

        // Backfill: invoice yang sudah approved diberi nomor urut sesuai
        // urutan approve, counter reset per tahun (tahun approved_at).
        // Pakai query builder (bukan model Eloquent) supaya migration ini tidak
        // ikut terpengaruh scope/kolom yang ditambahkan ke model Invoice di masa depan.
        $counters = [];
        DB::table('invoices')
            ->whereNotNull('approved_at')
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get(['id', 'approved_at'])
            ->each(function ($inv) use (&$counters) {
                $year = substr($inv->approved_at, 0, 4);
                $counters[$year] = ($counters[$year] ?? 0) + 1;
                DB::table('invoices')->where('id', $inv->id)->update([
                    'finance_number' => 'INV-' . $year . '-' . str_pad($counters[$year], 4, '0', STR_PAD_LEFT),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['finance_number']);
            $table->dropColumn('finance_number');
        });
    }
};
