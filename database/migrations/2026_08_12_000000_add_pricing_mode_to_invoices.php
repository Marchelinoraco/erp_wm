<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara hitung invoice: 'per_pax' atau 'per_room_night'.
 *
 * Nullable dan TIDAK di-backfill. NULL berarti 'per_pax' — perilaku yang
 * sudah berjalan — sehingga seluruh invoice yang sudah ada mempertahankan
 * nominalnya tanpa satu baris pun ditulis ulang (§7.1 protokol keamanan data:
 * migrasi hanya menambah).
 *
 * Saat ini hanya jenis hotel yang punya lebih dari satu mode; jenis lain
 * mengabaikan kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pricing_mode')->nullable()->after('sales_line');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
