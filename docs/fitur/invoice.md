# Invoice

> **Status:** ✅ Berjalan (refactor per-jenis 🟡 di `dev`) · **Peran:** admin, sales, accountant · **Sejak:** Jun 2026
> **Terkait:** [logika-pembuatan-invoice/README.md](../logika-pembuatan-invoice/README.md), [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md), [rencana/README.md](../rencana/README.md)

## 1. Ringkasan

Alur penagihan dua tahap (Patokan → Rincian → Setujui) dengan nomor gapless `INV-<tahun>-NNNN` ditetapkan saat disetujui, mendukung multi-mata-uang dengan kurs per pembayaran, dan biaya tambahan yang diajukan sales saat tour berjalan lalu tampil sebagai baris "Additional". Begitu disetujui, invoice terkunci dan menjadi gerbang masuk ke modul Keuangan (AR) serta memicu pembuatan Bill draft otomatis untuk tiap item bersupplier. Dipakai oleh admin & sales (pembuatan/persetujuan) dan accountant (status/pembayaran/laporan); detail lengkap alur per-tahap ada di deep-dive [`logika-pembuatan-invoice/`](../logika-pembuatan-invoice/README.md).

## 2. Cara kerja (as-built)

> Ringkasan alur — untuk kondisi lengkap per langkah (siapa boleh apa, apa yang ditegakkan server vs UI, semua kasus tepi), baca deep-dive [`logika-pembuatan-invoice/`](../logika-pembuatan-invoice/README.md). Bagian ini sengaja tidak mengulangnya.

Satu tour hanya boleh punya **satu invoice** (`InvoiceController::store` menolak kalau sudah ada). Invoice tidak punya kolom status terpisah untuk tahap sales — tahapnya **diturunkan** dari data (`Invoice::stage`, computed, bukan kolom):

1. **Proforma / Patokan (`baseline`)** — sales mengisi mata uang, `unit_price` (per pax), baris deskripsi bebas, dan rekening bank yang ditampilkan di PDF. Total dihitung `unit_price × pax` (lewat `SalesLineRuleRegistry` sejak refactor per-jenis Fase 1 — hasil hitung identik dengan rumus lama, lihat §5). Semua masih bebas diubah.
2. **Rincian (`detail`)** — begitu patokan dikunci (`lockBaseline`, snapshot `baseline_total` dari total saat itu), sales mengisi **Rincian Profit** (`invoice_items`, panel `InvoicesPanel.vue`) — tabel modal-vs-jual internal per invoice, terpisah dari `tour_items`, dengan snapshot harga & `line_cost`/`line_sell` generated column serupa pola `tour_items`. Baris ini **tidak memengaruhi tagihan customer** — tagihan murni `unit_price × pax`. Sales bisa "Samakan Patokan" ulang selama belum approve.
3. **Setujui (`approved`)** — `approved_at` terisi lewat `InvoiceController::approve`. Ini gerbang satu arah ke Keuangan: nomor keuangan gapless dibuat (`Invoice::nextFinanceNumber()`, format `INV-<tahun>-NNNN`, terpisah dari nomor invoice biasa), dan untuk mata uang non-IDR **kurs wajib diisi saat approve** → `total_idr` dihitung & dibekukan. `Bill::createMissingFromInvoice()` otomatis membuat Bill draft (nominal 0) untuk tiap item Rincian Profit bersupplier — akuntan tinggal isi nominal riilnya.

**Nomor invoice** (`Invoice::nextNumber`) formatnya `INV-<tahun>-<kode tipe>-NNNN` — kode tipe sama dengan `Tour::resolveTypeCode()` (lihat [penjualan-tour.md](penjualan-tour.md)), urut sesuai kapan invoice **dibuat**. Nomor keuangan (`finance_number`) terpisah, `INV-<tahun>-NNNN` tanpa kode tipe, urut sesuai kapan **disetujui** — dua penomoran ini sengaja beda makna: satu untuk pelacakan sales per-tipe, satu untuk urutan masuk buku Keuangan.

**Pembayaran** (`invoice_payments`) dicatat dari panel tour oleh sales (DP/cicilan) atau dari Keuangan oleh akuntan. Tiap pembayaran punya `cash_account_id` (lihat [rekening-bank.md](rekening-bank.md)) dan `exchange_rate` sendiri — DP dan pelunasan boleh pakai kurs berbeda, masing-masing dikonversi ke `amount_idr` sendiri-sendiri lalu dijumlah untuk total diterima, bukan re-konversi total pakai satu kurs.

**Biaya Tambahan (Cost Request)** — jalur terpisah dari Rincian Profit, untuk biaya tak terduga **setelah** tour berjalan (Rincian Profit sudah terkunci pasca-approve): sales ajukan (`cost-requests.store`, status `pending`, bisa dibatalkan sendiri selama pending), akuntan approve (boleh sesuaikan nominal final) atau reject (wajib alasan). Approve **selalu** membuat `Bill` baru (AP ke supplier); **opsional** (`bill_customer`), sekaligus menagih ke customer lewat `CostRequestController::appendAdditionalCharge()` — menambah baris "Additional" langsung ke `description_lines` invoice yang sudah approved dan menambah `total`/`total_idr` invoice itu secara langsung (**bukan** lewat `unit_price × pax`). Hanya berlaku untuk invoice bermata uang IDR.

## 3. Keterkaitan

- **Tour** ([penjualan-tour.md](penjualan-tour.md)) — satu invoice per tour; tipe `tour` dengan invoice approved mengganti sumber angka profit tour (lihat §2 di sana).
- **Bill / biaya tambahan** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — Bill draft dibuat otomatis saat approve; Cost Request approve juga membuat Bill.
- **InvoicePayment** — pembayaran DP/pelunasan, tiap baris punya kurs & rekening kas sendiri; lihat [rekening-bank.md](rekening-bank.md) untuk `cash_account_id`.
- **Keuangan — AR** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — invoice approved = gerbang masuk piutang; akuntan hanya bisa mengubah tanggal/status/catatan, tidak nominal.
- **Supplier** ([supplier.md](supplier.md)) — sumber `supplier_id` produk yang menentukan Bill mana yang dibuat otomatis dari item Rincian Profit.

## 4. Batasan & jebakan ⚠️

- **Invoice yang sudah disetujui tidak dihitung ulang.** Begitu `approved_at` terisi, invoice **terkunci** — proforma, baseline, item Rincian Profit, dan due date tidak bisa diubah lagi lewat sisi sales (`ensureNotApproved()`/`ensureEditable()` di tiap method). Tidak ada mekanisme revisi: sales tidak bisa mengubah/menghapusnya, dan tidak bisa membuat invoice pengganti (aturan satu tour satu invoice tetap berlaku). Menghitung ulang invoice yang sudah disetujui akan menghapus/mendistorsi uang yang sudah ditagihkan/dibayar — lihat invarian di [ikhtisar-proyek.md §3.6](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa) dan deep-dive [06-penguncian-setelah-disetujui.md](../logika-pembuatan-invoice/06-penguncian-setelah-disetujui.md).
- **`total` ≠ `unit_price × pengali`, karena biaya tambahan.** Rumus dasar tahap Proforma memang `total = unit_price × pax`, tapi begitu ada Cost Request yang disetujui dengan `bill_customer` dicentang, `total` dan `total_idr` invoice **ditambah langsung** (`CostRequestController::appendAdditionalCharge()`) di luar rumus itu — invoice tidak "dihitung ulang" dari `unit_price`, baris "Additional" ditempel di atas total yang sudah ada. Jangan mengasumsikan `total` selalu bisa diturunkan balik dari `unit_price × pax` begitu ada baris Additional di `description_lines`.
- **Refactor "Pemisahan Aturan Invoice per Jenis" 🟡 sedang berjalan paralel** (kontrak `SalesLineInvoiceRule` + `SalesLineRuleRegistry`, per jenis penjualan). Fase 0–2 selesai di `dev` (kunci perilaku lama via characterization test, kontrak+registry, migrasi kolom+backfill `sales_line`) — hasil hitung `unit_price × pax` **belum berubah** secara terlihat, tapi jalur internalnya sudah lewat registry. Jangan menganggap `syncProformaTotal()` masih rumus hardcode sederhana saat menelusuri kode Fase 3 ke atas. Detail: [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md).
- **Label "Harga / pax" dipaksakan pada tipe yang sebenarnya ditagih per hari/unit/dokumen** (guide, rental, document, ticketing) — karena `total = unit_price × pax` berlaku tanpa kecuali untuk ketujuh tipe, sales pada tipe ini kadang terpaksa membagi nilai tagihan dengan `tour.pax` agar totalnya benar. Lihat [10-temuan.md §10.4](../logika-pembuatan-invoice/10-temuan.md#104-harga--pax-dipaksakan-pada-tipe-yang-ditagih-per-job) — ini akar masalah yang coba diperbaiki Fase 3 refactor per-jenis.
- **`pax` sumber utamanya tour, bukan invoice** — mengubah `tour.pax` setelah patokan dikunci bisa membuat total proforma bergeser dan tombol "Setujui" mati tanpa pesan penjelas (`baselineMatched()` gagal diam-diam). Lihat [10-temuan.md §10.3](../logika-pembuatan-invoice/10-temuan.md#103-perubahan-pax-tour-dapat-memblokir-persetujuan-tanpa-penjelasan).

## 5. Status & yang belum

Berjalan penuh di production. Sedang berjalan paralel refactor **"Pemisahan Aturan Invoice per Jenis Penjualan"** (kontrak `SalesLineInvoiceRule` per jenis penjualan): Fase 0 (characterization test) dan Fase 1 (kontrak + registry + 7 aturan) selesai di `dev`; Fase 2 (migrasi kolom + backfill `sales_line`) selesai di `dev` dan lulus uji staging, tapi **belum dijalankan ke production**. Fase 3 (pengali bisa diedit + label per jenis), Fase 4 (label di PDF), dan Fase 5 (pemecahan definisi frontend) belum ada plan. Lihat [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md) dan [rencana/README.md](../rencana/README.md).

## 6. Dokumen terkait

- [`logika-pembuatan-invoice/`](../logika-pembuatan-invoice/README.md) — deep-dive lengkap: prasyarat & pembuatan, tiap tahap, penguncian, pembayaran, perbedaan per tipe, matriks kondisi, dan temuan celah/perilaku yang perlu diketahui
- [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md) — desain & protokol keamanan data refactor per-jenis
- [rencana/README.md](../rencana/README.md) — rencana per fase refactor
- [penjualan-tour.md](penjualan-tour.md) — modul induk, kode tipe & profit tour
- [keuangan-ar-ap.md](keuangan-ar-ap.md), [rekening-bank.md](rekening-bank.md), [supplier.md](supplier.md) — fitur terkait (lihat §3)
- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — invarian lintas-fitur
