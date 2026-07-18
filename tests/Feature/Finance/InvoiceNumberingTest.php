<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_number_increments_sequentially(): void
    {
        $tour = Tour::create(['pax' => 1, 'type' => 'tour']);

        $first = Invoice::nextNumber($tour);
        Invoice::create(['tour_id' => $tour->id, 'number' => $first, 'date' => now()]);

        $second = Invoice::nextNumber($tour);

        $this->assertNotEquals($first, $second);
        $this->assertStringEndsWith('0002', $second);
    }

    public function test_next_number_does_not_collide_after_soft_deleting_latest_invoice(): void
    {
        $tour = Tour::create(['pax' => 1, 'type' => 'tour']);

        $number  = Invoice::nextNumber($tour);
        $invoice = Invoice::create(['tour_id' => $tour->id, 'number' => $number, 'date' => now()]);

        $invoice->delete(); // soft delete — invoice terakhir kini "tersembunyi"

        $nextNumber = Invoice::nextNumber($tour);

        $this->assertNotEquals(
            $number,
            $nextNumber,
            'Nomor invoice tidak boleh dipakai ulang setelah invoice sebelumnya di-soft-delete — akan bentrok unique constraint.'
        );

        // Buktikan langsung: membuat invoice baru dengan nomor ini tidak gagal.
        Invoice::create(['tour_id' => $tour->id, 'number' => $nextNumber, 'date' => now()]);
        $this->assertDatabaseCount('invoices', 2);
    }
}
