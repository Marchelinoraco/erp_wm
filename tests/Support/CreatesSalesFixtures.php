<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\Tour;
use App\Models\User;

/**
 * Pembuat data uji bersama untuk karakterisasi alur invoice.
 *
 * erp_wm hanya punya UserFactory, jadi tour dan invoice dibuat langsung lewat
 * Model::create() — mengikuti pola tests/Feature/Finance/InvoiceNumberingTest.php.
 */
trait CreatesSalesFixtures
{
    /** Ketujuh jenis penjualan seperti tersimpan di kolom tours.type. */
    public const SALES_TYPES = ['tour', 'hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'];

    private int $userCounter = 0;

    /** Pengguna ber-role sales — seluruh rute invoice memakai middleware role:admin,sales. */
    protected function salesUser(): User
    {
        $this->userCounter++;

        return User::create([
            'name'     => 'Sales Uji ' . $this->userCounter,
            'email'    => 'sales' . $this->userCounter . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'sales',
        ]);
    }

    /** Pengguna ber-role accountant — sejak fitur Rincian Profit Terbuka, ikut bisa mengedit item. */
    protected function accountantUser(): User
    {
        $this->userCounter++;

        return User::create([
            'name'     => 'Akuntan Uji ' . $this->userCounter,
            'email'    => 'akuntan' . $this->userCounter . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }

    /**
     * Tour untuk satu jenis penjualan. Status confirmed karena panel invoice
     * hanya muncul pada status itu.
     */
    protected function makeTour(string $type, array $overrides = []): Tour
    {
        return Tour::create(array_replace([
            'type'       => $type,
            'status'     => 'confirmed',
            'pax'        => 10,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-05',
        ], $overrides));
    }

    /** Invoice draft dengan harga proforma sudah terisi dan total tersinkron. */
    protected function makeInvoice(Tour $tour, float $unitPrice, array $overrides = []): Invoice
    {
        $invoice = Invoice::create(array_replace([
            'tour_id'    => $tour->id,
            'number'     => Invoice::nextNumber($tour),
            'date'       => now()->toDateString(),
            'currency'   => 'IDR',
            'unit_price' => $unitPrice,
            'status'     => 'draft',
        ], $overrides));

        $invoice->syncProformaTotal();

        return $invoice->fresh();
    }

    /**
     * Kunci patokan ke total saat ini lalu setujui invoice sebagai sales.
     * Merangkum urutan yang berulang di banyak test karakterisasi: kunci
     * baseline_total = total, POST invoices.approve, pastikan redirect (bukan
     * ditolak), lalu kembalikan invoice yang sudah segar dari database.
     *
     * JANGAN dipakai bila baseline_total sengaja perlu berbeda dari total,
     * atau bila approve-nya justru diharapkan GAGAL — kedua kasus itu tetap
     * ditulis manual di masing-masing test.
     */
    protected function approveInvoice(Invoice $invoice, array $payload = []): Invoice
    {
        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice), $payload)
            ->assertRedirect();

        return $invoice->fresh();
    }
}
