<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    /**
     * Sama seperti helper di PayrollDraftBuilderTest/PayrollProcessorTest:
     * kas bon butuh FinTransaction (kolom fin_transaction_id wajib).
     */
    private function beriKasBon(Employee $karyawan, float $amount, string $date, CashAccount $kas): EmployeeAdvance
    {
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

    public function test_buka_periode_menampilkan_draft_tanpa_menyimpan(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);

        $this->actingAs($this->makeAdmin())->get(route('payrolls.show', '2026-07'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('draft', 1));

        $this->assertDatabaseCount('payrolls', 0);
    }

    public function test_bayar_membuat_payroll_berstatus_paid(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payrolls', ['period' => '2026-07', 'status' => 'paid']);
    }

    public function test_periode_yang_sama_tidak_bisa_dibayar_dua_kali_tanpa_batalkan(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);

        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id])
            ->assertStatus(422);
    }

    public function test_batalkan_mengembalikan_status_draft(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);
        $payroll = Payroll::where('period', '2026-07')->firstOrFail();

        $this->actingAs($admin)->post(route('payrolls.cancel', $payroll))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', $payroll->fresh()->status);
    }

    /**
     * Item penutup review Task 5 (#1): PayrollProcessor::pay() sendiri TIDAK
     * memvalidasi cash_account_id — kalau ID tidak ada di tabel cash_accounts,
     * tanpa validasi di controller ini akan lempar QueryException mentah
     * (500). Controller wajib menolaknya rapi lewat validasi request.
     */
    public function test_bayar_dengan_cash_account_id_tidak_valid_ditolak_rapi_bukan_500(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);

        $response = $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => 999999,
        ]);

        $response->assertSessionHasErrors('cash_account_id');
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseCount('payrolls', 0);
    }

    /**
     * Item penutup review Task 5 (#3): PayrollProcessor::cancel() sendiri
     * adalah no-op aman untuk payroll berstatus draft (belum pernah dibayar),
     * tapi dari sisi UX controller harus menolak dengan pesan jelas — supaya
     * user tidak bisa "Batalkan" sesuatu yang belum pernah dibayar.
     */
    public function test_batalkan_ditolak_untuk_payroll_berstatus_draft(): void
    {
        $payroll = Payroll::create(['period' => '2026-07', 'status' => 'draft']);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.cancel', $payroll))
            ->assertStatus(422);

        $this->assertSame('draft', $payroll->fresh()->status);
    }

    /**
     * D6: kas bon otomatis boleh diturunkan admin sebelum bayar (kasus nyata
     * "bulan ini potong separuh dulu"). `overrides[]` mengirim
     * employee_advance_id -> nominal potongan baru; net_amount server harus
     * ikut naik sebesar selisihnya, dan payroll_item_lines kas bon mencatat
     * nominal yang SUDAH diturunkan, bukan nominal otomatis.
     */
    public function test_override_menurunkan_potongan_kas_bon_menaikkan_net_amount_dan_tercatat_di_line(): void
    {
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $budi = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kasBon = $this->beriKasBon($budi, 1_000_000, '2026-07-10', $kas);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
            'overrides' => [
                ['employee_advance_id' => $kasBon->id, 'potongan' => 500_000],
            ],
        ])->assertSessionHasNoErrors();

        $item = PayrollItem::where('employee_id', $budi->id)->firstOrFail();
        $this->assertSame(3_500_000.0, (float) $item->net_amount, 'net_amount naik karena potongan diturunkan 500rb');

        $line = PayrollItemLine::where('employee_advance_id', $kasBon->id)->where('kind', 'kas_bon')->firstOrFail();
        $this->assertSame(500_000.0, (float) $line->amount, 'payroll_item_lines mencatat nominal yang sudah diturunkan, bukan nominal otomatis');
    }

    /**
     * D6: override HANYA boleh menurunkan potongan, tidak pernah menaikkan
     * melebihi hasil FIFO otomatis — kalau tidak dibatasi, client bisa
     * memaksa potongan lebih besar dari sisa kas bon (atau lebih besar dari
     * gaji bersih yang tersedia), yang sudah dijamin aman oleh
     * PayrollDraftBuilder. Nominal client yang lebih besar diabaikan diam-diam,
     * hasil akhir tetap dibatasi ke nilai otomatis.
     */
    public function test_override_lebih_besar_dari_otomatis_dibatasi_ke_nilai_otomatis(): void
    {
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $budi = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kasBon = $this->beriKasBon($budi, 1_000_000, '2026-07-10', $kas);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
            'overrides' => [
                ['employee_advance_id' => $kasBon->id, 'potongan' => 2_000_000],
            ],
        ])->assertSessionHasNoErrors();

        $item = PayrollItem::where('employee_id', $budi->id)->firstOrFail();
        $this->assertSame(3_000_000.0, (float) $item->net_amount, 'net_amount TIDAK ikut turun — override lebih besar diabaikan');

        $line = PayrollItemLine::where('employee_advance_id', $kasBon->id)->where('kind', 'kas_bon')->firstOrFail();
        $this->assertSame(1_000_000.0, (float) $line->amount, 'potongan tercatat tetap nilai otomatis, bukan nilai client');
    }

    /**
     * D6: employee_advance_id yang valid di tabel employee_advances TAPI
     * tidak muncul di draft periode ini (mis. milik karyawan nonaktif, jadi
     * tidak pernah masuk baris draft manapun) harus diabaikan dengan aman —
     * tidak boleh crash, dan tidak boleh memengaruhi potongan karyawan lain
     * yang benar.
     */
    public function test_override_dengan_employee_advance_id_di_luar_draft_diabaikan_dengan_aman(): void
    {
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $budi = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kasBonBudi = $this->beriKasBon($budi, 1_000_000, '2026-07-10', $kas);

        // Rudi nonaktif -> tidak pernah masuk draft periode manapun, tapi
        // employee_advance_id miliknya tetap ID yang VALID di tabel.
        $rudi = Employee::create(['name' => 'Rudi', 'base_salary' => 3_000_000, 'is_active' => false]);
        $kasBonRudi = $this->beriKasBon($rudi, 500_000, '2026-07-05', $kas);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
            'overrides' => [
                ['employee_advance_id' => $kasBonRudi->id, 'potongan' => 100_000],
            ],
        ])->assertSessionHasNoErrors();

        $item = PayrollItem::where('employee_id', $budi->id)->firstOrFail();
        $this->assertSame(3_000_000.0, (float) $item->net_amount, 'potongan Budi tidak terpengaruh override milik Rudi');

        $line = PayrollItemLine::where('employee_advance_id', $kasBonBudi->id)->where('kind', 'kas_bon')->firstOrFail();
        $this->assertSame(1_000_000.0, (float) $line->amount);

        // Rudi nonaktif -> tidak masuk payroll_items sama sekali (hanya Budi).
        $this->assertDatabaseCount('payroll_items', 1);
    }
}
