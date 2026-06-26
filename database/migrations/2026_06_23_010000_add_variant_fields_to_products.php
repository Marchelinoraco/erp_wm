<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Produk dengan group_label sama = saudara varian (mis. "Lunch Prasmanan")
            $table->string('group_label')->nullable()->index()->after('name');
            // Grade varian: hemat | standar | premium (null = produk tunggal, bukan varian)
            $table->string('grade')->nullable()->after('group_label');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['group_label', 'grade']);
        });
    }
};
