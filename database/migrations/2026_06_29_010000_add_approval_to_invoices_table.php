<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur invoice pindah ke sisi sales (2 tahap):
 *  - pax            : jumlah peserta untuk hitung patokan (pax × harga)
 *  - baseline_total : "patokan" total yang dikunci di Tahap 1; rincian Tahap 2 wajib = ini
 *  - approved_at    : gerbang ke Keuangan — invoice baru tampil di finance setelah disetujui
 *  - approved_by    : sales yang menyetujui
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('pax')->nullable()->after('tour_id');
            $table->decimal('baseline_total', 15, 2)->default(0)->after('total');
            $table->timestamp('approved_at')->nullable()->after('notes');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['pax', 'baseline_total', 'approved_at']);
        });
    }
};
