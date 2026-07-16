<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurs per-pembayaran (bukan satu kurs terkunci di invoice). Kasus nyata:
 * DP tanggal 1 pakai kurs hari itu, pelunasan tanggal 4 pakai kurs yang
 * sudah berubah — masing-masing pembayaran punya nilai IDR sendiri sesuai
 * kurs saat diterima, dan Diterima/Cash Flow/Saldo Kas (yang sudah menjumlah
 * amount_idr per baris) otomatis jadi akurat tanpa perlu diubah lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 15, 6)->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
