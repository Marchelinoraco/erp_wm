<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override nama tamu per-invoice — tampil di PDF menggantikan nama Customer
 * bila diisi, tanpa mengubah data Customer yang sebenarnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('pax');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('guest_name');
        });
    }
};
