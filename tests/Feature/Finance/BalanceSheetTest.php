<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_sheet_always_balances(): void
    {
        $admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $tour = Tour::create(['pax' => 2, 'type' => 'tour']);

        $invoice = Invoice::create([
            'tour_id'   => $tour->id,
            'number'    => 'INV-2026-11-0001',
            'date'      => '2026-03-01',
            'total'     => 5000000,
            'total_idr' => 5000000,
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'date'       => '2026-03-05',
            'amount'     => 3000000,
            'amount_idr' => 3000000,
            'method'     => 'transfer',
        ]);

        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Hotel',
            'category'    => 'hotel',
            'date'        => '2026-03-02',
            'amount'      => 2000000,
        ]);
        BillPayment::create([
            'bill_id' => $bill->id,
            'date'    => '2026-03-06',
            'amount'  => 1000000,
            'method'  => 'cash',
        ]);

        $response = $this->actingAs($admin)->get(route('finance.balance-sheet', ['year' => 2026]));

        $response->assertInertia(fn ($page) => $page->where('balanced', true));
    }
}
