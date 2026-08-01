<?php

namespace Tests\Unit\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Services\Payroll\PayrollDraftBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.4/D6: draft dihitung dari keadaan LIVE (karyawan aktif, komponen,
 * kas bon belum lunas), tidak pernah disimpan. Potongan kas bon otomatis =
 * yang lebih kecil antara sisa kas bon dan gaji bersih (D6).
 */
class PayrollDraftBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function beriKasBon(Employee $karyawan, float $amount, string $date): EmployeeAdvance
    {
        $kas = CashAccount::firstOrCreate(['name' => 'Kas Besar'], ['type' => 'cash']);
        $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

        $trx = FinTransaction::create([
            'date' => $date, 'direction' => 'out', 'fin_category_id' => $kategori->id,
            'cash_account_id' => $kas->id, 'amount' => $amount, 'source' => 'advance',
        ]);

        return EmployeeAdvance::create([
            'employee_id' => $karyawan->id, 'date' => $date, 'amount' => $amount,
            'fin_transaction_id' => $trx->id,
        ]);
    }

    public function test_draft_menyertakan_gaji_pokok_dan_komponen_tetap(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000]);

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertCount(1, $draft);
        $this->assertSame(4_000_000.0, $draft[0]['base_salary']);
        $this->assertSame(4_800_000.0, $draft[0]['net_amount']); // belum ada kas bon
    }

    public function test_potongan_kas_bon_otomatis_penuh_saat_gaji_cukup(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000]);
        $this->beriKasBon($karyawan, 1_000_000, '2026-07-10');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(3_800_000.0, $draft[0]['net_amount']);
        $this->assertSame(1_000_000.0, $draft[0]['advances'][0]['potongan']);
    }

    public function test_kas_bon_dipotong_maksimal_sebesar_gaji_bersih_bukan_lebih(): void
    {
        $karyawan = Employee::create(['name' => 'Joko', 'base_salary' => 3_000_000]);
        $this->beriKasBon($karyawan, 5_000_000, '2026-07-01');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(0.0, $draft[0]['net_amount']);
        $this->assertSame(3_000_000.0, $draft[0]['advances'][0]['potongan']);
    }

    public function test_kas_bon_yang_sudah_diberi_setelah_gajian_bulan_lalu_tetap_muncul_penuh(): void
    {
        $karyawan = Employee::create(['name' => 'Sari', 'base_salary' => 3_500_000]);
        $this->beriKasBon($karyawan, 500_000, '2026-06-28');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(500_000.0, $draft[0]['advances'][0]['sisa']);
    }

    public function test_karyawan_nonaktif_tidak_masuk_draft(): void
    {
        Employee::create(['name' => 'Mantan', 'base_salary' => 3_000_000, 'is_active' => false]);

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertCount(0, $draft);
    }
}
