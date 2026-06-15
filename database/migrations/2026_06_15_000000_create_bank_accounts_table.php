<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank');
            $table->string('account_number');
            $table->string('holder_name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed awal dari config agar invoice tidak kosong (silakan diedit via UI).
        foreach (config('quotation.bank', []) as $i => $b) {
            DB::table('bank_accounts')->insert([
                'bank'           => $b['bank'] ?? '',
                'account_number' => $b['account'] ?? '',
                'holder_name'    => $b['name'] ?? '',
                'is_active'      => true,
                'sort_order'     => $i,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
