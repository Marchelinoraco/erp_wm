<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Jenis inquiry: tour | rental | guide | document | ticketing
            $table->string('type')->default('tour')->index()->after('code');
            // Field khusus per tipe (rute tiket, jenis dokumen, kendaraan, dll)
            $table->json('details')->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['type', 'details']);
        });
    }
};
