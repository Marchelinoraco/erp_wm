<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tour_packages MODIFY COLUMN type ENUM('manado','national','international') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tour_packages MODIFY COLUMN type ENUM('manado','international') NOT NULL");
    }
};
