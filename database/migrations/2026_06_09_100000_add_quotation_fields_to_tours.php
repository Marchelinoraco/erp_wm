<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Matriks harga publik (tier pax × hotel) untuk quotation customer
            $table->json('pricing')->nullable()->after('notes');
            // Teks quotation yang bisa diedit per tour (default dari config/quotation.php)
            $table->text('included')->nullable()->after('pricing');
            $table->text('excluded')->nullable()->after('included');
            $table->text('child_policy')->nullable()->after('excluded');
            $table->text('terms')->nullable()->after('child_policy');
            $table->date('price_validity')->nullable()->after('terms'); // harga berlaku s/d
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['pricing', 'included', 'excluded', 'child_policy', 'terms', 'price_validity']);
        });
    }
};
