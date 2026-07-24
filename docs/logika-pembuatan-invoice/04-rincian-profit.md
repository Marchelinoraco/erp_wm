# 04 — Rincian Profit (Item Invoice)

[← 03 Patokan](03-tahap-2-patokan.md) · [Peta](README.md) · Berikutnya: [05 — Persetujuan](05-tahap-3-persetujuan.md)

---

## 4.1 Sifat dasar — tidak memengaruhi tagihan

Ini bagian yang paling sering disalahpahami, dan komentar di modelnya menyatakannya eksplisit:

```php
/**
 * Item invoice hanya untuk pantauan profit internal (cost vs sell, IDR) dan
 * TIDAK lagi menentukan total invoice — total berasal dari proforma
 * (unit_price × pax). Karena itu tak ada sinkronisasi total di sini.
 */
```

Menambah, mengubah, atau menghapus item **tidak** mengubah satu angka pun yang dilihat customer. Tagihan tetap `unit_price × pax`.

## 4.2 Kolom terhitung di database

`line_cost` dan `line_sell` adalah **stored generated column** — dihitung database, bukan aplikasi:

```php
protected $guarded = ['id', 'line_cost', 'line_sell'];
```

Keduanya dilindungi dari mass assignment karena mengisinya manual akan ditolak database. Rumusnya `qty × nights × unit_cost` dan `qty × nights × unit_sell`.

Agar angka di layar tetap responsif saat mengetik, UI menghitung sendiri secara lokal:

```js
function lineSellLocal(itemId) {
    const f = itemForms[itemId]
    return (Number(f.qty) || 0) * (Number(f.nights) || 0) * (Number(f.unit_sell) || 0)
}
```

Nilai lokal ini hanya untuk tampilan; yang tersimpan tetap hasil hitungan database.

## 4.3 Kondisi umum semua operasi item

| # | Kondisi | Ditegakkan di | Pesan galat |
|---|---|---|---|
| K-30 | `approved_at` masih `null` | `InvoiceItemController::ensureEditable()` | "Invoice sudah disetujui dan masuk Keuangan, tidak bisa diubah. Buat invoice tambahan bila ada perubahan." |

Berlaku untuk `store`, `bulkStore`, `bulkUpdate`, `update`, dan `destroy` — kelimanya memanggil `ensureEditable()` lebih dulu.

> Saran "buat invoice tambahan" di pesan galat itu tidak dapat dijalankan; aturan satu tour satu invoice menolaknya. Lihat [10-temuan.md §10.5](10-temuan.md).

## 4.4 Menambah item dari produk

Endpoint: `POST /invoices/{invoice}/items` → `InvoiceItemController::store()`

### Validasi

| Field | Aturan |
|---|---|
| `product_id` | `required\|exists:products,id` |
| `qty` | `integer\|min:1` |
| `nights` | `integer\|min:1` |
| `start_date` | `nullable\|date` |
| `end_date` | `nullable\|date\|after_or_equal:start_date` |

### Kondisi tanggal wajib

| # | Kondisi | Akibat |
|---|---|---|
| K-31 | `product.type` ∈ `['hotel','transport','guide']` | `start_date` dan `end_date` menjadi **required** |
| K-32 | tipe produk lain | tanggal opsional |

Daftarnya ada di `InvoiceItem::DATED_TYPES`. Alasannya: item bertanggal tampil ke tim lapangan (guide, supir, tour leader) di MyJobs dan manifest — tanpa tanggal, item tidak muncul di jadwal siapa pun.

> Perhatikan ini merujuk **tipe produk**, bukan tipe penjualan. Sebuah tour bertipe `rental` tetap wajib mengisi tanggal bila menambahkan produk bertipe `hotel`.

### Nilai awal dari produk

`InvoiceItem::fromProduct()` menyalin:

| Kolom item | Sumber |
|---|---|
| `product_id`, `product_type` | produk |
| `description` | `product.name` |
| `unit_cost` | `product.cost` |
| `unit_sell` | `product.sell` |
| `currency` | `product.currency` |
| `qty`, `nights` | `1` (kecuali dikirim lain) |
| `sort_order` | `max(sort_order) + 1` pada invoice ini |

Harga disalin sebagai snapshot; mengubah harga produk di master tidak mengubah item yang sudah ada.

### Perilaku tambahan di UI

| # | Kondisi | Perilaku |
|---|---|---|
| K-33 | Produk bertipe wajib-tanggal dipilih | `start_date` diprefill dari `tour.start_date` |
| K-34 | Produk bertipe opsional dipilih | tanggal dibiarkan kosong, agar tidak terisi tanpa sengaja |
| K-35 | `end_date` lebih awal dari `start_date` | `end_date` otomatis disamakan dengan `start_date` |
| K-36 | Produk bertipe `hotel` | `nights` dihitung otomatis dari selisih hari, minimal 1 |

## 4.5 Tempel massal dari clipboard

Endpoint: `POST /invoices/{invoice}/items/bulk` → `bulkStore()`

Item hasil tempel bersifat **manual** — tidak punya `product_id`, jadi tidak terhubung ke master produk maupun supplier.

### Validasi

| Field | Aturan | Default |
|---|---|---|
| `items` | `required\|array\|min:1\|max:200` | — |
| `items.*.description` | `required\|string\|max:500` | — |
| `items.*.product_type` | `nullable\|string\|max:50` | `null` |
| `items.*.qty` | `nullable\|integer\|min:1` | `1` |
| `items.*.nights` | `nullable\|integer\|min:1` | `1` |
| `items.*.unit_cost` | `nullable\|numeric\|min:0` | `0` |
| `items.*.unit_sell` | `nullable\|numeric\|min:0` | `0` |

### Kondisi penguraian di sisi UI

Parser di `parsedPasteRows` menerima tempelan bergaya Excel/Sheets (dipisah tab):

| # | Kondisi | Perilaku |
|---|---|---|
| K-37 | Baris seluruhnya kosong | dilewati |
| K-38 | Sel pertama = `deskripsi`/`description`/`total`/`profit`/`margin` | dilewati — ini header & baris ringkasan hasil tombol Salin |
| K-39 | Kurang dari 2 sel | dilewati |
| K-40 | Sel ke-2 bukan angka dan total sel ≥ 6 | kolom "Tipe" dianggap ada, offset kolom digeser 1 |
| K-41 | Label tipe tidak dikenali | `product_type` jadi `null` |

Angka diurai `parseNum()` yang menangani beragam format: `25.000` (ribuan gaya Indonesia), `25,000.00` (gaya Inggris), `Rp 25.000,50`. Aturannya: pemisah desimal adalah tanda baca **paling belakang**; sisanya pemisah ribuan.

Karena baris ringkasan dilewati, hasil tombol **Salin** dapat langsung ditempel kembali tanpa dibersihkan lebih dulu.

## 4.6 Autosave

Ini mekanisme paling rumit di panel, dan yang paling menentukan apakah data hilang atau tidak.

### Alur normal

```
ketikan → markDirty(itemId) → saveState = 'pending', timer direset 1500 ms
                                     │
                      1,5 detik tanpa ketikan
                                     ▼
                              flushSaves()
                                     │
              kelompokkan baris kotor per invoice
                                     ▼
        PATCH /invoices/{id}/items/bulk  (satu request, semua baris)
                                     ▼
                        saveState = 'saved'
```

### Kondisi di `flushSaves()`

| # | Kondisi | Perilaku |
|---|---|---|
| K-42 | `saveState === 'saving'` | langsung keluar — cegah request tumpang tindih |
| K-43 | Tidak ada baris kotor | jalankan `pendingAction` bila ada, lalu keluar |
| K-44 | Request gagal | baris dikembalikan ke `dirtyIds`, `saveState` → `pending` — **data tidak hilang** |
| K-45 | Ada ketikan baru selama request berjalan | jadwalkan flush lagi 300 ms; `pendingAction` tetap menunggu |
| K-46 | Selesai dan tidak ada baris kotor | jalankan `pendingAction` |

### `afterFlush()` — penjaga race

```js
function afterFlush(action) {
    if (!dirtyIds.value.size && saveState.value !== 'saving') return action()
    pendingAction = action
    flushSaves()
}
```

Aksi yang dibungkus `afterFlush`: **hapus item**, **kunci/samakan patokan**, **setujui**. Ketiganya berbahaya bila berjalan mendahului autosave — misalnya menyetujui invoice sementara perubahan cost terakhir belum tersimpan.

### Perlindungan data lain

| # | Kondisi | Perilaku |
|---|---|---|
| K-47 | Data server datang untuk baris yang sedang diedit | **diabaikan** — `if (dirtyIds.value.has(item.id)) return` |
| K-48 | Pengguna menutup/refresh tab dengan perubahan menggantung | `beforeunload` menampilkan peringatan browser |
| K-49 | Navigasi Inertia (GET) dengan perubahan menggantung | konfirmasi "Ada perubahan Rincian Profit yang belum tersimpan. Tetap tinggalkan halaman?" |
| K-50 | Item dihapus | id-nya dibuang dari `dirtyIds` lebih dulu — tak perlu menyimpan yang akan hilang |

### Kondisi di `bulkUpdate()` sisi server

| Field | Aturan |
|---|---|
| `items` | `required\|array\|min:1\|max:200` |
| `items.*.id` | `required\|integer` |
| `items.*.qty` | `sometimes\|integer\|min:1` |
| `items.*.nights` | `sometimes\|integer\|min:1` |
| `items.*.description` | `sometimes\|nullable\|string\|max:500` |
| `items.*.unit_cost` | `sometimes\|numeric\|min:0` |
| `items.*.unit_sell` | `sometimes\|numeric\|min:0` |
| `items.*.start_date` | `sometimes\|nullable\|date` |
| `items.*.end_date` | `sometimes\|nullable\|date\|after_or_equal:items.*.start_date` |

Pembaruan dibatasi pada item milik invoice tersebut:

```php
$items = $invoice->items()
    ->whereIn('id', collect($data['items'])->pluck('id'))
    ->get()
    ->keyBy('id');

foreach ($data['items'] as $row) {
    $items->get($row['id'])?->update(collect($row)->except('id')->all());
}
```

| # | Kondisi | Perilaku |
|---|---|---|
| K-51 | `id` milik invoice lain atau tidak ada | **dilewati diam-diam** (`?->`), bukan galat |

Ini melindungi dari perubahan lintas invoice, tetapi juga berarti id keliru tidak memunculkan peringatan apa pun.

## 4.7 Rumus profit

Bercabang menurut tipe penjualan — dijelaskan lengkap di [08-perbedaan-per-tipe.md](08-perbedaan-per-tipe.md).

| Tipe penjualan | Rumus |
|---|---|
| `tour` | tagihan customer (IDR) − Σ `line_cost` |
| tipe lain | Σ (`line_sell` − `line_cost`) |

| # | Kondisi | Hasil |
|---|---|---|
| K-52 | Tipe `tour`, mata uang non-IDR, kurs belum diisi | `invProfit()` mengembalikan `null` → UI menampilkan "kurs belum diisi" |
| K-53 | Tipe non-`tour` | profit selalu dapat dihitung, tidak bergantung kurs |
