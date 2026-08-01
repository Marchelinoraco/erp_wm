<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D7/D10: kas bon adalah piutang (aset), bukan beban. Sisa
 * dihitung dari amount dikurangi payroll_item_lines yang merujuknya — TIDAK
 * disimpan di sini, supaya "Batalkan" gajian (Task 6) memulihkan sisa
 * otomatis tanpa perbaikan manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->foreignId('fin_transaction_id')->constrained('fin_transactions')->restrictOnDelete();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
