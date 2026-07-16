<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales pilih rekening mana yang ditampilkan di PDF invoice customer.
 * Null/kosong = tampilkan semua rekening aktif (perilaku lama, tetap berlaku
 * untuk invoice yang sudah ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('bank_account_ids')->nullable()->after('description_lines');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('bank_account_ids');
        });
    }
};
