<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('lender', 100)->nullable();
            $table->string('loan_type', 20)->default('leasing');   // bank_loan|leasing|other
            $table->decimal('original_amount', 15, 2);
            $table->date('start_date');
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);         // saldo pokok terkini (manual)
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
