<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Halaman Keuangan menampilkan SATU daftar invoice — draft/proforma sampai
 * lunas — bukan lagi dua tabel terpisah (Belum Lunas / Lunas) yang keduanya
 * hanya berisi invoice yang sudah disetujui sales.
 */
class FinanceIndexInvoicesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Keuangan',
            'email'    => 'admin.keuangan@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    public function test_invoice_draft_dan_disetujui_tampil_dalam_satu_daftar(): void
    {
        $admin = $this->makeAdmin();

        // 'tour' -> kode tipe 11, 'hotel' -> kode tipe 16 -> urut number
        // memastikan draft muncul lebih dulu (11 < 16 secara string).
        $draft    = $this->makeInvoice($this->makeTour('tour'), 500_000);
        $approved = $this->approveInvoice($this->makeInvoice($this->makeTour('hotel'), 800_000));

        $this->actingAs($admin)
            ->get(route('finance.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Index')
                ->has('invoices', 2)
                ->where('invoices.0.id', $draft->id)
                ->where('invoices.0.status', 'draft')
                ->where('invoices.1.id', $approved->id)
                ->where('invoices.1.status', 'sent')
                ->missing('outstanding_invoices')
                ->missing('paid_invoices'));
    }

    public function test_invoice_lunas_ikut_tampil_dengan_status_lunas(): void
    {
        $admin    = $this->makeAdmin();
        $invoice  = $this->approveInvoice($this->makeInvoice($this->makeTour('tour'), 500_000));
        $invoice->update(['status' => 'paid']);

        $this->actingAs($admin)
            ->get(route('finance.index'))
            ->assertInertia(fn ($page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.status', 'paid'));
    }
}
