<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya tambahan yang DITAGIHKAN ke customer: saat akuntan approve dengan
 * opsi "tagihkan", sistem membuat invoice suplemen (nomor -T1/-T2, langsung
 * approved) — invoice utama yang sudah terbit tidak pernah diubah.
 * Kolom ini menautkan cost request ke invoice suplemennya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_requests', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('bill_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
