<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akun kas/bank (sisi aset di jurnal otomatis)
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cash', 'bank'])->default('cash');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Kategori pendapatan & pengeluaran (sisi income/expense di jurnal otomatis)
        Schema::create('fin_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->boolean('is_system')->default(false); // kategori bawaan (AR/AP) — tak bisa dihapus
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Transaksi kas — sumber tunggal arus kas, jurnal, buku besar
        Schema::create('fin_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('direction', ['in', 'out']);            // in = pendapatan, out = pengeluaran
            $table->foreignId('fin_category_id')->constrained('fin_categories')->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->enum('source', ['manual', 'invoice', 'bill'])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();  // id invoice_payment / bill_payment
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['date']);
            $table->index(['source', 'source_id']);
        });

        // ── Seed default ──
        $now = now();
        $kasId  = DB::table('cash_accounts')->insertGetId(['name' => 'Kas',      'type' => 'cash', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $bankId = DB::table('cash_accounts')->insertGetId(['name' => 'Bank BCA', 'type' => 'bank', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $catIds = [];
        $cats = [
            ['Penjualan Tour',      'income',  true],
            ['Pendapatan Lain-lain','income',  false],
            ['Biaya Supplier',      'expense', true],
            ['Gaji Karyawan',       'expense', false],
            ['Sewa & Utilitas',     'expense', false],
            ['Operasional',         'expense', false],
            ['Pengeluaran Lain-lain','expense', false],
        ];
        foreach ($cats as $i => [$name, $type, $sys]) {
            $catIds[$name] = DB::table('fin_categories')->insertGetId([
                'name' => $name, 'type' => $type, 'is_system' => $sys, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Pemetaan metode → akun kas
        $accFor = fn ($method) => str_contains(strtolower((string) $method), 'cash') || str_contains(strtolower((string) $method), 'tunai') ? $kasId : $bankId;

        // ── Backfill: pembayaran invoice (AR) = pendapatan "Penjualan Tour" ──
        foreach (DB::table('invoice_payments')->get() as $p) {
            DB::table('fin_transactions')->insert([
                'date' => $p->date, 'direction' => 'in',
                'fin_category_id' => $catIds['Penjualan Tour'], 'cash_account_id' => $accFor($p->method),
                'amount' => $p->amount, 'description' => 'Pembayaran invoice' . ($p->notes ? ' — ' . $p->notes : ''),
                'source' => 'invoice', 'source_id' => $p->id, 'created_by' => 'Sistem (impor)',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── Backfill: pembayaran bill (AP) = pengeluaran "Biaya Supplier" ──
        foreach (DB::table('bill_payments')->get() as $p) {
            DB::table('fin_transactions')->insert([
                'date' => $p->date, 'direction' => 'out',
                'fin_category_id' => $catIds['Biaya Supplier'], 'cash_account_id' => $accFor($p->method),
                'amount' => $p->amount, 'description' => 'Pembayaran ke supplier' . ($p->notes ? ' — ' . $p->notes : ''),
                'source' => 'bill', 'source_id' => $p->id, 'created_by' => 'Sistem (impor)',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_transactions');
        Schema::dropIfExists('fin_categories');
        Schema::dropIfExists('cash_accounts');
    }
};
