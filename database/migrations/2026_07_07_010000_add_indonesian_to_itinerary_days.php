<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi bahasa Indonesia itinerary per hari — diisi manual oleh sales,
 * ditampilkan ke tim lapangan (MyJobs & manifest) menggantikan teks asli
 * (yang biasanya bahasa Inggris untuk quotation customer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_itinerary_days', function (Blueprint $table) {
            $table->string('title_ind')->nullable()->after('title');
            $table->text('description_ind')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tour_itinerary_days', function (Blueprint $table) {
            $table->dropColumn(['title_ind', 'description_ind']);
        });
    }
};
