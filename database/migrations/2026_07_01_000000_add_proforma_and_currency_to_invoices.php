<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice Tahap 1 → proforma editor + multi-currency.
 * - Invoice punya mata uang sendiri (currency) & kurs (exchange_rate) yang
 *   diisi saat disetujui; total_idr = total * exchange_rate untuk Keuangan.
 * - Baris deskripsi proforma terstruktur disimpan sebagai JSON (description_lines).
 * - Harga proforma = unit_price * pax.
 * - Pembayaran menyimpan amount_idr (nilai IDR) untuk buku besar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('currency', 3)->default('IDR')->after('total');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency');
            $table->decimal('unit_price', 15, 2)->default(0)->after('exchange_rate');
            $table->json('description_lines')->nullable()->after('unit_price');
            $table->decimal('total_idr', 15, 2)->default(0)->after('description_lines');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('amount_idr', 15, 2)->default(0)->after('amount');
        });

        // Backfill baris lama → tetap IDR, kurs 1, nilai IDR = nilai asli.
        DB::statement("UPDATE invoices SET currency = 'IDR', exchange_rate = 1, total_idr = total");
        DB::statement('UPDATE invoice_payments SET amount_idr = amount');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'unit_price', 'description_lines', 'total_idr']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('amount_idr');
        });
    }
};
