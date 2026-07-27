# Desain: Ekspor Invoice ke .docx

> **Status:** DRAFT — menunggu review user sebelum masuk ke implementation plan.

## 1. Masalah

Invoice saat ini hanya bisa diunduh sebagai PDF (`InvoiceController::download()`/`preview()`, dibangun lewat mPDF dari `resources/views/invoice.blade.php`). Sales butuh versi `.docx` juga — bukan menggantikan PDF, melainkan tambahan — supaya bisa diedit manual sebelum dipakai/dikirim.

## 2. Keputusan yang dikunci (hasil brainstorming)

1. **Tambahan, bukan pengganti** — PDF tetap ada apa adanya; `.docx` adalah opsi unduh baru.
2. **Scope hanya Invoice** — tidak menyentuh `quotation.blade.php` atau dokumen lain.
3. **Tampilan `.docx` semirip mungkin PDF** — termasuk tabel berbingkai dan watermark LUNAS/DP — karena sales akan mengedit dari hasil ini, bukan dari nol.
4. **Pendekatan: PHPWord native builder** (bukan konversi LibreOffice, bukan HTML importer bawaan PHPWord). Dipilih karena:
   - Tidak menambah dependency infrastruktur di server (LibreOffice belum terpasang, dan itu perubahan ops di luar kode).
   - Parser HTML PHPWord tidak cukup andal untuk tabel bersarang serumit `invoice.blade.php`.
   - Trade-off yang diterima: dua "template" (Blade untuk PDF, PHPWord builder untuk Word) harus dijaga tetap sinkron manual kalau layout invoice berubah nanti. Untuk mengurangi risiko ini, **data** yang mengisi kedua template diekstrak ke satu sumber bersama (§3.1) — hanya **layout/rendering** yang terduplikasi, bukan datanya.
5. **Hanya unduh, tidak ada preview inline** — browser tidak bisa menampilkan `.docx` di tab seperti PDF, jadi cukup satu tombol "⬇ Word".
6. **Watermark LUNAS/DP** digambar sebagai PNG transparan diagonal pakai ekstensi GD (sudah aktif di server), ditempel lewat `Header::addWatermark()` PHPWord — meniru kondisi yang sama persis dengan mPDF: tampil hanya bila `$paid > 0`, teks "PAID IN FULL" bila `$outstanding <= 0.005`, selain itu "DEPOSIT RECEIVED".

## 3. Arsitektur

### 3.1 Refactor: satu sumber data untuk PDF dan Word

**File:** `app/Http/Controllers/InvoiceController.php`

Method privat `build()` (baris ~283-335) saat ini menghitung sekaligus merender data invoice untuk mPDF dalam satu method. Ekstrak bagian penghitungan data (baris ~317-329, isi array yang dikirim ke `view('invoice', [...])`) menjadi method privat baru:

```php
private function invoiceRenderData(Invoice $invoice): array
{
    $invoice->load(['tour.customer', 'items.product', 'payments']);

    $paid        = (float) $invoice->payments->sum('amount');
    $outstanding = (float) $invoice->total - $paid;

    return [
        'invoice'      => $invoice,
        'company'      => config('quotation.company'),
        'bank'         => $this->bankAccounts($invoice),
        'paymentTerms' => config('quotation.payment_terms', ''),
        'logo'         => $this->logoDataUri(),
        'lines'        => $invoice->description_lines ?? [],
        'unitPrice'    => (float) $invoice->unit_price,
        'pax'          => (int) ($invoice->pax ?? $invoice->tour?->pax ?? 0),
        'paid'         => $paid,
        'outstanding'  => $outstanding,
    ];
}
```

`build()` dipangkas jadi memanggil `$data = $this->invoiceRenderData($invoice);` lalu memakai `$data['paid']`/`$data['outstanding']` untuk watermark mPDF dan `$data` (di-`array_merge` dengan apa pun yang spesifik-Blade bila ada) untuk `view('invoice', $data)`. **Tidak ada perubahan perilaku PDF** — murni pemindahan kode, dibuktikan test PDF yang sudah ada (bila ada) tetap hijau.

### 3.2 Route & controller baru

**File:** `routes/web.php` (dekat baris 221-222, grup yang sama dengan `invoices.preview`/`invoices.download`):

```php
Route::get('/invoices/{invoice}/download-docx', [InvoiceController::class, 'downloadDocx'])->name('invoices.download-docx');
```

**File:** `app/Http/Controllers/InvoiceController.php`, method baru dekat `download()`/`preview()`:

```php
public function downloadDocx(Invoice $invoice)
{
    $data     = $this->invoiceRenderData($invoice);
    $filename = $invoice->number . '.docx';

    return InvoiceDocxBuilder::stream($data, $filename);
}
```

Tidak ada guard `ensureNotApproved()` atau pengecekan status apa pun — mengikuti persis pola `preview()`/`download()` PDF yang sudah ada (bisa diunduh kapan pun, draft maupun sudah disetujui).

### 3.3 `InvoiceDocxBuilder` — builder PHPWord

**File baru:** `app/Support/InvoiceDocxBuilder.php`

```php
namespace App\Support;

class InvoiceDocxBuilder
{
    public static function stream(array $data, string $filename): \Illuminate\Http\Response
    {
        // bangun PhpOffice\PhpWord\PhpWord, isi section, kembalikan Response
        // dengan Content-Type application/vnd.openxmlformats-officedocument.wordprocessingml.document
        // dan Content-Disposition attachment; filename="..."
    }
}
```

Struktur section meniru `invoice.blade.php` bagian per bagian:

| Bagian PDF (blade) | Elemen PHPWord |
|---|---|
| Header (logo + identitas perusahaan) | Tabel 3 kolom tanpa border, gambar logo via `Cell::addImage()` |
| Pita "services" | Baris tabel/paragraf dengan border atas-bawah, teks tebal center |
| Judul INVOICE + kotak terbit/nomor | Tabel 2 kolom; kolom kanan pakai `TableStyle` border penuh |
| Bill To | Tabel label:nilai tanpa border |
| Deskripsi/Nominal | Tabel utama berbingkai (`TableStyle` dgn `borderSize`/`borderColor`); baris info tamu+baris deskripsi tanpa `amount`, baris harga×pax, baris ber-`amount` ("Additional"), baris Total/DP/Balance Due |
| Catatan | Paragraf teks biasa |
| Rekening bank | Tabel label:nilai per rekening |
| Catatan per-invoice | Paragraf, baris baru mengikuti `\n` di `notes` |
| Baris proforma | Paragraf tebal center, border atas-bawah merah |
| Watermark | PNG digambar GD (diagonal, alpha rendah), `Header::addWatermark()` |

### 3.4 Frontend

**File:** `resources/js/Components/Tours/InvoicesPanel.vue`, dekat baris 713-718 (tombol "👁 PDF" / "⬇ Unduh"):

```html
<a :href="route('invoices.preview', inv.id)" target="_blank">
    <Button size="sm" variant="outline">👁 PDF</Button>
</a>
<a :href="route('invoices.download', inv.id)">
    <Button size="sm" variant="outline">⬇ Unduh</Button>
</a>
<a :href="route('invoices.download-docx', inv.id)">
    <Button size="sm" variant="outline">⬇ Word</Button>
</a>
```

### 3.5 Dependency baru

```
composer require phpoffice/phpword
```

## 4. Non-tujuan (YAGNI)

- Tidak menyentuh `quotation.blade.php` atau dokumen PDF lain (`profit_breakdown.blade.php`, dst).
- Tidak mengubah tampilan/perilaku PDF invoice yang sudah ada — refactor §3.1 murni pemindahan kode.
- Tidak ada konversi baliknya (upload `.docx` yang sudah diedit sales untuk disinkronkan kembali ke data invoice) — hasil unduhan bersifat satu-arah, sales edit di luar sistem.
- Tidak ada UI untuk memilih/menyesuaikan template Word dari halaman invoice — satu layout tetap untuk semua invoice, sama seperti PDF sekarang.

## 5. Kriteria selesai

- Ada tombol "⬇ Word" di panel invoice tour, menghasilkan file `.docx` yang bisa dibuka di Microsoft Word tanpa pesan error/corrupt.
- Isi `.docx` mencakup semua informasi yang sama dengan PDF: identitas perusahaan, info tamu/reservasi, baris deskripsi, baris "Additional" bernominal, total, pembayaran (DP), balance due, rekening bank, catatan, baris proforma.
- Watermark "PAID IN FULL"/"DEPOSIT RECEIVED" muncul di `.docx` pada kondisi yang identik dengan PDF (`paid > 0`, teks tergantung `outstanding`).
- PDF invoice tidak berubah perilaku maupun tampilannya sama sekali setelah refactor §3.1.
- Test otomatis membuktikan: route baru mengembalikan file docx valid untuk invoice draft, invoice disetujui tanpa pembayaran, dengan DP sebagian, dan lunas penuh.
