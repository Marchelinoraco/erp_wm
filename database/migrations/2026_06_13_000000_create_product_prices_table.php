<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('sell', 15, 2)->default(0);
            $table->decimal('pending_cost', 15, 2)->nullable();
            $table->string('status', 20)->nullable(); // null | 'pending'
            $table->string('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
