<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Harga modal yang diajukan travel agent, menunggu persetujuan internal
            $table->decimal('pending_cost', 15, 2)->nullable()->after('cost');
            $table->string('price_status')->nullable()->after('pending_cost'); // null | pending
            $table->string('price_submitted_by')->nullable()->after('price_status');
            $table->timestamp('price_updated_at')->nullable()->after('price_submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pending_cost', 'price_status', 'price_submitted_by', 'price_updated_at']);
        });
    }
};
