<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['manado', 'international']);
            $table->unsignedBigInteger('original_id');  // id di DB website
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('tour_type')->default('daily'); // daily|overnight|multi_day
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('duration_nights')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('package_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('tour_packages')
                  ->nullOnDelete();
            $table->enum('inquiry_source', ['website', 'external'])
                  ->nullable()
                  ->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn(['package_id', 'inquiry_source']);
        });
        Schema::dropIfExists('tour_packages');
    }
};
