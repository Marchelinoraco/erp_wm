# Quotation

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md)

## 1. Ringkasan

Penawaran harga ke customer sebelum tour deal, dikelola lewat `quotation_items` yang independen dari `tour_items` — tiap item berstatus proposed/approved/rejected, dan item yang approved otomatis dikonversi jadi item tour riil saat status tour berubah menjadi confirmed. Mendukung kalkulator harga per-pax (mode Per Kendaraan / Per Jumlah Pax), PDF branded, dan ekspor Word untuk dikirim ke customer, termasuk itinerary dan field customer-facing (included/excluded/child policy/terms) yang menghormati field yang sengaja dikosongkan. Dipakai oleh admin & sales sebagai bagian dari panel Tour.

## 2. Cara kerja (as-built)

### Item quotation, independen dari item tour

`quotation_items` (`QItemsPanel.vue`, `QuotationItemController`) adalah daftar produk yang diajukan ke customer **sebelum** deal — terpisah dari `tour_items` (yang baru terisi setelah confirmed). Tiap item punya `product_id` (opsional — boleh baris bebas tanpa produk katalog), `qty`, `nights`, `unit_sell`, dan `status` (`proposed`/`approved`/`rejected`). Menambah item lewat katalog produk otomatis menebak `pax_mode` (lihat di bawah) dari tipe produk: `transport`/`guide` → `shared`, selain itu → `per_pax`. Sales/customer menandai mana yang disetujui; saat tour di-set `confirmed`, item berstatus `approved` otomatis dikonversi jadi `tour_items` riil (`TourController::convertApprovedQuotationItems` — lihat [penjualan-tour.md](penjualan-tour.md)). **Begitu tour berstatus `confirmed`, seluruh endpoint `quotation-items.*` (store/update/destroy) ditolak 403** — quotation dikunci begitu tour deal.

### Kalkulator Harga per Pax

Panel `QItemsPanel.vue` punya kalkulator interaktif (murni sisi klien, tidak mengubah data tersimpan sampai "Terapkan ke Quotation" ditekan) dengan dua mode:
- **Per Jumlah Pax** — membandingkan harga per orang untuk beberapa skenario jumlah pax sekaligus. Item `per_pax` (mis. tiket masuk per kepala) tetap nilainya berapa pun jumlah pax; item `shared` (mis. transport/guide, dibagi rata rombongan) mengecil per kepala saat pax bertambah.
- **Per Kendaraan** — untuk kasus armada campuran: sales memasukkan opsi kendaraan (nama, biaya total, kapasitas muat), dan kalkulator menghitung harga per pax per opsi kendaraan (biaya kendaraan + biaya item `shared` lain) ÷ kapasitas muat + item `per_pax`.

Kedua mode punya field **Markup %** bersama; harga akhir dibulatkan ke atas ke kelipatan Rp 1.000. Hasil kalkulasi hanya membantu menentukan `unit_sell` — tidak tersimpan sebagai entitas sendiri.

### Budget gauge (khusus tipe MICE)

Kalau tipe tour `mice` dan `tour.budget` terisi, panel yang sama menampilkan gauge budget: total `quotation_items` berstatus approved dibandingkan `tour.budget`, dengan indikator warna (hijau/kuning/merah) dan peringatan margin (`sell` dibanding estimasi modal) — lihat [mice-template.md](mice-template.md) untuk konteks tipe MICE.

### PDF & ekspor Word

`QuotationController` menghasilkan dokumen dari HTML yang sama (`renderHtml()`), dipakai bersama oleh tiga endpoint:
- `quotation.preview` / `quotation.download` — PDF (mPDF, format A4) untuk dilihat/diunduh.
- `quotation.word` — HTML yang sama, dengan tag khusus mPDF (`<htmlpagefooter>`/`<htmlpageheader>`) dibuang dan namespace Office + page-setup A4 disisipkan, dikirim dengan `Content-Type: application/msword` — dibuka & bisa langsung diedit di Microsoft Word, tanpa library tambahan.

Ada **dua template Blade berbeda** dipilih lewat `$isTour = $tour->type === 'tour'`: `quotation.blade.php` (tipe `tour`, memuat itinerary harian/jam) vs `quotation_service.blade.php` (tipe lain, memuat `quotation_items` sebagai daftar layanan). Untuk tipe `tour`, itinerary (`itineraryDays`/`itineraryHours`) ikut dirender ke PDF; untuk tipe lain, tidak ada konsep itinerary.

### Field customer-facing & field sengaja dikosongkan

Field `included`/`excluded`/`child_policy`/`terms`/`price_validity` tersimpan di tabel `tours` (bahasa Inggris, karena customer-facing — lihat `Tour::TYPES_EN`/`DETAIL_LABELS`). `renderHtml()` membedakan **`null`** (belum pernah diisi → jatuh ke teks default dari `config('quotation.*')`) dari **string kosong `''`** (sengaja dikosongkan sales → dihormati, tidak ditampilkan) memakai `??`, bukan `?:` — supaya string kosong tidak ikut jatuh balik ke default. Lihat §4.

## 3. Keterkaitan

- **Tour** ([penjualan-tour.md](penjualan-tour.md)) — quotation menempel ke satu tour; item approved dikonversi jadi `tour_items` saat confirmed; field customer-facing tersimpan langsung di tabel `tours`.
- **Produk** ([produk.md](produk.md)) — `quotation_items.product_id` opsional merujuk katalog produk; memilih produk transport/guide otomatis menebak `pax_mode: shared`.
- **Customer** ([customer.md](customer.md)) — PDF/Word ditujukan ke `tour.customer`.
- **MICE Template** ([mice-template.md](mice-template.md)) — template MICE mengisi `quotation_items` lewat `mice-templates.apply`, bukan `tour_items` langsung.

## 4. Batasan & jebakan ⚠️

- **Quotation terkunci begitu tour `confirmed`.** `QuotationItemController::store/update/destroy` menolak 403 begitu `tour.status === 'confirmed'` — perubahan penawaran setelah deal harus lewat item tour (`tour_items`), bukan quotation.
- **Field kosong ≠ field belum diisi.** `included`/`excluded`/`child_policy` memakai perbedaan `null` vs `''` untuk membedakan "belum pernah diisi (pakai default)" dari "sengaja dikosongkan sales (jangan tampilkan apa pun)". Jangan mengubah `??` menjadi `?:` di `QuotationController::renderHtml()` — itu akan membuat pengosongan sengaja jatuh balik ke teks default lagi.
- **Kalkulator harga per pax murni alat bantu di sisi klien** — hasil hitungnya tidak tersimpan sebagai data sampai sales menekan "Terapkan ke Quotation" (yang menulis ke `unit_sell` item terkait). Me-refresh halaman sebelum menerapkan akan kehilangan hasil kalkulasi.
- **Dua template PDF berbeda** (`quotation.blade.php` vs `quotation_service.blade.php`) dipilih murni dari `tour->type === 'tour'` — field yang ada di satu template (mis. itinerary) tidak otomatis tersedia di template lainnya; perubahan pada satu tidak berlaku untuk yang lain.

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP; ekspor Word ditambahkan menyusul (akhir Juni 2026).

## 6. Dokumen terkait

- [penjualan-tour.md](penjualan-tour.md) — modul induk tempat quotation menempel
- [pola-ui-desain.md](../referensi/pola-ui-desain.md) — fondasi UI/warna/layout bersama
- [mice-template.md](mice-template.md) — pemakaian `quotation_items` sebagai target template MICE
- [produk.md](produk.md), [customer.md](customer.md) — sumber data terkait
