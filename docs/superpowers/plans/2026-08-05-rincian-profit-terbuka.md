# Rincian Profit Tetap Terbuka — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow Rincian Profit (InvoiceItem rows) to stay editable forever — even after an invoice is approved and locked into Keuangan — for both sales and accountant/admin, with every post-approval add/edit/delete logged to tour history, and a delete guard so an item that already has a Bill can't silently orphan it.

**Architecture:** Remove the single `ensureEditable()` approval gate from `InvoiceItemController` (the 5 CRUD actions become always-allowed), add a Bill-existence guard specifically to `destroy()`, add history logging on every mutation that happens post-approval, widen the 5 routes to include the `accountant` role, then extract the currently-duplicated editable-table UI into one shared `RincianProfitEditor.vue` component used by both `InvoicesPanel.vue` (sales, Tours page) and `Finance/Tour.vue` (accountant, Finance page) — replacing the old `v-if="isApproved(inv)"`/`v-else` split in both files.

**Tech Stack:** Laravel 12 (PHPUnit feature tests), Vue 3 `<script setup>`, Inertia.js, shadcn-vue UI components.

## Global Constraints

- No time cutoff: Rincian Profit is editable at ANY invoice status, forever (spec D1).
- Both sales and accountant/admin can edit (spec D2) — enforced via route middleware, not client-side role checks (page-level role gating already exists: Tours pages are `role:admin,sales`, Finance pages are `role:admin,accountant`).
- Deleting an `InvoiceItem` that already has a `Bill` (`bills.invoice_item_id`) is rejected; editing that same item is still allowed (spec D3).
- New items added post-approval do NOT auto-create a Bill draft — that only happens once, at `InvoiceController::approve()` time via `Bill::createMissingFromInvoice()`. Post-approval additions rely on the existing manual "+ Bill" button (spec D4).
- Every add/edit/delete that happens AFTER approval logs exactly one line to `tour.histories` via the existing mechanism; mutations BEFORE approval are not logged (spec D5).
- Invoice's own `exchange_rate` lock at `approve()` is untouched — different concern (spec D6).
- Customer billing (`unit_price × pax`, proforma) is untouched by this change (spec D7).
- Extract ONE shared Vue component (`RincianProfitEditor.vue`) rather than duplicating logic across `InvoicesPanel.vue` and `Finance/Tour.vue` (spec D8).
- The shared component covers the CORE editable table (inline edit, autosave, add plain line, delete). The existing product-catalog picker dialog (`openAddDialog`/`pickProduct`/`confirmAddDated`), the clipboard paste dialog (`openPasteDialog`/`submitPaste`), and the viewport-positioned date popover (`toggleDatePopover`) stay in `InvoicesPanel.vue` as sales-only power tools layered alongside the shared component — they already POST to the same backend endpoints, so they keep working once the endpoints are unlocked. This is a deliberate scoping decision made during planning (not in the original spec) to avoid mistranscribing complex clipboard-parsing/viewport-math code into this plan; state it plainly rather than hide it.
- `copyProfitTable()` in `InvoicesPanel.vue` currently reads live-unsaved values via `itemForms[item.id] ?? item`. Since `itemForms` moves into the child component, `copyProfitTable()` falls back to reading `item` directly — meaning a copy click made mid-edit, before autosave flushes, copies the last-SAVED values rather than what's mid-typing. This is a minor accepted trade-off, not a regression to fix elsewhere.
- Follow existing code style: Indonesian comments/strings for user-facing text and business-logic explanations, English for generic scaffolding.

---

### Task 1: Backend — unlock the 5 InvoiceItem actions, add Bill-delete guard, log history

**Files:**
- Modify: `app/Http/Controllers/InvoiceItemController.php` (full file, 148 lines)
- Modify: `tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php:123-134` (remove one test method)
- Create: `tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php`

**Interfaces:**
- Consumes: `App\Models\Bill` (`Bill::where('invoice_item_id', ...)->exists()`), `App\Models\Invoice` (`is_approved`, `tour`), `App\Models\InvoiceItem` (`decimal:2` casts on `unit_cost`/`unit_sell`, `date:Y-m-d` on `start_date`/`end_date`), `TourHistory` via `$tour->histories()->create([...])` (fillable: `tour_id, status_snapshot, type, description, created_by` — `tour_id` filled automatically by the relation).
- Produces: unlocked `store()`, `bulkStore()`, `bulkUpdate()`, `update()`, `destroy()` — no behavior change to their request validation or response shape (still `redirect()->back()`), just the approval gate removed and history logging added. Later tasks (routes, Vue) depend on these 5 actions accepting requests regardless of `invoice.is_approved`.

- [ ] **Step 1: Write the failing tests in a new file**

Create `tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Bill;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Kebalikan dari kunci lama: sejak keputusan D1 (lihat
 * docs/superpowers/specs/2026-08-05-rincian-profit-tetap-terbuka-design.md),
 * Rincian Profit TIDAK PERNAH terkunci oleh status approval invoice — sales
 * maupun akuntan/admin bisa menambah, mengubah, atau menghapus item kapan
 * pun. Test ini menutup jalur itu; ApprovedInvoiceFrozenTest tetap menutup
 * jalur proforma/baseline/approve/delete-invoice yang TIDAK berubah.
 */
class RincianProfitTetapTerbukaTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function approvedInvoice(): Invoice
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);

        return $this->approveInvoice($invoice);
    }

    public function test_item_bisa_ditambahkan_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Sisipan setelah disetujui', 'unit_cost' => 1, 'unit_sell' => 2]],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertCount(1, $invoice->fresh()->items);
    }

    public function test_item_bisa_diubah_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000])
            ->assertSessionDoesntHaveErrors();

        $this->assertEquals('600000.00', (string) $item->fresh()->unit_cost);
    }

    public function test_item_bisa_dihapus_setelah_invoice_disetujui_bila_belum_ada_bill(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($item->fresh());
    }

    public function test_item_yang_sudah_punya_bill_tidak_bisa_dihapus(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);
        Bill::create([
            'tour_id' => $invoice->tour_id, 'invoice_item_id' => $item->id,
            'description' => 'Hotel test', 'category' => 'hotel',
            'date' => now()->toDateString(), 'amount' => 500_000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item))
            ->assertSessionHasErrors('invoice');

        $this->assertNotNull($item->fresh(), 'Item tidak boleh terhapus bila sudah punya Bill');
    }

    public function test_mengubah_item_yang_sudah_punya_bill_tetap_boleh(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);
        Bill::create([
            'tour_id' => $invoice->tour_id, 'invoice_item_id' => $item->id,
            'description' => 'Hotel test', 'category' => 'hotel',
            'date' => now()->toDateString(), 'amount' => 500_000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 550_000])
            ->assertSessionDoesntHaveErrors();

        $this->assertEquals('550000.00', (string) $item->fresh()->unit_cost);
    }

    public function test_menambah_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Extra bed pasca approve', 'unit_cost' => 100_000, 'unit_sell' => 150_000]],
            ]);

        $this->assertTrue(
            $invoice->tour->histories()->where('description', 'like', '%Extra bed pasca approve%')->exists(),
            'Penambahan item pasca-approve harus tercatat di riwayat tour'
        );
    }

    public function test_mengubah_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000]);

        $this->assertTrue(
            $invoice->tour->histories()
                ->where('description', 'like', '%diubah pasca-approve%')
                ->where('description', 'like', '%unit_cost%')
                ->exists(),
            'Perubahan item pasca-approve harus tercatat di riwayat tour dengan menyebut field yang berubah'
        );
    }

    public function test_menghapus_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel dihapus lagi', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item));

        $this->assertTrue(
            $invoice->tour->histories()->where('description', 'like', '%Hotel dihapus lagi%')->exists(),
            'Penghapusan item pasca-approve harus tercatat di riwayat tour'
        );
    }

    public function test_edit_item_sebelum_disetujui_tidak_menambah_riwayat_tour(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);
        $item    = $invoice->items()->create([
            'description' => 'Belum disetujui', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $historiesSebelum = $tour->histories()->count();

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000]);

        $this->assertEquals(
            $historiesSebelum,
            $tour->histories()->count(),
            'Edit sebelum invoice disetujui tidak boleh menambah riwayat tour'
        );
    }

    public function test_tagihan_customer_tidak_berubah_oleh_edit_rincian_profit_pasca_approve(): void
    {
        $invoice = $this->approvedInvoice();
        $totalSebelum = $invoice->total;

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Item baru', 'unit_cost' => 1, 'unit_sell' => 9_999_999]],
            ]);

        $this->assertEquals(
            $totalSebelum,
            $invoice->fresh()->total,
            'Rincian Profit adalah internal — menambah item tidak boleh menggeser tagihan customer (unit_price × pax)'
        );
    }
}
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `php artisan test tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php`
Expected: FAIL — most assertions fail because `ensureEditable()` still rejects every request with `assertSessionHasErrors('invoice')` where the test now expects success, and no history rows are created yet.

- [ ] **Step 3: Remove the now-contradicted test from `ApprovedInvoiceFrozenTest.php`**

In `tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php`, delete the `test_item_tidak_bisa_diubah_setelah_invoice_disetujui()` method (current lines 123-134):

```php
    public function test_item_tidak_bisa_diubah_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Sisipan setelah disetujui', 'unit_cost' => 1, 'unit_sell' => 2]],
            ])
            ->assertSessionHasErrors('invoice');

        $this->assertCount(0, $invoice->fresh()->items);
    }

```

This method asserted the OLD behavior (Rincian Profit frozen after approval), which is exactly what this feature reverses per spec D1. Its replacement (opposite assertion) now lives in `RincianProfitTetapTerbukaTest::test_item_bisa_ditambahkan_setelah_invoice_disetujui()`. The rest of `ApprovedInvoiceFrozenTest` — proforma/baseline/approve recompute-lock, invoice-deletion-lock, the deliberate `syncProformaTotal()` vulnerability note — protects unrelated behavior (D6/D7) and must stay untouched and green.

- [ ] **Step 4: Rewrite `InvoiceItemController.php` in full**

Replace the entire contents of `app/Http/Controllers/InvoiceItemController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceItemController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty'        => 'integer|min:1',
            'nights'     => 'integer|min:1',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Hotel/transport/guide wajib punya jadwal — tampil ke tim lapangan di MyJobs
        if (in_array($product->type, InvoiceItem::DATED_TYPES, true)) {
            $request->validate([
                'start_date' => 'required|date',
                'end_date'   => 'required|date|after_or_equal:start_date',
            ]);
        }

        $item = InvoiceItem::fromProduct($product, [
            'invoice_id' => $invoice->id,
            'qty'        => $request->input('qty', 1),
            'nights'     => $request->input('nights', 1),
            'start_date' => $request->input('start_date'),
            'end_date'   => $request->input('end_date'),
            'sort_order' => (int) $invoice->items()->max('sort_order') + 1,
        ]);

        $item->save();

        $this->catatRiwayatPascaApprove(
            $invoice,
            "Item Rincian Profit ditambahkan pasca-approve oleh {$this->namaPengguna()}: {$item->description}."
        );

        return redirect()->back();
    }

    /** Tempel massal dari clipboard (Excel/Sheets) — item manual tanpa product_id. */
    public function bulkStore(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'items'                => 'required|array|min:1|max:200',
            'items.*.description'  => 'required|string|max:500',
            'items.*.product_type' => 'nullable|string|max:50',
            'items.*.qty'          => 'nullable|integer|min:1',
            'items.*.nights'       => 'nullable|integer|min:1',
            'items.*.unit_cost'    => 'nullable|numeric|min:0',
            'items.*.unit_sell'    => 'nullable|numeric|min:0',
        ]);

        $sort = (int) $invoice->items()->max('sort_order');

        foreach ($data['items'] as $row) {
            $invoice->items()->create([
                'product_type' => $row['product_type'] ?? null,
                'description'  => $row['description'],
                'qty'          => $row['qty'] ?? 1,
                'nights'       => $row['nights'] ?? 1,
                'unit_cost'    => $row['unit_cost'] ?? 0,
                'unit_sell'    => $row['unit_sell'] ?? 0,
                'sort_order'   => ++$sort,
            ]);
        }

        $jumlah = count($data['items']);
        $this->catatRiwayatPascaApprove(
            $invoice,
            "{$jumlah} item Rincian Profit ditambahkan pasca-approve oleh {$this->namaPengguna()} (tempel massal)."
        );

        return redirect()->back();
    }

    /** Autosave massal dari tabel Rincian Profit — satu request untuk semua baris yang berubah. */
    public function bulkUpdate(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'items'               => 'required|array|min:1|max:200',
            'items.*.id'          => 'required|integer',
            'items.*.qty'         => 'sometimes|integer|min:1',
            'items.*.nights'      => 'sometimes|integer|min:1',
            'items.*.description' => 'sometimes|nullable|string|max:500',
            'items.*.unit_cost'   => 'sometimes|numeric|min:0',
            'items.*.unit_sell'   => 'sometimes|numeric|min:0',
            'items.*.start_date'  => 'sometimes|nullable|date',
            'items.*.end_date'    => 'sometimes|nullable|date|after_or_equal:items.*.start_date',
        ]);

        $items = $invoice->items()
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->get()
            ->keyBy('id');

        foreach ($data['items'] as $row) {
            $item = $items->get($row['id']);
            if (! $item) {
                continue;
            }

            $perubahan = collect($row)->except('id')->all();
            $before    = $item->only(array_keys($perubahan));
            $item->update($perubahan);

            $ringkasan = $this->ringkasPerubahan($item, $before);
            $this->catatRiwayatPascaApprove(
                $invoice,
                "Item Rincian Profit diubah pasca-approve oleh {$this->namaPengguna()} ({$item->description}): {$ringkasan}."
            );
        }

        return redirect()->back();
    }

    public function update(Request $request, InvoiceItem $invoiceItem)
    {
        $data = $request->validate([
            'qty'         => 'sometimes|integer|min:1',
            'nights'      => 'sometimes|integer|min:1',
            'description' => 'sometimes|nullable|string|max:500',
            'unit_cost'   => 'sometimes|numeric|min:0',
            'unit_sell'   => 'sometimes|numeric|min:0',
            'sort_order'  => 'sometimes|integer|min:0',
            'start_date'  => 'sometimes|nullable|date',
            'end_date'    => 'sometimes|nullable|date|after_or_equal:start_date',
        ]);

        $before = $invoiceItem->only(array_keys($data));
        $invoiceItem->update($data);

        $ringkasan = $this->ringkasPerubahan($invoiceItem, $before);
        $this->catatRiwayatPascaApprove(
            $invoiceItem->invoice,
            "Item Rincian Profit diubah pasca-approve oleh {$this->namaPengguna()} ({$invoiceItem->description}): {$ringkasan}."
        );

        return redirect()->back();
    }

    public function destroy(InvoiceItem $invoiceItem)
    {
        if (Bill::where('invoice_item_id', $invoiceItem->id)->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.',
            ]);
        }

        $invoice = $invoiceItem->invoice;
        $label   = $invoiceItem->description ?: ($invoiceItem->product_type ?? 'item');

        $invoiceItem->delete();

        $this->catatRiwayatPascaApprove(
            $invoice,
            "Item Rincian Profit dihapus pasca-approve oleh {$this->namaPengguna()}: {$label}."
        );

        return redirect()->back();
    }

    /** Mencatat satu baris riwayat tour, HANYA bila invoice sudah disetujui (spec D5). */
    private function catatRiwayatPascaApprove(Invoice $invoice, string $keterangan): void
    {
        if (! $invoice->is_approved) {
            return;
        }

        $invoice->tour?->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $invoice->tour->status,
            'description'     => $keterangan,
            'created_by'      => $this->namaPengguna(),
        ]);
    }

    private function namaPengguna(): string
    {
        return auth()->user()?->name ?? 'Sistem';
    }

    /** Ringkasan "field lama → baru" untuk field yang benar-benar berubah, dipakai di riwayat tour. */
    private function ringkasPerubahan(InvoiceItem $item, array $before): string
    {
        $label = [
            'qty' => 'qty', 'nights' => 'nights', 'description' => 'deskripsi',
            'unit_cost' => 'unit_cost', 'unit_sell' => 'unit_sell',
            'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai',
        ];

        $perubahan = [];
        foreach ($before as $field => $nilaiLama) {
            $nilaiBaru = $item->{$field};
            if ((string) $nilaiLama !== (string) $nilaiBaru) {
                $perubahan[] = ($label[$field] ?? $field) . " {$nilaiLama} → {$nilaiBaru}";
            }
        }

        return $perubahan ? implode(', ', $perubahan) : 'tidak ada field yang berubah';
    }
}
```

Note what was removed: the `ensureEditable()` method and every call to it (previously in `store()`, `bulkStore()`, `bulkUpdate()`, `update()`, `destroy()`). This is the entire mechanism being reversed.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php`
Expected: PASS — all tests in both files green.

- [ ] **Step 6: Run the full invoice/finance test suite to check for regressions**

Run: `php artisan test --filter=Invoice && php artisan test tests/Feature/Finance`
Expected: PASS — no other test relies on Rincian Profit being locked post-approval.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/InvoiceItemController.php tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php
git commit -m "feat: biarkan Rincian Profit tetap bisa diedit setelah invoice disetujui"
```

---

### Task 2: Backend — widen the 5 routes to include `accountant`, add `accountantUser()` fixture

**Files:**
- Modify: `routes/web.php:165-169` (extract these 5 lines out of the surrounding `role:admin,sales` group into a new dedicated group)
- Modify: `tests/Support/CreatesSalesFixtures.php` (add `accountantUser()` helper)
- Modify: `tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php` (add a test using the new helper)

**Interfaces:**
- Consumes: existing `role` middleware (already used elsewhere in `routes/web.php`, e.g. `role:admin,sales,accountant` at line 223), `Tests\Support\CreatesSalesFixtures::salesUser()` as the pattern to mirror.
- Produces: `accountantUser(): User` fixture helper, usable by any future test needing an accountant-role user.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php`, add:

```php
    public function test_akuntan_bisa_menambah_item_setelah_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->accountantUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Koreksi biaya oleh akuntan', 'unit_cost' => 10_000, 'unit_sell' => 0]],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertCount(1, $invoice->fresh()->items);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=test_akuntan_bisa_menambah_item_setelah_disetujui`
Expected: FAIL — `accountantUser()` doesn't exist yet on the trait (`Undefined method`), and even once added, the route middleware doesn't allow the `accountant` role yet (403/redirect instead of success).

- [ ] **Step 3: Add `accountantUser()` to the shared fixture trait**

In `tests/Support/CreatesSalesFixtures.php`, add this method right after `salesUser()` (after line 33):

```php

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
```

- [ ] **Step 4: Widen the routes**

In `routes/web.php`, remove these 5 lines from inside the `role:admin,sales` group (currently lines 165-169):

```php
        Route::post('/invoices/{invoice}/items',      [InvoiceItemController::class, 'store'])->name('invoice-items.store');
        Route::post('/invoices/{invoice}/items/bulk', [InvoiceItemController::class, 'bulkStore'])->name('invoice-items.bulk');
        Route::patch('/invoices/{invoice}/items/bulk', [InvoiceItemController::class, 'bulkUpdate'])->name('invoice-items.bulk-update');
        Route::patch('/invoice-items/{invoiceItem}',  [InvoiceItemController::class, 'update'])->name('invoice-items.update');
        Route::delete('/invoice-items/{invoiceItem}', [InvoiceItemController::class, 'destroy'])->name('invoice-items.destroy');
```

leaving the surrounding `// Invoice — dibuat & disetujui sales (alur 2 tahap: patokan → rincian → setujui)` comment and its 6 `invoices.*` routes (store/proforma/baseline/due-date/approve/destroy) in place inside the `role:admin,sales` group — those stay sales/admin-only, only the item-level CRUD is widening.

Then add a new dedicated group immediately after the `role:admin,sales` group closes (after the `});` that currently ends at line 197), right before the `// Reminders — admin + sales` comment:

```php

    // Rincian Profit (InvoiceItem) tetap bisa diedit setelah invoice disetujui —
    // sales DAN akuntan/admin (spec 2026-08-05-rincian-profit-tetap-terbuka-design.md D2).
    Route::middleware('role:admin,sales,accountant')->group(function () {
        Route::post('/invoices/{invoice}/items',       [InvoiceItemController::class, 'store'])->name('invoice-items.store');
        Route::post('/invoices/{invoice}/items/bulk',  [InvoiceItemController::class, 'bulkStore'])->name('invoice-items.bulk');
        Route::patch('/invoices/{invoice}/items/bulk', [InvoiceItemController::class, 'bulkUpdate'])->name('invoice-items.bulk-update');
        Route::patch('/invoice-items/{invoiceItem}',   [InvoiceItemController::class, 'update'])->name('invoice-items.update');
        Route::delete('/invoice-items/{invoiceItem}',  [InvoiceItemController::class, 'destroy'])->name('invoice-items.destroy');
    });
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=RincianProfitTetapTerbukaTest`
Expected: PASS — all tests in the file green, including the new accountant test.

- [ ] **Step 6: Run full route-sensitive suites to check for regressions**

Run: `php artisan route:list --name=invoice-items` (sanity-check the 5 routes still exist under the new middleware) then `php artisan test --filter=Invoice`
Expected: route list shows all 5 `invoice-items.*` names; full invoice test suite still green.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php tests/Support/CreatesSalesFixtures.php tests/Feature/Invoice/RincianProfitTetapTerbukaTest.php
git commit -m "feat: izinkan role accountant ikut mengedit Rincian Profit"
```

---

### Task 3: Frontend — shared `RincianProfitEditor.vue` component

**Files:**
- Create: `resources/js/Components/Tours/RincianProfitEditor.vue`

**Interfaces:**
- Consumes: `@/Components/ui/button` (`Button`), `@/Components/ui/input` (`Input`), `@/Components/ui/dialog` (`Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`), `@/lib/confirm` (`confirm`), `@/lib/fmt` (`fmtRp`), `@/lib/tourConstants` (`TYPE_LABELS`), Inertia `router` (`patch`/`delete`/`post`/`on`), backend routes `invoice-items.bulk-update`, `invoice-items.destroy`, `invoice-items.bulk` (all from Task 1/2, now open to sales+accountant+admin regardless of approval status).
- Produces: `RincianProfitEditor` component with a single prop `invoice: Object` (must include `id`, `items[]`, and optionally `approved_at`/`is_approved` purely for the cosmetic "tercatat di riwayat" hint — no behavior branches on it). No emits — the component is fully self-contained; the parent just passes `:invoice="inv"` and lets Inertia's partial reload (`only: ['tour']`) refresh `invoice.items` after any mutation, which the component's own `watch()` picks up. Later tasks (4, 5) consume this component directly.

- [ ] **Step 1: Create the component**

```vue
<script setup>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({
    invoice: { type: Object, required: true },
})

// ── State per-item, di-scope ke SATU invoice ini saja ───────────────────────
const itemForms = reactive({})
const dirtyIds  = ref(new Set())
const saveState = ref('idle')
const errorMsg  = ref('')

watch(
    () => props.invoice.items,
    (items) => {
        const ids = []
        ;(items ?? []).forEach(item => {
            ids.push(item.id)
            if (dirtyIds.value.has(item.id)) return
            itemForms[item.id] = {
                qty: item.qty, nights: item.nights,
                description: item.description ?? '',
                unit_cost: item.unit_cost, unit_sell: item.unit_sell,
                start_date: item.start_date ? String(item.start_date).slice(0, 10) : '',
                end_date:   item.end_date   ? String(item.end_date).slice(0, 10)   : '',
            }
        })
        Object.keys(itemForms).forEach(id => { if (!ids.includes(Number(id))) delete itemForms[id] })
    },
    { immediate: true, deep: true }
)

let saveTimer = null
let pendingAction = null

function markDirty(itemId) {
    dirtyIds.value.add(itemId)
    saveState.value = 'pending'
    clearTimeout(saveTimer)
    saveTimer = setTimeout(flushSaves, 1500)
}

function flushSaves() {
    clearTimeout(saveTimer)
    if (saveState.value === 'saving') return
    if (!dirtyIds.value.size) {
        const act = pendingAction
        pendingAction = null
        act?.()
        return
    }

    const rows = [...dirtyIds.value].filter(id => itemForms[id]).map(id => ({ id, ...itemForms[id] }))
    dirtyIds.value = new Set()
    saveState.value = 'saving'
    errorMsg.value = ''

    router.patch(route('invoice-items.bulk-update', props.invoice.id), { items: rows }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => { saveState.value = dirtyIds.value.size ? 'pending' : 'saved' },
        onError: (errors) => {
            errorMsg.value = Object.values(errors ?? {})[0] ?? 'Terjadi kesalahan.'
            rows.forEach(r => dirtyIds.value.add(r.id))
            saveState.value = 'pending'
        },
        onFinish: () => {
            if (saveState.value === 'saving') saveState.value = 'idle'
            if (dirtyIds.value.size) {
                saveTimer = setTimeout(flushSaves, 300)
            } else {
                const act = pendingAction
                pendingAction = null
                act?.()
            }
        },
    })
}

function afterFlush(action) {
    if (!dirtyIds.value.size && saveState.value !== 'saving') return action()
    pendingAction = action
    flushSaves()
}

async function deleteItem(itemId) {
    if (!(await confirm({ title: 'Hapus item ini?', confirmLabel: 'Hapus' }))) return

    errorMsg.value = ''
    dirtyIds.value.delete(itemId)
    afterFlush(() => {
        router.delete(route('invoice-items.destroy', itemId), {
            preserveScroll: true,
            only: ['tour'],
            onError: (errors) => {
                // Guard "item sudah punya Bill" (lihat InvoiceItemController::destroy) muncul di sini.
                errorMsg.value = Object.values(errors ?? {})[0] ?? 'Item tidak bisa dihapus.'
            },
        })
    })
}

function lineSellLocal(itemId) {
    const f = itemForms[itemId]
    if (!f) return 0
    return (Number(f.qty) || 0) * (Number(f.nights) || 0) * (Number(f.unit_sell) || 0)
}

function handleBeforeUnload(e) {
    if (dirtyIds.value.size || saveState.value === 'saving') {
        e.preventDefault()
        e.returnValue = ''
    }
}

let stopRouterGuard = null
onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
    stopRouterGuard = router.on('before', () => {
        if (dirtyIds.value.size || saveState.value === 'saving') {
            return window.confirm('Ada perubahan Rincian Profit yang belum tersimpan. Tetap pindah halaman?')
        }
    })
})
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    stopRouterGuard?.()
    clearTimeout(saveTimer)
})

// ── Tambah item manual (deskripsi bebas, tanpa katalog produk) ─────────────
// Katalog produk & tempel-dari-clipboard tetap jadi fitur khusus sales di
// InvoicesPanel.vue (lihat plan Task 4) — di sini hanya jalur tambah paling
// sederhana yang dipakai bersama sales & akuntan.
const addOpen = ref(false)
const addForm = reactive({ description: '', qty: 1, nights: 1, unit_cost: 0, unit_sell: 0 })

function submitAdd() {
    router.post(route('invoice-items.bulk', props.invoice.id), {
        items: [{ ...addForm }],
    }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            addOpen.value = false
            Object.assign(addForm, { description: '', qty: 1, nights: 1, unit_cost: 0, unit_sell: 0 })
        },
        onError: (errors) => { errorMsg.value = Object.values(errors ?? {})[0] ?? 'Gagal menambah item.' },
    })
}
</script>

<template>
    <div class="rounded-md border">
        <div class="px-3 py-2 flex items-center justify-between border-b">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Rincian Profit (internal · IDR)</span>
            <span v-if="invoice.is_approved" class="text-[11px] text-amber-600">
                Perubahan di sini tercatat di riwayat tour.
            </span>
        </div>

        <p v-if="errorMsg" class="px-3 py-1.5 text-xs text-red-600 bg-red-50 border-b">{{ errorMsg }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted text-muted-foreground text-xs uppercase">
                        <th class="px-3 py-2 text-left">Deskripsi</th>
                        <th class="px-2 py-2 text-center w-24">Tanggal</th>
                        <th class="px-2 py-2 text-center w-14">Qty</th>
                        <th class="px-2 py-2 text-center w-14">Mlm</th>
                        <th class="px-2 py-2 text-right w-28">Cost/unit</th>
                        <th class="px-2 py-2 text-right w-28">Sell/unit</th>
                        <th class="px-2 py-2 text-right w-28">Total Jual</th>
                        <th class="px-2 py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!(invoice.items ?? []).length">
                        <td colspan="8" class="text-center py-6 text-muted-foreground">Belum ada item.</td>
                    </tr>
                    <tr v-for="item in invoice.items" :key="item.id" class="border-b last:border-0">
                        <td class="px-2 py-1">
                            <span class="block text-xs text-muted-foreground mb-0.5">
                                {{ TYPE_LABELS[item.product_type] ?? item.product_type ?? '—' }}
                            </span>
                            <Input v-model="itemForms[item.id].description" @input="markDirty(item.id)" class="text-sm" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="date" v-model="itemForms[item.id].start_date" @change="markDirty(item.id)"
                                class="border rounded px-1 py-0.5 text-xs w-full mb-1" />
                            <input type="date" v-model="itemForms[item.id].end_date" @change="markDirty(item.id)"
                                :min="itemForms[item.id].start_date" class="border rounded px-1 py-0.5 text-xs w-full" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].qty" @input="markDirty(item.id)" min="1"
                                class="w-14 border rounded px-1 py-1 text-center text-sm" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].nights" @input="markDirty(item.id)" min="1"
                                class="w-14 border rounded px-1 py-1 text-center text-sm" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].unit_cost" @input="markDirty(item.id)" min="0"
                                class="w-28 border rounded px-2 py-1 text-right text-sm font-mono" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].unit_sell" @input="markDirty(item.id)" min="0"
                                class="w-28 border rounded px-2 py-1 text-right text-sm font-mono" />
                        </td>
                        <td class="px-2 py-1 text-right font-mono text-sm font-medium">{{ fmtRp(lineSellLocal(item.id)) }}</td>
                        <td class="px-2 py-1 text-center">
                            <button type="button" @click="deleteItem(item.id)"
                                class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus item">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2 border-t flex items-center justify-between">
            <span v-if="saveState !== 'idle'" class="text-[11px]"
                :class="saveState === 'saved' ? 'text-green-600' : 'text-amber-600'">
                {{ saveState === 'pending' ? '● Ada perubahan…' : saveState === 'saving' ? '⏳ Menyimpan…' : '✓ Tersimpan' }}
            </span>
            <span v-else></span>
            <Button size="sm" variant="outline" @click="addOpen = true">+ Tambah Item</Button>
        </div>
    </div>

    <Dialog v-model:open="addOpen">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Tambah Item Rincian Profit</DialogTitle></DialogHeader>
            <form @submit.prevent="submitAdd" class="space-y-3 mt-2">
                <Input v-model="addForm.description" placeholder="Deskripsi" required />
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="addForm.qty" type="number" min="1" placeholder="Qty" />
                    <Input v-model="addForm.nights" type="number" min="1" placeholder="Malam" />
                    <Input v-model="addForm.unit_cost" type="number" min="0" placeholder="Cost/unit" />
                    <Input v-model="addForm.unit_sell" type="number" min="0" placeholder="Sell/unit" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" @click="addOpen = false">Batal</Button>
                    <Button type="submit">Tambah</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds with no errors referencing `RincianProfitEditor.vue` (it isn't imported anywhere yet, so this only checks the file itself is valid Vue/JS — a syntax or unresolved-import error would still surface here since Vite type-checks all `.vue` files it discovers... if it does not discover unimported files, skip straight to Task 4's build check instead and note that here).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Tours/RincianProfitEditor.vue
git commit -m "feat: komponen bersama RincianProfitEditor untuk sales & akuntan"
```

---

### Task 4: Frontend — wire `RincianProfitEditor` into `InvoicesPanel.vue`

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue`

**Interfaces:**
- Consumes: `RincianProfitEditor` from Task 3 (`import RincianProfitEditor from '@/Components/Tours/RincianProfitEditor.vue'`).
- Produces: `InvoicesPanel.vue` keeps its own product-catalog dialog, paste-from-clipboard dialog, `copyProfitTable()`, and the "+ Bill" affordance untouched; only the per-item editable-table block and its dedicated autosave state are removed in favor of the shared component.

- [ ] **Step 1: Remove item-editing state that moves into the child component**

In the `<script setup>` block, remove `itemForms` from the reactive-state declarations (was line 48: `const itemForms = reactive({})`) and remove the `dirtyIds`/`saveState` declarations (were lines 52-53) — these now live inside `RincianProfitEditor.vue`. Leave `proformaForms`, `exchangeForms`, `dueForms`, `profitOpen` untouched — they're unrelated concerns still owned by this file.

In the big tour-wide `watch()` block, remove the sub-loop that populates `itemForms` for each item (the item-keyed `itemForms[item.id] = {...}` assignment and its matching stale-id cleanup) — the child component now populates its own copy of this from its own `invoice` prop. Leave the `proformaForms`/`exchangeForms`/`dueForms`/`profitOpen` population in this same `watch()` untouched.

- [ ] **Step 2: Remove item-editing functions that moves into the child component**

Remove `markDirty()`, `flushSaves()`, `deleteItem()`, `lineSellLocal()`, and `itemInvoiceMap` (the computed that mapped item-id → invoice-id across the whole tour — it existed solely to support the old single-tour-wide autosave state, and has no other caller).

Keep `afterFlush()` but simplify it: its only remaining callers are `lockBaseline()` and `approve()`-related handlers, and since Rincian Profit items are never locked by approval anymore (D1), there's no more "unsaved item edit about to be lost forever" race to guard against. Find every call site that currently wraps `lockBaseline()`/`approve()` inside `afterFlush(() => ...)` and call the underlying `router.patch(...)`/`router.post(...)` directly instead, removing the `afterFlush` wrapper and then deleting the now-unused `afterFlush()` function itself.

- [ ] **Step 3: Fix `copyProfitTable()`'s fallback**

In `copyProfitTable(inv)`, change:

```js
const f = itemForms[item.id] ?? item
```

to:

```js
const f = item
```

`itemForms` no longer exists in this file, so the fallback becomes the only path — copying always reflects the last-saved item state (accepted trade-off, documented in Global Constraints).

- [ ] **Step 4: Replace the dual-branch item table with the shared component**

In the template, find the `<!-- ── Rincian profit internal (opsional, collapsible) ── -->` block. Inside it, replace this entire structure:

```html
                        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto rounded-md border" @scroll="dateOpenId = null">
                            <table class="w-full text-sm">
                                ...
                            </table>
                        </div>
                        <div v-if="!isApproved(inv)">
                            <Button size="sm" variant="outline" @click="openAddDialog(inv)">+ Tambah Produk</Button>
                        </div>
```

(the `<table>` containing both the `v-if="isApproved(inv)"` read-only `<template>` and the `v-else` editable `<template>`, plus the "+ Tambah Produk" button below it) with:

```html
                        <RincianProfitEditor :invoice="inv" />
                        <div class="flex items-center gap-2">
                            <Button v-if="!isApproved(inv)" size="sm" variant="outline" @click="openPasteDialog(inv)">📥 Tempel</Button>
                            <Button v-if="(inv.items ?? []).length" size="sm" variant="outline" @click="copyProfitTable(inv)">
                                {{ copiedProfit === inv.id ? '✓ Tersalin' : '📋 Salin' }}
                            </Button>
                            <Button v-if="!isApproved(inv)" size="sm" variant="outline" @click="openAddDialog(inv)">+ Tambah Produk</Button>
                        </div>
```

This keeps "Tempel"/"Salin"/"+ Tambah Produk" as sales-only extras (still gated `v-if="!isApproved(inv)"` where that gating already existed — Tempel and the catalog-add stay pre-approval-only conveniences since they're bulk/catalog power tools, not core editing; "Salin" stays available regardless of approval since copying was never gated on approval before). Also remove the now-redundant `saveState`-indicator markup that used to sit next to these buttons (the `<span v-if="!isApproved(inv) && saveState !== 'idle'">...</span>` block) — the shared component now renders its own save-state indicator internally.

Add the import at the top of `<script setup>`:

```js
import RincianProfitEditor from '@/Components/Tours/RincianProfitEditor.vue'
```

- [ ] **Step 5: Remove now-dead date-popover state**

The viewport-positioned date popover (`dateOpenId`, `datePopRef`, `toggleDatePopover`, `dateTitle`, `clearDates`, the `📅` button in the old editable row, and the `onDocMouseDown` click-outside handler registered in `onMounted`/`onBeforeUnmount`) existed solely to edit `itemForms[item.id].start_date`/`end_date` inline. Since inline date editing now lives in `RincianProfitEditor.vue` (as plain `<input type="date">` fields, not a popover), remove this dead state and its `onMounted`/`onBeforeUnmount` registration from `InvoicesPanel.vue`. Keep the `beforeunload`/`router.on('before')` unsaved-changes guard only if it also protects `proformaForms`/`exchangeForms`/`dueForms` dirty state — check whether those forms have their own "unsaved" tracking; if the guard's condition checks `dirtyIds.value.size` exclusively (item-only), remove the guard entirely from this file since `RincianProfitEditor.vue` now has its own equivalent guard scoped to its own items.

- [ ] **Step 6: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds with no errors. Specifically check there are no lingering references to `itemForms`, `dirtyIds`, `saveState`, `markDirty`, `flushSaves`, `deleteItem`, `lineSellLocal`, `itemInvoiceMap`, `afterFlush`, `dateOpenId`, `toggleDatePopover` anywhere left in the file — a leftover reference to a removed identifier will fail the Vue compiler with a clear "not defined" error.

- [ ] **Step 7: Manual verification in browser**

Start the dev server (`npm run dev` or existing local setup), open a Tour with an approved invoice as a sales user, and confirm:
- Rincian Profit table is now editable (previously read-only) — edit a `unit_cost`, see "● Ada perubahan…" then "✓ Tersimpan".
- Delete an item with no Bill — succeeds. Manually create a Bill for another item (via existing "+ Bill" UI in Finance/Tour), then try deleting that item from Tours — see the rejection message from Task 1's guard.
- "📥 Tempel", "📋 Salin", "+ Tambah Produk" still work as before.
- Add a fresh item via the shared component's own "+ Tambah Item" — appears in the table.
- Open the tour's history tab — confirm one new history line per add/edit/delete performed above.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: pakai RincianProfitEditor bersama di InvoicesPanel, hapus kunci pasca-approve"
```

---

### Task 5: Frontend — wire `RincianProfitEditor` into `Finance/Tour.vue`

**Files:**
- Modify: `resources/js/Pages/Finance/Tour.vue`

**Interfaces:**
- Consumes: `RincianProfitEditor` from Task 3.
- Produces: accountants/admins viewing a tour's Finance page can now edit Rincian Profit directly (previously 100% read-only), through the exact same backend endpoints already widened in Task 2.

- [ ] **Step 1: Replace the static read-only block**

In `resources/js/Pages/Finance/Tour.vue`, find the `<!-- Rincian Profit (internal, read-only untuk akuntan) -->` block (currently lines 419-479ish), which currently renders:
- A collapsible header with profit/margin summary (`profitOpen[inv.id]`)
- A fully static `<table>` of items (no inputs, no delete button)
- A summary footer (`Total Cost`, `Total Jual`/`Total Tagihan`, `Profit`)

Replace the collapsible-header + static-table portion with:

```html
                        <!-- Rincian Profit (internal, akuntan bisa mengedit langsung) -->
                        <div v-if="inv.items?.length || true" class="mt-3">
                            <RincianProfitEditor :invoice="inv" />
                        </div>
```

Keep the summary footer block (`Total Cost (Modal)` / `Total Tagihan Customer / Total Jual Item` / `Profit`) exactly as-is below it — it reads from `invTotalCost(inv)`, `invTotalSell(inv)`, `invProfit(inv)`, `invMargin(inv)` (this file's own computed helpers over `props.salesLine`), which are unaffected by this change and still need to render regardless of whether `inv.items` is empty (so an accountant can still see "+ Tambah Item" even when there are zero items yet — hence dropping the old `v-if="inv.items?.length"` gate that hid the whole block, including its own "add" capability, when the tour had no items at all).

Add the import at the top of `<script setup>`:

```js
import RincianProfitEditor from '@/Components/Tours/RincianProfitEditor.vue'
```

- [ ] **Step 2: Remove now-unused per-item computed helpers if they have no other callers**

Check whether `invTotalCost`, `invTotalSell`, `invProfit`, `invMargin` (this file's own versions) are used anywhere else in `Finance/Tour.vue` besides the summary footer kept in Step 1 — if the footer is the only caller, keep them as-is (still needed). Do NOT remove them; they're independent from the table rendering and still required for the footer summary.

- [ ] **Step 3: Verify the build compiles**

Run: `npm run build`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual verification in browser**

Log in as an accountant (or admin), open Finance → a tour with an approved invoice, and confirm:
- Rincian Profit table is now editable in this page too (previously fully static).
- Editing here produces the same autosave/history-logging behavior verified in Task 4.
- The Profit/Margin summary footer still updates correctly after edits (reload happens via Inertia's `only: ['tour']` partial reload, which should refresh `salesLine`/`tour` props feeding these computed helpers).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Finance/Tour.vue
git commit -m "feat: akuntan bisa mengedit Rincian Profit langsung dari Finance/Tour"
```

---

### Task 6: Whole-branch acceptance check

**Files:** none (verification only)

**Interfaces:** none — this task runs the full existing test/build pipeline and a final manual walkthrough spanning Tasks 1-5 together.

- [ ] **Step 1: Full backend test suite**

Run: `php artisan test`
Expected: PASS, zero failures. Pay special attention to `tests/Feature/Invoice/*` and `tests/Feature/Finance/*`.

- [ ] **Step 2: Full frontend build**

Run: `npm run build`
Expected: PASS, zero errors/warnings about unresolved imports or unused-but-referenced identifiers.

- [ ] **Step 3: `finance:snapshot` regression gate**

Run: `php artisan finance:snapshot` locally (or on dev-erp) and diff against the last saved patokan (see `storage/app/patokan-*.json`, per the established re-run methodology: re-serialize both old and new JSON through `json_decode`/`json_encode` with `JSON_PRETTY_PRINT`, then `diff`).
Expected: empty diff. This feature touches `InvoiceItem` mutation and `TourHistory` creation only — it must NOT change any financial report's numbers, since Rincian Profit was already excluded from `total`/proforma calculations before this change (D7) and remains so.

- [ ] **Step 4: End-to-end manual walkthrough**

As a sales user: open an approved invoice's Rincian Profit, add/edit/delete an item, confirm tour history shows the new entries, confirm the customer-facing invoice total is unchanged.
As an accountant: open the same tour via Finance, confirm the same edits are visible and further edits from this side also log to history.
Attempt to delete an item that has a Bill from both sides — confirm the rejection message appears and the item survives.

- [ ] **Step 5: Final commit (if any cleanup was needed)**

```bash
git status
```

If Steps 1-4 required no further code changes, there is nothing to commit here — this task is a pure verification gate.

---

## Self-Review

**1. Spec coverage:** D1 (no time cutoff) → Task 1 removes `ensureEditable()` entirely, not conditionally. D2 (sales + accountant) → Task 2 widens routes; page-level role gating already covers "who sees which page." D3 (Bill-delete guard) → Task 1 `destroy()`. D4 (no auto-Bill for new items) → nothing added that calls `Bill::createMissingFromInvoice()` outside the existing `approve()` call site; explicitly called out in Global Constraints. D5 (history logging, pre-approval exempt) → Task 1's `catatRiwayatPascaApprove()` early-returns on `! $invoice->is_approved`, tested explicitly. D6 (exchange_rate lock untouched) → no task touches `approve()`'s exchange-rate logic. D7 (customer billing untouched) → tested explicitly in Task 1's last test. D8 (shared component) → Tasks 3-5.

**2. Placeholder scan:** No TBD/TODO markers. Every step has literal, runnable code or an explicit shell command. Task 4/5's Vue edits are described as precise before/after replacements against real, previously-verified line content rather than vague "update the template" instructions.

**3. Type consistency:** `RincianProfitEditor` prop name (`invoice`) matches its usage in both Task 4 (`:invoice="inv"`) and Task 5 (`:invoice="inv"`). Route names (`invoice-items.bulk-update`, `invoice-items.destroy`, `invoice-items.bulk`) match Task 2's route definitions exactly. `catatRiwayatPascaApprove`/`ringkasPerubahan`/`namaPengguna` are used consistently by name across all 5 controller actions in Task 1's single rewritten file (no drift since it's one file, one step).

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-05-rincian-profit-terbuka.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
