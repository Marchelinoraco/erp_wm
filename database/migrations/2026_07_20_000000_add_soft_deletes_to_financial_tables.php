<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'invoice_payments', 'bills', 'bill_payments', 'customers', 'tours'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'invoice_payments', 'bills', 'bill_payments', 'customers', 'tours'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
