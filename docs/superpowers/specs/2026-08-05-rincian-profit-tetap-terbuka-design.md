# Desain: Rincian Profit Tetap Bisa Diedit Setelah Invoice Disetujui

> Status (5 Agu 2026): disetujui, siap direncanakan.

---

## 1. Latar belakang

Pertanyaan yang memicu ini: bagaimana kalau customer sudah DP, tapi sales belum sempat mengisi Rincian Profit atau belum menekan Setujui?

Penelusuran ke [`docs/logika-pembuatan-invoice/`](../../logika-pembuatan-invoice/README.md) menjawabnya, dan sekaligus membuka masalah yang lebih besar:

1. **Rincian Profit bukan syarat approve.** Satu-satunya syarat (§5.1 dokumen itu) adalah patokan terkunci, total proforma cocok patokan, dan kurs terisi untuk non-IDR. Sales bisa menekan Setujui kapan saja tanpa mengisi Rincian Profit sama sekali.
2. **Begitu disetujui, Rincian Profit terkunci selamanya — untuk siapa pun.** `InvoiceItemController::ensureEditable()` menolak `store`, `bulkStore`, `bulkUpdate`, `update`, dan `destroy` begitu `invoice->is_approved` benar, tanpa pengecualian role. Dikonfirmasi eksplisit sebagai "konsekuensi desain yang disengaja" di [06-penguncian-setelah-disetujui.md §6.5](../../logika-pembuatan-invoice/06-penguncian-setelah-disetujui.md#65-perubahan-setelah-disetujui): "Satu-satunya jalan adalah intervensi langsung di database."
3. **Profit tour dihitung dari Rincian Profit itu sendiri.** Untuk tipe `tour`: `tagihan customer (IDR) − Σ line_cost` ([04-rincian-profit.md §4.7](../../logika-pembuatan-invoice/04-rincian-profit.md#47-rumus-profit)). Kalau item belum diisi saat approve, profit yang tersimpan selamanya adalah tagihan penuh dikurangi nol — margin yang salah, tanpa jalan koreksi lewat aplikasi.

Jadi kuncinya justru terbalik dari intuisi: DP yang sudah diterima **tidak pernah** jadi alasan untuk buru-buru approve (uangnya sudah di tangan, mencatatnya di sistem boleh menyusul kapan saja). Yang sebenarnya butuh perbaikan adalah penguncian permanen Rincian Profit itu sendiri — dan itu berlaku untuk dua skenario sekaligus: sales belum sempat isi sebelum approve, ATAU biaya aktual dari supplier ternyata berbeda dari estimasi dan perlu dikoreksi belakangan.

## 2. Keputusan yang sudah dikunci

| # | Keputusan |
|---|---|
| D1 | Rincian Profit **tetap bisa diedit di status invoice apa pun** (draft, sent, partial, paid) — kunci `is_approved` dihapus dari `ensureEditable()`. Tidak ada batas waktu atau status akhir yang menutupnya lagi. |
| D2 | Yang boleh mengedit: **sales dan akuntan/admin**, bukan cuma sales. |
| D3 | **Menghapus** item yang sudah punya Bill (tagihan ke supplier) ditolak — mencegah Bill kehilangan jejak asalnya (lihat §3). **Mengubah** item yang sudah punya Bill tetap boleh — Bill dan InvoiceItem adalah dua angka independen. |
| D4 | Item baru pasca-approve **tidak** otomatis dibuatkan draft Bill. Akuntan membuatnya manual lewat tombol "+ Bill" yang sudah ada di bagian "AP — Bill ke Supplier" (`Finance/Tour.vue`) — mekanisme umum yang sudah ada, tidak berubah. |
| D5 | Setiap tambah/ubah/hapus item **setelah invoice disetujui** dicatat satu baris di riwayat tour, memakai mekanisme yang sama dengan catatan "Invoice disetujui & masuk Keuangan" yang sudah ada. Edit **sebelum** approve tidak dicatat — itu area kerja normal sales, mencatat tiap ketikan akan membanjiri riwayat tour tanpa manfaat. |
| D6 | Kunci `exchange_rate` invoice di `approve()` (dua lapis konfirmasi non-IDR) **tidak disentuh** — itu kunci berbeda, soal kurs invoice untuk `total_idr`, bukan Rincian Profit. Alasannya masih berlaku penuh. |
| D7 | Tagihan customer (`unit_price × pax`) tidak pernah tersentuh perubahan ini — Rincian Profit memang tidak pernah memengaruhinya (§4.1 dokumen lama), jadi membuka edit ini tidak membuka risiko baru di sisi tagihan. |
| D8 | Form edit Rincian Profit diekstrak jadi **satu komponen Vue bersama** (`RincianProfitEditor.vue`), dipakai baik oleh `InvoicesPanel.vue` (sales) maupun `Finance/Tour.vue` (akuntan) — bukan disalin dua kali. Lihat §4.4 untuk alasannya. |

## 3. Kenapa D3 (item ber-Bill tidak boleh dihapus) diperlukan

`bills.invoice_item_id` memakai `nullOnDelete()` ([migrasi `2026_07_13_000000_add_invoice_item_id_to_bills.php`](../../database/migrations/2026_07_13_000000_add_invoice_item_id_to_bills.php)): kalau `InvoiceItem` dihapus, Bill yang menunjuk ke sana **tidak ikut terhapus** — cuma kehilangan tautannya. Bill itu (bisa jadi sudah dibayar ke supplier) tetap ada, tapi mekanisme checklist "Perlu Dibuatkan Bill" (yang menandai item mana sudah dibuatkan Bill, dari komentar migrasi yang sama) kehilangan pegangan untuk item yang sudah tidak ada.

Sebelum perubahan ini, risiko ini nyaris tidak pernah terjadi — item hanya bisa dihapus sebelum approve, dan Bill baru dibuat SAAT approve, jadi urutannya aman (tidak mungkin ada Bill sebelum item dihapus). Begitu Rincian Profit dibuka untuk diedit kapan saja, item yang SUDAH punya Bill kini bisa dihapus kapan saja juga — celah ini jadi nyata. D3 menutupnya dengan pola yang sama seperti "kas bon sudah dipotong tidak bisa dihapus" di fitur Master Karyawan (Tahap B): action yang sudah punya efek nyata ke pihak lain tidak bisa dibatalkan begitu saja, harus dibongkar dari sisi sana dulu.

## 4. Perubahan konkret

### 4.1 `app/Http/Controllers/InvoiceItemController.php`

`ensureEditable()` saat ini:

```php
private function ensureEditable(Invoice $invoice): void
{
    if ($invoice->is_approved) {
        throw ValidationException::withMessages([
            'invoice' => 'Invoice sudah disetujui dan masuk Keuangan, tidak bisa diubah. Buat invoice tambahan bila ada perubahan.',
        ]);
    }
}
```

Dihapus seluruhnya — method ini dan pemanggilannya di kelima aksi (`store`, `bulkStore`, `bulkUpdate`, `update`, `destroy`) tidak lagi diperlukan (D1).

`destroy()` mendapat guard baru (D3):

```php
public function destroy(InvoiceItem $invoiceItem)
{
    if (Bill::where('invoice_item_id', $invoiceItem->id)->exists()) {
        throw ValidationException::withMessages([
            'invoice' => 'Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.',
        ]);
    }

    $this->catatRiwayatJikaSudahApprove($invoiceItem->invoice, 'hapus', $invoiceItem);

    $invoiceItem->delete();

    return redirect()->back();
}
```

Perlu `use App\Models\Bill;` ditambahkan di bagian atas berkas.

### 4.2 Pencatatan riwayat (D5)

Satu method privat baru, dipanggil dari `store`, `bulkStore`, `bulkUpdate`, `update`, `destroy` — **hanya bila `$invoice->is_approved` benar**:

```php
private function catatRiwayatJikaSudahApprove(Invoice $invoice, string $aksi, InvoiceItem $item): void
{
    if (! $invoice->is_approved) {
        return;
    }

    $nama = auth()->user()?->name ?? 'Sistem';
    $label = $item->description ?: ($item->product_type ?? 'item');

    $keterangan = match ($aksi) {
        'tambah' => "Item baru ditambahkan pasca-approve oleh {$nama}: {$label}.",
        'hapus'  => "Item dihapus pasca-approve oleh {$nama}: {$label}.",
        default  => "Rincian Profit diubah pasca-approve oleh {$nama}: {$label}.",
    };

    $invoice->tour?->histories()->create([
        'type'            => 'note',
        'status_snapshot' => $invoice->tour->status,
        'description'     => $keterangan,
        'created_by'      => $nama,
    ]);
}
```

Untuk `update()`/`bulkUpdate()`, keterangan idealnya menyebut field yang berubah (mis. "unit_cost Hotel Sutan Raja 800.000 → 950.000") — ini detail implementasi yang diserahkan ke rencana kerja, prinsipnya: bandingkan nilai sebelum-sesudah dari field yang benar-benar berubah, bukan mencetak seluruh baris.

### 4.3 `routes/web.php`

Kelima route Rincian Profit dipindah dari grup `role:admin,sales` (baris ~157-169) ke grup baru:

```php
// Rincian Profit — sales DAN akuntan/admin bisa mengedit kapan saja,
// termasuk setelah invoice disetujui (lihat spek 2026-08-05).
Route::middleware('role:admin,sales,accountant')->group(function () {
    Route::post('/invoices/{invoice}/items',       [InvoiceItemController::class, 'store'])->name('invoice-items.store');
    Route::post('/invoices/{invoice}/items/bulk',  [InvoiceItemController::class, 'bulkStore'])->name('invoice-items.bulk');
    Route::patch('/invoices/{invoice}/items/bulk', [InvoiceItemController::class, 'bulkUpdate'])->name('invoice-items.bulk-update');
    Route::patch('/invoice-items/{invoiceItem}',   [InvoiceItemController::class, 'update'])->name('invoice-items.update');
    Route::delete('/invoice-items/{invoiceItem}',  [InvoiceItemController::class, 'destroy'])->name('invoice-items.destroy');
});
```

Route invoice lain di grup asli (`invoices.store`, `.proforma`, `.baseline`, `.approve`, dst.) **tetap** di `role:admin,sales` — hanya kelima route item yang pindah.

### 4.4 Dua tempat Rincian Profit dirender — keduanya perlu bisa edit (D2)

Ternyata ada **dua** file Vue terpisah yang menampilkan Rincian Profit, bukan satu:

| Berkas | Role | Kondisi sekarang |
|---|---|---|
| `resources/js/Components/Tours/InvoicesPanel.vue` | sales | Punya DUA cabang render per invoice: `v-if="isApproved(inv)"` (baris 1008-1025, teks statis tanpa input) vs `v-else` (baris 1026-1071, form editable dengan `markDirty`/autosave + tombol hapus). Kolom header Tipe/Tanggal/hapus (baris 992, 993, 999), tombol Tempel (baris 981), dan tombol "+ Tambah Produk" (baris 1075-1077) semuanya dibungkus `v-if="!isApproved(inv)"`. |
| `resources/js/Pages/Finance/Tour.vue` | akuntan | **100% read-only** (baris 432-464) — tabel teks statis, tidak ada input, tidak ada tombol tambah/hapus sama sekali. Komentar di kode sendiri eksplisit: "Rincian Profit (internal, read-only untuk akuntan)". |

`CostingPanel.vue` (diimpor bersama `InvoicesPanel.vue` di `Tours/Edit.vue`) **tidak relevan** — diverifikasi nol referensi ke `InvoiceItem`/Rincian Profit/`isApproved`, kemungkinan panel biaya/budget yang berbeda.

**Keputusan (D8): ekstrak komponen bersama, bukan duplikasi.** Menyalin form edit dari `InvoicesPanel.vue` ke `Finance/Tour.vue` secara terpisah akan menduplikasi logika bisnis (validasi qty/nights, autosave, rumus tampilan) di dua tempat — pola yang sudah pernah terbukti bermasalah di repo ini (aturan profit per jenis sempat terduplikasi di 3 file sebelum disentralisasi ke `SalesLineRuleRegistry`, lihat [`docs/desain/pemisahan-invoice-per-jenis.md`](../../desain/pemisahan-invoice-per-jenis.md)).

Sebagai gantinya: ekstrak satu komponen baru, **`resources/js/Components/Invoices/RincianProfitEditor.vue`**, berisi:
- Tabel item (baris editable: deskripsi, tipe, tanggal, qty, nights, unit_cost, unit_sell, total jual, tombol hapus)
- Tombol "+ Tambah Produk" (buka dialog pilih produk)
- Tombol "Tempel" (paste dari clipboard)
- Autosave (`markDirty`/`flushSaves`/debounce) — logika ini disalin persis dari `InvoicesPanel.vue`, tidak ditulis ulang dari nol
- Indikator status simpan ("Ada perubahan…"/"Menyimpan…"/"Tersimpan")

Props yang diterima: `invoice` (objek invoice beserta `items`). **Tidak ada lagi cabang `v-if="isApproved(inv)"` di dalam komponen ini** — perilakunya sama persis untuk invoice draft maupun yang sudah disetujui, dan sama persis dipanggil dari `InvoicesPanel.vue` maupun `Finance/Tour.vue` (D1, D8). Tidak perlu prop tambahan untuk membedakan konteks pemanggil — kalau nanti ada perbedaan tampilan yang genuinely dibutuhkan, itu keputusan baru, bukan diantisipasi sekarang.

`InvoicesPanel.vue` dan `Finance/Tour.vue` sama-sama mengganti blok Rincian Profit masing-masing dengan `<RincianProfitEditor :invoice="inv" />`. Baris 1008-1025 (cabang statis lama) di `InvoicesPanel.vue` dan blok statis di `Finance/Tour.vue` (baris 432-464) **dihapus**, digantikan komponen ini.

Tambahan kecil di kedua halaman: label yang menjelaskan bahwa perubahan pasca-approve tercatat di riwayat tour — mis. teks kecil di atas komponen, "Perubahan di sini setelah invoice disetujui akan tercatat di riwayat tour." Ini sekadar teks, bukan logika baru.

### 4.5 Route Finance/Tour untuk aksi item

`Finance/Tour.vue` butuh akses ke rute `invoice-items.*` yang sama (store/bulkStore/bulkUpdate/update/destroy) — sudah tercakup oleh perubahan §4.3 (grup route baru memasukkan `accountant`). Tidak ada route baru yang perlu dibuat, `RincianProfitEditor.vue` memanggil rute yang sama dari kedua konteks.

## 5. Yang sengaja tidak diubah

- **Kunci `exchange_rate` invoice saat approve** (D6) — dua lapis konfirmasi non-IDR tetap seperti sekarang.
- **Bill draft otomatis saat approve pertama kali** — `Bill::createMissingFromInvoice()` tetap dipanggil sekali saat `approve()`, tidak diubah.
- **Aturan satu tour satu invoice** — di luar cakupan, tidak berkaitan.
- **`bulkUpdate()` melewati id asing secara diam-diam** (temuan lama, [10-temuan.md](../../logika-pembuatan-invoice/10-temuan.md)) — bukan bagian dari perubahan ini, tidak diperbaiki di sini.

## 6. Pengujian

| Skenario | Yang dipastikan |
|---|---|
| Tambah item ke invoice yang sudah disetujui, oleh sales | Berhasil, `line_cost`/`line_sell` terhitung benar, riwayat tour bertambah satu baris |
| Tambah/ubah item, oleh akuntan | Berhasil (role `accountant` sekarang punya akses) |
| Ubah `unit_cost` item yang sudah punya Bill | Berhasil — Bill tidak ikut berubah |
| Hapus item yang **belum** punya Bill, invoice sudah disetujui | Berhasil, riwayat tour bertambah |
| Hapus item yang **sudah** punya Bill | **Ditolak**, pesan jelas, item maupun Bill tidak berubah |
| Edit item **sebelum** invoice disetujui | Berhasil seperti biasa, **tidak** ada baris riwayat baru (bukan pasca-approve) |
| Tagihan customer (`unit_price × pax`) sebelum dan sesudah edit Rincian Profit pasca-approve | **Identik** — tidak boleh bergeser sedikit pun |
| Role selain sales/akuntan/admin (mis. `guide`) | Tetap ditolak seperti sebelumnya |

## 7. Ringkasan alur baru

```
                    tour.status = 'confirmed'
                              │
                    ┌─────────▼─────────┐
                    │  + Buat Invoice   │
                    └─────────┬─────────┘
              ┌───────────────▼───────────────┐
              │  TAHAP 1-2 · Proforma+Patokan │
              └───────────────┬───────────────┘
                    ┌─────────▼─────────┐
                    │     Setujui       │  ← Rincian Profit TIDAK jadi syarat
                    └─────────┬─────────┘
              ┌───────────────▼───────────────┐
              │  TAHAP 3 · Sudah di Keuangan  │
              │  Rincian Profit TETAP TERBUKA │  ← BARU: tidak lagi terkunci
              │  (sales + akuntan/admin,      │
              │   dicatat di riwayat tour)    │
              │  pembayaran (DP/pelunasan)    │
              │  juga terbuka seperti sebelumnya │
              └───────────────────────────────┘
```
