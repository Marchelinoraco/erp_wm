# Desain: Satu Nomor Invoice (Sales, Keuangan, PDF)

> **Status:** DRAFT — menunggu persetujuan sebelum implementasi. ERP ini berjalan di production dan dipakai sales/akuntan sehari-hari; nomor invoice sudah pernah dikirim ke customer lewat PDF. Lihat §5 Protokol Keamanan Data sebelum menyetujui.

## 1. Masalah

Invoice punya **dua nomor berbeda** hari ini:

| Kolom | Format | Diberikan kapan | Dipakai di mana |
|---|---|---|---|
| `number` | `INV-<tahun>-<tipe>-NNNN` (invoice dibuat ≥16 Jul 2026) atau `INV-<tahun>-NNNN` (invoice lebih lama) | Saat invoice **dibuat** (Tahap 1 Proforma) | Sales (`InvoicesPanel.vue`), **PDF invoice** (`invoice.blade.php`) — termasuk yang dikirim ke customer sebelum disetujui |
| `finance_number` | `INV-<tahun>-NNNN`, satu deret gapless lintas semua tipe | Saat invoice **disetujui** (masuk Keuangan) | Halaman Keuangan (`Finance/Index.vue`, `Finance/Tour.vue`, `profit_breakdown.blade.php`) sebagai nomor utama |

Akuntan dan sales melihat **nomor berbeda** untuk invoice yang sama — membingungkan saat berkoordinasi (mis. sales bilang "invoice INV-2026-11-0004", akuntan mencatat "INV-2026-0002").

## 2. Keputusan yang dikunci (hasil diskusi)

1. **Satu nomor saja** untuk sales, Keuangan, dan PDF — bukan dua field terpisah.
2. Nomor itu adalah **`number` yang sudah ada** (bukan `finance_number`) — karena `number` sudah dipakai PDF sejak invoice dibuat, dan PDF **bisa dikirim ke customer sebelum invoice disetujui** (tidak ada guard approval di route download/preview). Menjadikan `finance_number` sebagai nomor tunggal berarti invoice draft tidak akan punya nomor sampai disetujui — merusak alur proforma-ke-customer yang sudah berjalan.
3. **Konsekuensi diterima:** karena `number` diberikan saat dibuat (bukan saat disetujui), urutannya tidak lagi 100% gapless dari sudut pandang Keuangan — kalau ada invoice draft (tipe sama) dibuat lalu dihapus sebelum disetujui, nomor itu "terlewat". Ini dianggap dapat diterima demi konsistensi nomor sejak proforma pertama.
4. **Kode tipe penjualan wajib ada di SEMUA invoice** — termasuk invoice lama (dibuat <16 Jul 2026) yang saat ini belum punya kode tipe (mis. `INV-2026-0009`). Perlu backfill.
5. **Backfill tidak boleh mengubah nomor yang sudah stabil** — invoice yang sudah lunas dan sudah dikirim ke customer (yang nomornya sudah punya kode tipe, dibuat ≥16 Jul) **tidak boleh digeser/diganti** hanya demi menyisipkan invoice lama secara kronologis persis. Invoice lama yang di-backfill **ditambahkan di akhir urutan** nomor tipe tersebut (dapat NNNN berikutnya yang tersedia), bukan disisipkan di awal dengan menggeser yang sudah ada. Ini konsisten dengan keputusan sebelumnya di §7.8-style protocol: nomor resmi yang sudah keluar tidak pernah berubah.

## 3. Arsitektur akhir

```
number  ← SATU-SATUNYA nomor invoice. Format INV-<tahun>-<tipe>-NNNN untuk SEMUA invoice
          setelah backfill. Diberikan saat invoice dibuat, tidak pernah berubah lagi.
          Dipakai: sales (InvoicesPanel.vue), PDF (invoice.blade.php) — TIDAK BERUBAH,
          sudah begini sejak awal.

finance_number ← PENSIUN. Kolom TETAP ADA di database (tidak di-drop — menghindari
          risiko schema change tak perlu), tapi:
          - Tidak lagi DIISI saat invoice disetujui (InvoiceController::approve()).
          - Tidak lagi DITAMPILKAN di halaman Keuangan.
          Nilai lama pada invoice yang sudah disetujui dibiarkan apa adanya di kolom
          (data historis, tidak dihapus), sekadar tidak dipakai lagi.
```

### Perubahan kode

- **`app/Http/Controllers/InvoiceController.php`** (`approve()`, sekitar baris 165): hapus baris yang mengisi `finance_number`.
- **`resources/js/Pages/Finance/Index.vue`** (baris 163-165, 225-227): ganti `inv.finance_number ?? inv.number` + baris kecil `inv.number` di bawahnya → tampilkan `inv.number` saja.
- **`resources/js/Pages/Finance/Tour.vue`** (baris 378-379, 528, 578, 885): pola sama — ganti ke `inv.number`/`invoice.number` saja.
- **`resources/views/finance/profit_breakdown.blade.php`** (baris 24): ganti ke `$invoice->number` saja.
- **`resources/js/Components/Tours/InvoicesPanel.vue`** (baris 698-701): hapus baris kecil "Keuangan: {{ inv.finance_number }}" — sudah tidak relevan (cuma ada satu nomor).
- **`tests/Feature/Invoice/ApprovalSideEffectsTest.php`**: hapus/tulis ulang `test_nomor_keuangan_tidak_membedakan_jenis_penjualan` (mengunci perilaku LAMA yang sengaja dibalik) dan `test_disetujui_dapat_nomor_keuangan` (baris ~26-36) — sesuaikan agar tidak lagi mengasersi keberadaan `finance_number` sebagai nomor yang terlihat pengguna.

### Backfill (Fase 2 — production data)

Command baru (pola sama seperti `BackfillInvoiceSalesLine` di refactor invoice per-jenis): identifikasi invoice dengan `number` yang **tidak** mengandung kode tipe (format `INV-<tahun>-NNNN`, 2 segmen setelah `INV-`, bukan 3), lalu untuk tiap invoice:
1. Tentukan kode tipe dari `tour->resolveTypeCode()` (fallback `'11'` kalau tour terhapus/tak ada — persis logika yang sudah dipakai `Invoice::nextNumber()` hari ini).
2. Ambil nomor NNNN **berikutnya yang tersedia** untuk (tahun, tipe) tersebut — yaitu, hitung dari `number` tertinggi yang SUDAH ada untuk (tahun, tipe) itu (yang dibuat ≥16 Jul), lalu +1. **Tidak** menyisipkan di awal / menggeser nomor yang sudah ada.
3. Update kolom `number` invoice tersebut ke format baru.

**Invarian yang wajib dijaga backfill (dan diverifikasi otomatis):**
- Nomor invoice yang **sudah punya kode tipe** (dibuat ≥16 Jul) **tidak boleh berubah sama sekali** — nol perubahan pada baris-baris ini.
- `total`, `total_idr`, `approved_at`, dan seluruh kolom lain **tidak tersentuh** — hanya string `number` yang berubah, dan hanya untuk invoice yang sebelumnya belum punya kode tipe.
- Idempoten — dijalankan dua kali tidak mengubah apa pun di jalan kedua (invoice yang sudah punya kode tipe di-skip).

## 4. Non-tujuan (YAGNI)

- Tidak drop kolom `finance_number` dari database — menyisakan data historis, mengurangi risiko.
- Tidak mengubah format `number` untuk invoice yang dibuat setelah perubahan ini disetujui — mekanismenya (`Invoice::nextNumber()`) sudah benar, tidak berubah.
- Tidak menyentuh alur approval, pembayaran, atau perhitungan total apa pun — murni penomoran.

## 5. Protokol Keamanan Data (WAJIB sebelum backfill jalan di production)

Mengikuti pola yang sama seperti migrasi `sales_line` sebelumnya:

1. Backup manual database production terbaru, verifikasi bisa di-restore.
2. Jalankan backfill di **staging** (`erp_wm_dev`) dengan **salinan data production nyata** (bukan data dummy).
3. Query verifikasi SEBELUM vs SESUDAH: pastikan **nol perubahan** pada `total`, `total_idr`, `approved_at`, dan pada `number` milik invoice yang sebelumnya SUDAH punya kode tipe.
4. Pastikan setiap invoice yang di-backfill sekarang punya `number` berformat lengkap (`INV-<tahun>-<tipe>-NNNN`), dan tidak ada duplikat `number` di database.
5. Test regresi penuh lolos (termasuk test baru untuk command backfill).
6. Command backfill bersifat idempoten — jalankan dua kali, hasil kedua sama dengan hasil pertama.
7. Rencana rollback (bila backfill perlu dibatalkan) diuji di salinan yang sama sebelum menyentuh production.

## 6. Kriteria selesai

- Sales, Keuangan, dan PDF menampilkan **nomor yang identik** untuk invoice yang sama.
- Invoice baru (dibuat setelah perubahan ini) otomatis punya satu nomor berkode tipe, seperti sekarang.
- Seluruh invoice lama (termasuk yang sudah lunas) punya `number` berkode tipe setelah backfill di production, dengan invoice yang sebelumnya sudah berkode tipe **tidak berubah nomornya sama sekali**.
- `finance_number` tidak lagi ditampilkan di mana pun, tidak lagi diisi untuk invoice baru.
