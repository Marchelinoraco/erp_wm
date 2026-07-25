<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2 pemisahan aturan invoice per jenis penjualan — lihat
     * docs/desain/pemisahan-invoice-per-jenis.md §3.3.
     *
     * Migrasi ini HANYA menambah dua kolom nullable. Tidak ada kolom dihapus,
     * diganti nama, atau diubah tipe — down() aman tanpa kehilangan data
     * (§7.1 protokol keamanan data).
     *
     * Schema::hasColumn menjaga migrasi tetap idempoten: bila proses migrate
     * terputus di tengah jalan dan dijalankan ulang, up() tidak melempar
     * error "column already exists" (§7.7).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'sales_line')) {
                $table->string('sales_line', 20)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('invoices', 'billing_quantities')) {
                $table->json('billing_quantities')->nullable()->after('sales_line');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['sales_line', 'billing_quantities']);
        });
    }
};
