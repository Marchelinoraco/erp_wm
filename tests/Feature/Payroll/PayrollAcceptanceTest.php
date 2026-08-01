<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance test — skenario penuh spek §4.2, LEWAT HTTP (bukan manipulasi
 * model langsung), meniru urutan aksi admin sungguhan:
 * 1. Buat karyawan + komponen tetap.
 * 2. Beri kas bon.
 * 3. Buka periode gajian, lihat draft.
 * 4. Bayar.
 * 5. Verifikasi: Neraca balance, Laba Rugi penuh, Buku Besar satu baris
 *    bersaldo nol untuk Piutang Karyawan.
 * 6. Batalkan, verifikasi semuanya pulih.
 */
class PayrollAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_skenario_lengkap_kas_bon_dan_gajian_dari_ujung_ke_ujung(): void
    {
        $admin = $this->actingAs($this->makeAdmin());
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        // 1. Karyawan + komponen
        $admin->post(route('employees.store'), [
            'name' => 'Budi Santoso', 'position' => 'Driver', 'base_salary' => 4_000_000,
        ])->assertSessionHasNoErrors();
        $karyawan = Employee::where('name', 'Budi Santoso')->firstOrFail();

        $admin->post(route('employees.components.store', $karyawan), [
            'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000,
        ])->assertSessionHasNoErrors();

        // 2. Kas bon
        $admin->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ])->assertSessionHasNoErrors();

        // 3. Buka periode — pastikan draft sudah mencerminkan kas bon
        //
        // Perbandingan dipakai lewat closure (bukan assertSame nilai literal
        // float): AssertableInertia::fromTestResponse() melakukan
        // json_decode(json_encode(...)) atas props (lihat vendor
        // inertiajs/inertia-laravel/src/Testing/AssertableInertia.php) — float
        // bulat seperti 3800000.0 kehilangan jejak "float"-nya lewat json_encode
        // PHP dan didekode balik jadi int. assertSame(3_800_000.0, ...) gagal
        // BUKAN karena nilainya salah (nilainya persis benar), tapi karena
        // int(3800000) !== float(3800000.0) secara tipe. Closure di bawah tetap
        // menuntut nilai NUMERIK yang persis sama, hanya longgar soal tipe
        // int/float akibat batas transport JSON — bukan pelonggaran substansi.
        $admin->get(route('payrolls.show', '2026-07'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('draft.0.net_amount', fn ($v) => (float) $v === 3_800_000.0)
                ->where('draft.0.advances.0.potongan', fn ($v) => (float) $v === 1_000_000.0)
            );

        // 4. Bayar
        $admin->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id])
            ->assertSessionHasNoErrors();

        // 5. Verifikasi laporan
        $controller = app(\App\Http\Controllers\FinanceReportController::class);

        $refNeraca = new \ReflectionMethod($controller, 'balanceSheetData');
        $refNeraca->setAccessible(true);
        $neraca = $refNeraca->invoke($controller, 2026);
        $this->assertTrue($neraca['balanced'], 'Neraca harus tetap balance setelah gajian dengan kas bon');
        $this->assertSame(0.0, $neraca['aset']['other_assets_total'], 'Piutang lunas -> saldo nol');

        $refLabaRugi = new \ReflectionMethod($controller, 'incomeStatementData');
        $refLabaRugi->setAccessible(true);
        $labaRugi = $refLabaRugi->invoke($controller, 2026);
        $this->assertSame(4_800_000.0, $labaRugi['totalOpex'], 'Beban gaji harus penuh, termasuk yang dilunasi lewat kas bon');

        $refBukuBesar = new \ReflectionMethod($controller, 'ledgerData');
        $refBukuBesar->setAccessible(true);
        $bukuBesar = $refBukuBesar->invoke($controller, 2026, null);
        $piutangEntries = collect($bukuBesar['accounts'])->where('name', 'Piutang Karyawan');
        $this->assertCount(1, $piutangEntries, 'Piutang Karyawan harus SATU baris, bukan pecah dua (regresi N1 Tahap A)');
        $this->assertSame(0.0, $piutangEntries->first()['balance']);

        // 6. Batalkan — semuanya pulih
        $payroll = Payroll::where('period', '2026-07')->firstOrFail();
        $admin->post(route('payrolls.cancel', $payroll))->assertSessionHasNoErrors();

        $this->assertSame('draft', $payroll->fresh()->status);
        $this->assertSame(1_000_000.0, $karyawan->fresh()->advances()->first()->sisa(), 'sisa kas bon pulih tanpa perbaikan manual (D10)');
    }
}
