<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keterangan "Hotel / Room" untuk invoice hotel bermode per pax.
 *
 * Mode kamar menyimpan nama hotelnya per baris rincian (key `hotel` di JSON
 * description_lines) karena satu invoice boleh memuat dua hotel berbeda. Mode
 * pax tidak punya baris rincian sama sekali, jadi satu invoice = satu
 * keterangan, dan tempatnya di kolom ini.
 *
 * Nullable dan TIDAK di-backfill: invoice yang sudah ada tetap tercetak tanpa
 * baris Hotel / Room, persis seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('hotel_room')->nullable()->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('hotel_room');
        });
    }
};
