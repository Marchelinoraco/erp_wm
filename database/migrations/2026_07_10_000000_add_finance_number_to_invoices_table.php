<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $counters = [];
        Invoice::whereNotNull('approved_at')
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $inv) use (&$counters) {
                $year = $inv->approved_at->format('Y');
                $counters[$year] = ($counters[$year] ?? 0) + 1;
                $inv->update([
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
