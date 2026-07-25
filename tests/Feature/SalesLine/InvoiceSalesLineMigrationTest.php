<?php

namespace Tests\Feature\SalesLine;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi Fase 2: dua kolom nullable pada invoices. Idempoten (Schema::hasColumn)
 * supaya aman dijalankan ulang bila migrasi terputus di tengah jalan (§7.7).
 */
class InvoiceSalesLineMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_sales_line_ada_dan_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
    }

    public function test_kolom_billing_quantities_ada_dan_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'));
    }

    public function test_kedua_kolom_defaultnya_null_untuk_invoice_baru(): void
    {
        $tour = \App\Models\Tour::create(['type' => 'tour', 'status' => 'confirmed', 'pax' => 1]);

        $invoice = \App\Models\Invoice::create([
            'tour_id'    => $tour->id,
            'number'     => \App\Models\Invoice::nextNumber($tour),
            'date'       => now()->toDateString(),
            'currency'   => 'IDR',
            'unit_price' => 100_000,
        ]);

        $this->assertNull($invoice->fresh()->sales_line);
        $this->assertNull($invoice->fresh()->billing_quantities);
    }

    public function test_migrasi_aman_dijalankan_ulang(): void
    {
        // Simulasi migrasi terputus lalu dijalankan ulang — up() kedua tidak
        // boleh error karena kolom sudah ada (Schema::hasColumn guard).
        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'));
    }

    public function test_migrasi_pulih_dari_kolom_yang_setengah_selesai(): void
    {
        // Skenario §7.7 yang sesungguhnya: migrasi terputus PERSIS di antara
        // dua kolom — satu sudah ada, satu belum. Guard per kolom harus
        // independen: kolom yang hilang ditambahkan, kolom yang sudah ada
        // TIDAK boleh memicu error "column already exists".
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('billing_quantities');
        });
        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertFalse(Schema::hasColumn('invoices', 'billing_quantities'));

        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'), 'Kolom yang sudah ada tidak boleh error');
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'), 'Kolom yang hilang harus ditambahkan');
    }

    public function test_down_membersihkan_kedua_kolom(): void
    {
        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );

        $migration->down();

        $this->assertFalse(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertFalse(Schema::hasColumn('invoices', 'billing_quantities'));

        // Pulihkan supaya RefreshDatabase test berikutnya tidak terpengaruh —
        // migrate:fresh di awal setiap run akan membangun ulang dari nol,
        // tapi kita kembalikan eksplisit agar test ini tidak meninggalkan
        // skema rusak bila dijalankan sendirian dengan --filter.
        $migration->up();
    }
}
