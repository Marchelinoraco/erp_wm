<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal mulai & selesai layanan per item invoice — diisi untuk produk
 * bertipe hotel/transport/guide agar tim lapangan (MyJobs) tahu jadwalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('nights');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
