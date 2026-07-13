<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akuntan memilih akun kas (BCA/BNI/Kas dll) secara eksplisit saat mencatat
 * pembayaran — menggantikan tebakan LedgerSync::accountForMethod() yang
 * cuma mengambil akun pertama bertipe cash/bank (salah begitu ada >1 akun
 * bank). Nullable: baris lama tetap valid, LedgerSync fallback ke tebakan
 * lama untuk baris yang belum punya cash_account_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('method')
                ->constrained()->nullOnDelete();
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->after('method')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
        });
    }
};
