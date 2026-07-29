# Ekspor Invoice ke .docx Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sales bisa mengunduh invoice sebagai `.docx` (selain PDF yang sudah ada), tampilannya semirip mungkin PDF termasuk watermark LUNAS/DP, supaya bisa diedit manual sebelum dipakai.

**Architecture:** Data yang mengisi PDF (mPDF) dan Word (PHPWord) diambil dari satu method bersama `InvoiceController::invoiceRenderData()` — hanya LAYOUT yang terduplikasi (Blade untuk PDF, class builder baru `App\Support\InvoiceDocxBuilder` untuk Word), bukan datanya. Route/controller baru menyalurkan data yang sama ke builder baru itu.

**Tech Stack:** Laravel 13, `phpoffice/phpword` (baru), GD (sudah aktif) untuk watermark, PHPUnit.

## Global Constraints

- Route baru: `GET /invoices/{invoice}/download-docx`, name `invoices.download-docx`, di grup middleware `role:admin,sales,accountant` — SAMA PERSIS dengan grup `invoices.preview`/`invoices.download` di `routes/web.php` (jangan buat grup baru).
- Content-Type respons: `application/vnd.openxmlformats-officedocument.wordprocessingml.document`.
- Nama file: `{$invoice->number}.docx`, `Content-Disposition: attachment` (tidak ada mode preview/inline untuk docx).
- Watermark tampil hanya bila `$paid > 0`; teks `"PAID IN FULL"` bila `$outstanding <= 0.005`, selain itu `"DEPOSIT RECEIVED"` — identik dengan kondisi di `InvoiceController::build()` (mPDF) saat ini.
- **Tidak ada perubahan apa pun pada tampilan/perilaku PDF invoice** — `resources/views/invoice.blade.php` TIDAK disentuh sama sekali di plan ini.
- Package baru: `phpoffice/phpword:^1.3`.
- Font watermark: `vendor/mpdf/mpdf/ttfonts/DejaVuSansCondensed-Bold.ttf` (sudah ada di repo lewat dependency mpdf, jangan tambah file font baru).
- Warna: border tabel utama `#000000`, judul "INVOICE" `#2e74b5`, baris proforma `#c0272d`.

---

### Task 1: Karakterisasi PDF + ekstrak `invoiceRenderData()`

**Files:**
- Create: `tests/Feature/Invoice/InvoicePdfCharacterizationTest.php`
- Modify: `app/Http/Controllers/InvoiceController.php:283-335` (method `build()`)

**Interfaces:**
- Produces: `private function invoiceRenderData(Invoice $invoice): array` di `InvoiceController` — dipakai Task 3 (`downloadDocx()`). Mengembalikan array dengan key: `invoice, company, bank, paymentTerms, logo, lines, unitPrice, pax, paid, outstanding, custName, party, resvDate, domain`.

- [ ] **Step 1: Tulis test karakterisasi PDF (mengunci perilaku SEBELUM refactor)**

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci perilaku PDF invoice (preview/download) SEBELUM invoiceRenderData()
 * diekstrak dari build() — membuktikan refactor Task 1 tidak mengubah apa pun
 * yang dikirim ke customer lewat PDF.
 */
class InvoicePdfCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_preview_mengembalikan_pdf_valid(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000);

        $response = $this->actingAs($this->salesUser())
            ->get(route('invoices.preview', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_mengembalikan_pdf_dengan_nama_file_bernomor_invoice(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000);

        $response = $this->actingAs($this->salesUser())
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment; filename="' . $invoice->number . '.pdf"',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_pdf_untuk_invoice_yang_sudah_ada_pembayaran_tetap_bisa_diunduh(): void
    {
        $invoice = $this->approveInvoice($this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000));
        $invoice->payments()->create(['date' => now()->toDateString(), 'amount' => $invoice->total]);

        $response = $this->actingAs($this->salesUser())
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan LULUS terhadap kode SEKARANG (sebelum refactor)**

Run: `php artisan test --filter=InvoicePdfCharacterizationTest`
Expected: `3 passed` — ini membuktikan test-nya sudah benar mengunci perilaku yang ada, bukan test yang salah tulis.

- [ ] **Step 3: Ekstrak `invoiceRenderData()` dari `build()`**

Baca dulu `app/Http/Controllers/InvoiceController.php:283-335` untuk melihat kode `build()` yang sekarang. Ganti isinya (bagian yang menyusun array untuk `view('invoice', [...])`, baris ~317-329) — pindahkan penghitungan array itu ke method privat baru, tambahkan juga turunan tampilan (`custName`, `party`, `resvDate`, `domain`) yang saat ini hanya dihitung inline di `@php` block Blade (baris 93-113 `invoice.blade.php`) — **Blade TIDAK diubah**, turunan ini ditambahkan semata untuk dipakai `InvoiceDocxBuilder` di Task 2:

```php
private function invoiceRenderData(Invoice $invoice): array
{
    $invoice->load(['tour.customer', 'items.product', 'payments']);

    $paid        = (float) $invoice->payments->sum('amount');
    $outstanding = (float) $invoice->total - $paid;
    $tour        = $invoice->tour;
    $company     = config('quotation.company');

    $custName = $tour?->customer?->name ?? 'Valued Guest';
    $party    = trim((string) $invoice->guest_name) !== ''
        ? $invoice->guest_name
        : (($tour?->pax ?? 0) > 1 ? $custName . ' & Party' : $custName);

    $resvDate = $tour?->start_date
        ? \Carbon\Carbon::parse($tour->start_date)->format('d F Y')
          . ($tour->end_date ? ' – ' . \Carbon\Carbon::parse($tour->end_date)->format('d F Y') : '')
        : null;

    $domain = preg_replace('#^https?://#', '', (string) $company['website']);
    $domain = preg_replace('#^www\.#', '', $domain);

    return [
        'invoice'      => $invoice,
        'company'      => $company,
        'bank'         => $this->bankAccounts($invoice),
        'paymentTerms' => config('quotation.payment_terms', ''),
        'logo'         => $this->logoDataUri(),
        'lines'        => $invoice->description_lines ?? [],
        'unitPrice'    => (float) $invoice->unit_price,
        'pax'          => (int) ($invoice->pax ?? $tour?->pax ?? 0),
        'paid'         => $paid,
        'outstanding'  => $outstanding,
        'custName'     => $custName,
        'party'        => $party,
        'resvDate'     => $resvDate,
        'domain'       => $domain,
    ];
}
```

Lalu `build()` menjadi:

```php
private function build(Invoice $invoice): Mpdf
{
    $data = $this->invoiceRenderData($invoice);

    $tmp = storage_path('app/mpdf');
    if (! is_dir($tmp)) {
        mkdir($tmp, 0775, true);
    }

    $mpdf = new Mpdf([
        'format'        => 'A4',
        'margin_left'   => 9,
        'margin_right'  => 9,
        'margin_top'    => 8,
        'margin_bottom' => 22,
        'margin_header' => 0,
        'margin_footer' => 7,
        'default_font'  => 'dejavusans',
        'tempDir'       => $tmp,
    ]);

    $mpdf->SetTitle('Invoice ' . $invoice->number);

    $paid        = $data['paid'];
    $outstanding = $data['outstanding'];

    // Watermark berdasarkan status pembayaran
    if ($paid > 0) {
        $watermarkText  = $outstanding <= 0.005 ? 'PAID IN FULL' : 'DEPOSIT RECEIVED';
        $mpdf->SetWatermarkText($watermarkText);
        $mpdf->showWatermarkText  = true;
        $mpdf->watermarkTextAlpha = 0.07;
    }

    $html = view('invoice', $data)->render();

    $mpdf->WriteHTML($html);

    return $mpdf;
}
```

`view('invoice', $data)` tetap menerima semua key yang sebelumnya dikirim (`invoice, company, bank, paymentTerms, logo, lines, unitPrice, pax, paid, outstanding`) — key tambahan (`custName, party, resvDate, domain`) diabaikan Blade karena Blade masih menghitungnya sendiri di `@php` block-nya sendiri (tidak diubah). Ini sengaja: menghindari menyentuh template PDF yang sudah berjalan, dengan konsekuensi turunan tampilan itu dihitung dua kali (Blade & `invoiceRenderData()`) — diterima sebagai bagian dari trade-off Approach A yang sudah disetujui di spec.

- [ ] **Step 4: Jalankan test lagi, pastikan TETAP LULUS setelah refactor**

Run: `php artisan test --filter=InvoicePdfCharacterizationTest`
Expected: `3 passed` — membuktikan refactor murni pemindahan kode, nol perubahan perilaku PDF.

- [ ] **Step 5: Jalankan seluruh suite untuk pastikan tidak ada regresi lain**

Run: `php artisan test`
Expected: semua test lulus (tidak ada yang gagal dibanding sebelum Task 1).

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Invoice/InvoicePdfCharacterizationTest.php app/Http/Controllers/InvoiceController.php
git commit -m "refactor: ekstrak invoiceRenderData() dari build(), kunci perilaku PDF dgn test karakterisasi"
```

---

### Task 2: `InvoiceDocxBuilder` — builder PHPWord

**Files:**
- Create: `app/Support/InvoiceDocxBuilder.php`
- Create: `tests/Unit/Support/InvoiceDocxBuilderTest.php`
- Modify: `composer.json` (lewat `composer require`)

**Interfaces:**
- Consumes: array dengan bentuk persis hasil `InvoiceController::invoiceRenderData()` (Task 1) — key: `invoice, company, bank, paymentTerms, logo, lines, unitPrice, pax, paid, outstanding, custName, party, resvDate, domain`.
- Produces: `InvoiceDocxBuilder::stream(array $data, string $filename): \Illuminate\Http\Response` — dipakai Task 3.

- [ ] **Step 1: Install PHPWord**

Run: `composer require phpoffice/phpword:^1.3`
Expected: `phpoffice/phpword` muncul di `composer.json` bagian `require`, `composer.lock` ter-update, tidak ada error dependency conflict.

- [ ] **Step 2: Tulis unit test untuk builder (fixture dibuat manual, tanpa DB)**

Catatan: test lain di `tests/Unit/` (mis. `SalesLine/CalculateTotalTest.php`) extends `PHPUnit\Framework\TestCase` polos karena murni logika PHP tanpa Laravel. Test ini SENGAJA extends `Tests\TestCase` (yang mem-boot aplikasi) karena `InvoiceDocxBuilder` memanggil `config()`, `public_path()`, `base_path()` — helper itu butuh container ter-boot, tidak akan berfungsi di atas PHPUnit polos. Tidak butuh `RefreshDatabase` karena tidak ada baris yang di-`save()` ke database (semua fixture dibuat lewat `forceFill()` + `setRelation()` di memori saja).

```php
<?php

namespace Tests\Unit\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tour;
use App\Support\InvoiceDocxBuilder;
use PhpOffice\PhpWord\IOFactory;
use Tests\TestCase;

class InvoiceDocxBuilderTest extends TestCase
{
    private function fixtureData(array $overrides = []): array
    {
        $customer = new Customer();
        $customer->forceFill(['name' => 'John Doe', 'phone' => '0812345678']);

        $tour = new Tour();
        $tour->forceFill([
            'pax' => 4, 'title' => 'Bunaken Explore', 'code' => 'TUR-001',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-05',
        ]);
        $tour->setRelation('customer', $customer);

        $invoice = new Invoice();
        $invoice->forceFill(array_replace([
            'number' => 'INV-2026-11-0001', 'date' => '2026-07-20', 'due_date' => '2026-07-27',
            'currency' => 'IDR', 'unit_price' => 500_000, 'pax' => 4,
            'total' => 2_000_000, 'total_idr' => 2_000_000,
            'guest_name' => null, 'notes' => null, 'description_lines' => [],
        ], $overrides['invoice'] ?? []));
        $invoice->setRelation('tour', $tour);
        $invoice->setRelation('payments', collect($overrides['payments'] ?? []));

        return [
            'invoice'      => $invoice,
            'company'      => config('quotation.company'),
            'bank'         => [['bank' => 'Bank BCA', 'account' => '1234567890', 'name' => 'PT. Welcome Manado Wisata']],
            'paymentTerms' => config('quotation.payment_terms', ''),
            'logo'         => null,
            'lines'        => $invoice->description_lines ?? [],
            'unitPrice'    => (float) $invoice->unit_price,
            'pax'          => (int) $invoice->pax,
            'paid'         => (float) collect($overrides['payments'] ?? [])->sum('amount'),
            'outstanding'  => (float) $invoice->total - (float) collect($overrides['payments'] ?? [])->sum('amount'),
            'custName'     => 'John Doe',
            'party'        => 'John Doe & Party',
            'resvDate'     => '01 August 2026 – 05 August 2026',
            'domain'       => 'welcomemanado.com',
        ];
    }

    /**
     * Buka docx yang barusan dibangun & kembalikan teks polos semua elemen —
     * termasuk yang bersarang di TextRun (mis. "Date of Issued: ... No: ...")
     * dan di dalam sel tabel, karena PHPWord's TextRun/Table TIDAK punya
     * getText(), hanya getElements()/getRows() untuk anak-anaknya.
     */
    private function extractText(string $binary): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $binary);

        $phpWord = IOFactory::load($tmp, 'Word2007');
        $text    = '';
        $walk    = function (array $elements) use (&$walk, &$text): void {
            foreach ($elements as $element) {
                if (method_exists($element, 'getText') && ! is_array($element->getText())) {
                    $text .= $element->getText() . ' ';
                }
                if (method_exists($element, 'getElements')) {
                    $walk($element->getElements());
                }
                if (method_exists($element, 'getRows')) {
                    foreach ($element->getRows() as $row) {
                        foreach ($row->getCells() as $cell) {
                            $walk($cell->getElements());
                        }
                    }
                }
            }
        };

        foreach ($phpWord->getSections() as $section) {
            $walk($section->getElements());
        }
        unlink($tmp);

        return $text;
    }

    public function test_docx_memuat_nomor_invoice_nama_tamu_dan_nominal(): void
    {
        $response = InvoiceDocxBuilder::stream($this->fixtureData(), 'INV-2026-11-0001.docx');

        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('attachment; filename="INV-2026-11-0001.docx"', $response->headers->get('Content-Disposition'));

        $text = $this->extractText($response->getContent());
        $this->assertStringContainsString('INV-2026-11-0001', $text);
        $this->assertStringContainsString('JOHN DOE', $text); // party ditampilkan uppercase
        $this->assertStringContainsString('Bank BCA', $text);
    }

    /**
     * Cek lewat relationship file MILIK HEADER (word/_rels/header1.xml.rels),
     * bukan sekadar "ada gambar di manapun dalam docx" — logo perusahaan JUGA
     * gambar dan selalu ada di body, jadi pengecekan generik word/media/imageN
     * akan salah-positif kalau logo ikut ke-hitung sebagai "watermark".
     */
    private function headerHasImage(string $binary): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $binary);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $headerRels = $zip->getFromName('word/_rels/header1.xml.rels');
        $zip->close();
        unlink($tmp);

        return $headerRels !== false && str_contains($headerRels, 'image');
    }

    public function test_tanpa_pembayaran_tidak_ada_watermark(): void
    {
        $response = InvoiceDocxBuilder::stream($this->fixtureData(), 'x.docx');

        $this->assertFalse(
            $this->headerHasImage($response->getContent()),
            'Header tidak boleh mereferensikan gambar (watermark) saat belum ada pembayaran'
        );
    }

    public function test_pembayaran_penuh_menampilkan_watermark_lunas(): void
    {
        $data = $this->fixtureData(['payments' => [
            (object) ['amount' => 2_000_000],
        ]]);

        $response = InvoiceDocxBuilder::stream($data, 'x.docx');

        $this->assertTrue(
            $this->headerHasImage($response->getContent()),
            'Header harus mereferensikan gambar watermark saat lunas'
        );
    }
}
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL (class belum ada)**

Run: `php artisan test --filter=InvoiceDocxBuilderTest`
Expected: FAIL — `Class "App\Support\InvoiceDocxBuilder" not found`.

- [ ] **Step 4: Tulis `InvoiceDocxBuilder`**

```php
<?php

namespace App\Support;

use Illuminate\Http\Response;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Membangun invoice sebagai .docx, meniru struktur resources/views/invoice.blade.php
 * bagian per bagian. Lihat docs/superpowers/specs/2026-07-28-ekspor-invoice-docx-design.md
 * untuk pemetaan section PDF → elemen PHPWord.
 */
class InvoiceDocxBuilder
{
    private const PAGE_WIDTH = 9000; // twip, lebar konten setelah margin (≈ A4 - margin kiri/kanan)
    private const BORDER     = ['borderSize' => 6, 'borderColor' => '000000'];

    public static function stream(array $data, string $filename): Response
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'marginLeft' => 500, 'marginRight' => 500, 'marginTop' => 500, 'marginBottom' => 900,
        ]);

        self::addHeader($section, $data);
        self::addServicesBand($section);
        self::addTitleRow($section, $data['invoice']);
        self::addBillTo($section, $data);
        self::addMainTable($section, $data);

        // config('quotation.invoice_note') ada di ROOT config, bukan di bawah
        // 'company' — dipanggil langsung di sini, sama seperti invoice.blade.php
        // yang juga membacanya langsung lewat config() bukan lewat prop.
        $invoiceNote = config('quotation.invoice_note');
        if ($invoiceNote) {
            $section->addText('Note: ' . $invoiceNote, ['italic' => true, 'size' => 9], ['spaceAfter' => 100]);
        }

        self::addBankTable($section, $data['bank']);
        if (! empty($data['invoice']->notes)) {
            $section->addText($data['invoice']->notes, ['size' => 9], ['spaceBefore' => 100]);
        }
        self::addProformaBanner($section);

        $paid        = (float) $data['paid'];
        $outstanding = (float) $data['outstanding'];
        if ($paid > 0) {
            $text = $outstanding <= 0.005 ? 'PAID IN FULL' : 'DEPOSIT RECEIVED';
            self::addWatermark($section, $text);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private static function addHeader($section, array $data): void
    {
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 40]);
        $table->addRow();
        $logoPath = public_path('logo.png');
        $logoCell = $table->addCell((int) (self::PAGE_WIDTH * 0.15));
        if (is_file($logoPath)) {
            $logoCell->addImage($logoPath, ['width' => 70, 'height' => 70, 'alignment' => Jc::CENTER]);
        }
        $infoCell = $table->addCell((int) (self::PAGE_WIDTH * 0.70));
        $infoCell->addText($data['company']['legal_name'], ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $infoCell->addText($data['company']['address'], ['size' => 8], ['alignment' => Jc::CENTER]);
        $infoCell->addText('Phone: ' . $data['company']['phone'], ['size' => 8], ['alignment' => Jc::CENTER]);
        $infoCell->addText('www.' . $data['domain'], ['size' => 8], ['alignment' => Jc::CENTER]);
        $table->addCell((int) (self::PAGE_WIDTH * 0.15));
    }

    private static function addServicesBand($section): void
    {
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => '000000', 'cellMargin' => 60]);
        $table->addRow();
        $cell = $table->addCell(self::PAGE_WIDTH);
        $cell->addText(
            'Inbound Tour – Outbound Tour – Airline Ticket – Voucher Hotel – Rental Car – MICE – Incentive Tour',
            ['bold' => true, 'size' => 8],
            ['alignment' => Jc::CENTER]
        );
    }

    private static function addTitleRow($section, $invoice): void
    {
        $table = $section->addTable(['borderSize' => 0]);
        $table->addRow();
        $titleCell = $table->addCell((int) (self::PAGE_WIDTH * 0.5));
        $titleCell->addText('INVOICE', ['bold' => true, 'size' => 30, 'color' => '2E74B5']);

        $issuedCell = $table->addCell((int) (self::PAGE_WIDTH * 0.5), array_merge(self::BORDER, ['valign' => 'center']));
        $dateStr = \Carbon\Carbon::parse($invoice->date)->format('d F Y');
        $issuedText = $issuedCell->addTextRun(['alignment' => Jc::CENTER]);
        $issuedText->addText('Date of Issued: ');
        $issuedText->addText($dateStr, ['color' => 'C0272D', 'bold' => true]);
        $issuedText->addText(', No: ');
        $issuedText->addText($invoice->number, ['color' => 'C0272D', 'bold' => true]);
        if ($invoice->due_date) {
            $dueRun = $issuedCell->addTextRun(['alignment' => Jc::CENTER]);
            $dueRun->addText('Payment Due: ');
            $dueRun->addText(\Carbon\Carbon::parse($invoice->due_date)->format('d F Y'), ['color' => 'C0272D', 'bold' => true]);
        }
    }

    private static function addBillTo($section, array $data): void
    {
        $invoice = $data['invoice'];
        $tour    = $invoice->tour;

        $table = $section->addTable(['borderSize' => 0]);
        $table->addRow();
        $table->addCell(1500)->addText('TO', ['bold' => true]);
        $table->addCell(self::PAGE_WIDTH - 1500)->addText(': ' . strtoupper($data['custName']), ['bold' => true]);

        if ($tour?->customer?->phone) {
            $table->addRow();
            $table->addCell(1500);
            $table->addCell(self::PAGE_WIDTH - 1500)->addText($tour->customer->phone);
        }

        $table->addRow();
        $table->addCell(1500)->addText('RESERVATION', ['bold' => true]);
        $table->addCell(self::PAGE_WIDTH - 1500)->addText(': ' . strtoupper($tour?->title ?? $tour?->code ?? '-'), ['bold' => true]);
    }

    private static function addMainTable($section, array $data): void
    {
        $invoice = $data['invoice'];
        $cur     = $invoice->currency ?: 'IDR';
        $fmt     = fn ($v) => $cur . ' ' . number_format((float) $v, 0, ',', '.');
        $descW   = (int) (self::PAGE_WIDTH * 0.78);
        $amtW    = self::PAGE_WIDTH - $descW;

        $table = $section->addTable(array_merge(self::BORDER, ['cellMargin' => 80]));

        $table->addRow();
        $table->addCell($descW, self::BORDER)->addText('DESCRIPTION', ['bold' => true], ['alignment' => Jc::CENTER]);
        $table->addCell($amtW, self::BORDER)->addText('AMOUNT', ['bold' => true], ['alignment' => Jc::CENTER]);

        // Baris info tamu + baris deskripsi tanpa amount
        $table->addRow();
        $infoCell = $table->addCell($descW, self::BORDER);
        $infoCell->addText('Guest Name: ' . strtoupper($data['party']), ['bold' => true]);
        $infoCell->addText('Reservation: ' . ($invoice->tour?->title ?? $invoice->tour?->code ?? '-'));
        if ($data['resvDate']) {
            $infoCell->addText('Date: ' . $data['resvDate']);
        }
        if ($invoice->tour?->pax) {
            $infoCell->addText('Total Pax: ' . $invoice->tour->pax . ' pax');
        }
        $prevLbl = null;
        foreach ($data['lines'] as $ln) {
            if (! empty($ln['amount'])) {
                continue;
            }
            $lbl = trim($ln['label'] ?? '');
            $dt  = trim($ln['date'] ?? '');
            $det = trim($ln['detail'] ?? '');
            if ($lbl === '' && $dt === '' && $det === '') {
                continue;
            }
            $showLbl = $lbl !== '' && $lbl !== $prevLbl;
            if ($lbl !== '') {
                $prevLbl = $lbl;
            }
            $prefix = $showLbl ? $lbl . ': ' : '';
            $infoCell->addText(trim($prefix . $dt . ' ' . $det));
        }
        $table->addCell($amtW, self::BORDER);

        // Baris harga proforma
        $table->addRow();
        $priceCell = $table->addCell($descW, self::BORDER);
        $priceText = $data['unitPrice'] > 0
            ? $fmt($data['unitPrice']) . ($data['pax'] > 0 ? ' × ' . $data['pax'] . ' pax' : '')
            : '';
        $priceCell->addText('Price: ' . $priceText);
        $lineTotal = collect($data['lines'])->sum('amount');
        $table->addCell($amtW, array_merge(self::BORDER, ['valign' => 'center']))
            ->addText($fmt((float) $invoice->total - $lineTotal), [], ['alignment' => Jc::END]);

        // Baris ber-amount ("Additional")
        foreach ($data['lines'] as $ln) {
            if (empty($ln['amount'])) {
                continue;
            }
            $table->addRow();
            $table->addCell($descW, self::BORDER)->addText((trim($ln['label'] ?? '') ?: 'Additional') . ': ' . ($ln['detail'] ?? ''));
            $table->addCell($amtW, array_merge(self::BORDER, ['valign' => 'center']))
                ->addText($fmt($ln['amount']), [], ['alignment' => Jc::END]);
        }

        // Totals
        $table->addRow();
        $table->addCell($descW, self::BORDER)->addText('Total', ['bold' => true], ['alignment' => Jc::END]);
        $table->addCell($amtW, self::BORDER)->addText($fmt($invoice->total), ['bold' => true], ['alignment' => Jc::END]);

        foreach ($invoice->payments as $p) {
            $table->addRow();
            $label = 'Down Payment – ' . \Carbon\Carbon::parse($p->date)->format('M d, Y');
            $table->addCell($descW, self::BORDER)->addText($label, [], ['alignment' => Jc::END]);
            $table->addCell($amtW, self::BORDER)->addText('(' . $fmt($p->amount) . ')', [], ['alignment' => Jc::END]);
        }

        $paid        = (float) $data['paid'];
        $outstanding = max((float) $invoice->total - $paid, 0);
        $table->addRow();
        $table->addCell($descW, self::BORDER)->addText('Balance Due', ['bold' => true], ['alignment' => Jc::END]);
        $table->addCell($amtW, self::BORDER)->addText($fmt($outstanding), ['bold' => true], ['alignment' => Jc::END]);
    }

    private static function addBankTable($section, array $bank): void
    {
        if (empty($bank)) {
            return;
        }
        $section->addText('Payment can be made via bank transfer to:', [], ['spaceBefore' => 150]);
        foreach ($bank as $b) {
            $table = $section->addTable(['borderSize' => 0]);
            $table->addRow();
            $table->addCell(2000)->addText('Bank');
            $table->addCell(self::PAGE_WIDTH - 2000)->addText($b['bank'] ?? '');
            $table->addRow();
            $table->addCell(2000)->addText('Account Number');
            $table->addCell(self::PAGE_WIDTH - 2000)->addText($b['account'] ?? '');
            $table->addRow();
            $table->addCell(2000)->addText('Account Name');
            $table->addCell(self::PAGE_WIDTH - 2000)->addText($b['name'] ?? '');
        }
    }

    private static function addProformaBanner($section): void
    {
        $section->addText(
            'THIS PROFORMA INVOICE IS ISSUED TO CONFIRM YOUR RESERVATION',
            ['bold' => true, 'size' => 12, 'color' => 'C0272D'],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 150, 'borderTopSize' => 12, 'borderBottomSize' => 12, 'borderColor' => 'C0272D']
        );
    }

    /** Watermark diagonal transparan lewat GD (PHPWord tak punya watermark teks bawaan). */
    private static function addWatermark($section, string $text): void
    {
        $ttf      = base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSansCondensed-Bold.ttf');
        $fontSize = 42;
        $angle    = 45;

        $box  = imagettfbbox($fontSize, $angle, $ttf, $text);
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        $minY = min($box[1], $box[3], $box[5], $box[7]);
        $maxY = max($box[1], $box[3], $box[5], $box[7]);
        $width  = $maxX - $minX + 20;
        $height = $maxY - $minY + 20;

        $canvas = imagecreatetruecolor($width, $height);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        $gray = imagecolorallocatealpha($canvas, 150, 150, 150, 118); // ≈7% opacity, meniru watermarkTextAlpha mPDF

        imagettftext($canvas, $fontSize, $angle, (int) (-$minX), (int) ($height - $maxY - 10), $gray, $ttf, $text);

        $tmpDir = storage_path('app/mpdf');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        $path = $tmpDir . '/watermark-' . md5($text) . '.png';
        imagepng($canvas, $path);
        imagedestroy($canvas);

        $section->getHeader()->addWatermark($path, ['marginTop' => 200, 'marginLeft' => 100]);
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=InvoiceDocxBuilderTest`
Expected: `3 passed`.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock app/Support/InvoiceDocxBuilder.php tests/Unit/Support/InvoiceDocxBuilderTest.php
git commit -m "feat: InvoiceDocxBuilder — bangun invoice .docx via PHPWord"
```

---

### Task 3: Route + controller `downloadDocx()`

**Files:**
- Modify: `routes/web.php` (grup dekat baris 220-224)
- Modify: `app/Http/Controllers/InvoiceController.php` (tambah method dekat `download()`/`preview()`)
- Create: `tests/Feature/Invoice/InvoiceDocxDownloadTest.php`

**Interfaces:**
- Consumes: `InvoiceController::invoiceRenderData()` (Task 1), `InvoiceDocxBuilder::stream()` (Task 2).
- Produces: route `invoices.download-docx`.

- [ ] **Step 1: Tulis test feature (gagal dulu — route belum ada)**

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

class InvoiceDocxDownloadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_invoice_draft_bisa_diunduh_docx(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000);

        $response = $this->actingAs($this->salesUser())
            ->get(route('invoices.download-docx', $invoice));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $response->assertHeader('Content-Disposition', 'attachment; filename="' . $invoice->number . '.docx"');
    }

    public function test_invoice_dengan_dp_sebagian_tetap_bisa_diunduh_docx(): void
    {
        $invoice = $this->approveInvoice($this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000));
        $invoice->payments()->create(['date' => now()->toDateString(), 'amount' => 300_000]);

        $response = $this->actingAs($this->salesUser())
            ->get(route('invoices.download-docx', $invoice));

        $response->assertOk();
    }

    public function test_akuntan_juga_bisa_mengunduh_docx(): void
    {
        $invoice   = $this->makeInvoice($this->makeTour('tour', ['pax' => 2]), 500_000);
        $accountant = \App\Models\User::create([
            'name' => 'Akuntan Uji', 'email' => 'akuntan-docx@test.local',
            'password' => bcrypt('password'), 'role' => 'accountant',
        ]);

        $response = $this->actingAs($accountant)->get(route('invoices.download-docx', $invoice));

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=InvoiceDocxDownloadTest`
Expected: FAIL — route `invoices.download-docx` tidak ditemukan.

- [ ] **Step 3: Tambah route**

Di `routes/web.php`, dalam grup yang sama dengan `invoices.preview`/`invoices.download` (dekat baris 221-222):

```php
Route::get('/invoices/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('invoices.preview');
Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
Route::get('/invoices/{invoice}/download-docx', [InvoiceController::class, 'downloadDocx'])->name('invoices.download-docx');
```

- [ ] **Step 4: Tambah method controller**

Di `app/Http/Controllers/InvoiceController.php`, tambahkan import baru di bagian atas file bersama `use` statement lain yang sudah ada (baris ~4-14, urutan alfabetis mengikuti yang sudah ada):

```php
use App\Support\InvoiceDocxBuilder;
```

Lalu tambahkan method baru ini di dalam class, dekat `download()`/`preview()` (baris ~226-234):

```php
public function downloadDocx(Invoice $invoice)
{
    $data = $this->invoiceRenderData($invoice);

    return InvoiceDocxBuilder::stream($data, $invoice->number . '.docx');
}
```

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=InvoiceDocxDownloadTest`
Expected: `3 passed`.

- [ ] **Step 6: Jalankan seluruh suite**

Run: `php artisan test`
Expected: semua test lulus.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/InvoiceDocxDownloadTest.php
git commit -m "feat: route+controller unduh invoice .docx (invoices.download-docx)"
```

---

### Task 4: Tombol "⬇ Word" di panel invoice

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue:713-718`

- [ ] **Step 1: Tambah tombol**

Cari blok tombol PDF (dekat baris 713-718):

```html
<a :href="route('invoices.preview', inv.id)" target="_blank">
    <Button size="sm" variant="outline">👁 PDF</Button>
</a>
<a :href="route('invoices.download', inv.id)">
    <Button size="sm" variant="outline">⬇ Unduh</Button>
</a>
```

Tambahkan tepat setelah tombol "⬇ Unduh":

```html
<a :href="route('invoices.download-docx', inv.id)">
    <Button size="sm" variant="outline">⬇ Word</Button>
</a>
```

- [ ] **Step 2: Build frontend, pastikan tidak ada error**

Run: `npm run build`
Expected: `✓ built in ...` tanpa error baru terkait `InvoicesPanel.vue`.

- [ ] **Step 3: Verifikasi manual di browser (tidak ada test JS di repo ini)**

Buka panel invoice sebuah tour di browser (`php artisan serve` + `npm run dev`), klik "⬇ Word", pastikan file `.docx` terunduh dan bisa dibuka Microsoft Word tanpa peringatan "file corrupt/format tidak dikenali", untuk minimal 3 kondisi: invoice draft, invoice disetujui dengan DP sebagian, invoice lunas. Cek juga di Word (bukan cuma test otomatis, yang tidak memverifikasi visual): tabel deskripsi/nominal tampil berbingkai penuh (bukan cuma garis luar), dan watermark LUNAS/DP muncul pada kondisi yang sesuai — test unit Task 2 hanya membuktikan ISI (teks/angka) dan KEBERADAAN watermark, bukan hasil visual bingkai tabel.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: tombol unduh invoice .docx di panel invoice tour"
```

---

## Setelah semua task selesai

Jalankan `php artisan test` sekali lagi (full suite) sebagai gerbang akhir, lalu ikuti pola sesi ini: dispatch reviewer subagent atas seluruh diff branch sebelum merge ke `dev` (lihat `superpowers:subagent-driven-development`).
