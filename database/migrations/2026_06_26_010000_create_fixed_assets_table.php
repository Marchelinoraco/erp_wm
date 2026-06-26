<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('other'); // vehicle|equipment|building|other
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 2)->default(0);
            $table->tinyInteger('useful_life_years')->unsigned()->default(5);
            $table->decimal('residual_value', 15, 2)->default(0); // nilai sisa / scrap value
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
