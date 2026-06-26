<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('label', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('finance_settings')->insert([
            ['key' => 'modal_disetor', 'value' => 0, 'label' => 'Modal Disetor', 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_settings');
    }
};
