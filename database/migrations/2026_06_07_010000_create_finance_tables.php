<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AR — tagihan ke customer
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();          // INV-YYYY-XXXX
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'partial', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['transfer', 'cash', 'other'])->default('transfer');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // AP — biaya aktual ke supplier
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->enum('category', ['hotel', 'transport', 'guide', 'restaurant', 'attraction', 'other'])
                  ->default('other');
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['transfer', 'cash', 'other'])->default('transfer');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
    }
};
