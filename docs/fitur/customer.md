# Customer

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [email-brevo.md](email-brevo.md), [supplier.md](supplier.md)

## 1. Ringkasan

Data master pelanggan (satu tabel `customers` sederhana) yang jadi sumber `customer_id` untuk tour, dengan empat tipe (`direct`, `agent`, `corporate`, `buyer`) yang menentukan perlakuan khusus — tipe `buyer` (travel agent yang membeli tour) mewajibkan nama tamu asli diisi agar tim lapangan tidak melihat identitas agen. Punya halaman Riwayat yang merangkum semua inquiry/tour milik satu customer beserta timeline perubahan status. Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

### Empat tipe customer

Kolom `type` (string, default `direct`) **bukan DB enum** — nilainya cuma dijaga lewat validasi `CustomerController` (`in:agent,corporate,direct,buyer`). Tipe `buyer` ditambahkan belakangan tanpa migration baru (kolom sudah `string` biasa sejak awal):

| `type` | Arti |
|---|---|
| `direct` | Wisatawan langsung (default) |
| `agent` | Agen perantara umum |
| `corporate` | Klien korporat |
| `buyer` | Travel agent yang **membeli** tour dari Welcome Manado |

### Tipe `buyer` — arahnya kebalikan dari Supplier travel agent

Gampang tertukar dengan konsep di [supplier.md](supplier.md) karena sama-sama disebut "travel agent", tapi arahnya berlawanan: **Customer tipe `buyer`** = agen yang jadi *pelanggan* WM (baris data biasa, tidak punya akun login), sedangkan **Supplier `is_travel_agent`** = agen yang jadi *pemasok* WM dan punya akun login sendiri.

Efeknya di `TourController::store`/`update`: saat customer yang dipilih di form Tour bertipe `buyer`, field `tour.guest_name` menjadi wajib (`Rule::requiredIf(fn () => Customer::find($request->customer_id)?->type === 'buyer')`) — ini nama tamu asli yang berangkat, bukan nama agen. `Tour::maskCustomerForField()` lalu mengganti relasi `customer` tour tsb dengan objek `Customer` sintetis berisi `guest_name`/`guest_phone` (mewarisi `country` dari customer asli) sebelum data dikirim ke halaman tim lapangan (My Jobs, manifest publik) — identitas buyer yang sebenarnya tertagih tetap tampil di invoice/panel sales. Lihat [penjualan-tour.md §2](penjualan-tour.md#2-cara-kerja-as-built) ("Guest name/phone").

### Halaman Riwayat (`customers.show`)

Satu-satunya halaman non-CRUD di modul ini (`Customers/Show.vue`): info customer + tombol edit, 4 kartu statistik (total inquiries, jumlah confirmed, conversion rate, total revenue estimasi — `Σ tour_items.line_sell` dari **semua** tour customer via `withSum('items as total_sell', 'line_sell')` di `CustomerController::show`, jadi estimasi item, bukan angka invoice riil), tabel daftar tour milik customer, dan timeline `tour_histories` gabungan dari semua tour customer tsb diurut terbaru dulu.

### Dorong ke Brevo

Dropdown aksi "Dorong ke Brevo" per baris di `Customers/Index.vue` (dinonaktifkan kalau customer belum punya email) membuka dialog yang mengambil daftar list Brevo secara live saat dialog dibuka (bukan saat halaman dimuat, lewat `marketing.lists`), lalu `POST marketing.customers.push` → `MarketingContactController::push` memanggil `BrevoGateway::createContact()`. Ini murni push manual per customer, terpisah dari alur pengiriman email quotation/invoice — detail penuh Brevo ada di [email-brevo.md](email-brevo.md).

### Hapus customer — soft delete

`Customer` memakai trait `SoftDeletes` (kolom `deleted_at` ditambahkan lewat migration `2026_07_20_000000_add_soft_deletes_to_financial_tables.php`, sepaket dengan `invoices`/`invoice_payments`/`bills`/`bill_payments`/`tours`). `CustomerController::destroy` hanya memanggil `$customer->delete()` tanpa cek relasi — dengan trait ini itu jadi soft delete (mengisi `deleted_at`), bukan penghapusan baris. Tidak ada `withTrashed()`/`restore()` di manapun pada modul ini, jadi begitu di-*soft delete* customer hilang dari semua query (termasuk relasi `Tour::customer()`) tanpa jalur pemulihan lewat UI.

## 3. Keterkaitan

- **Penjualan Tour** ([penjualan-tour.md](penjualan-tour.md)) — sumber `customer_id`; tipe `buyer` memicu masking guest name/phone (lihat §2).
- **Supplier** ([supplier.md](supplier.md)) — konsep "travel agent" yang berlawanan arah, lihat §2.
- **Email (Brevo)** ([email-brevo.md](email-brevo.md)) — aksi "Dorong ke Brevo" per customer.

## 4. Batasan & jebakan ⚠️

- **`type` bukan DB enum, dan didefinisikan ulang di dua file Vue** — `TYPE_LABELS`/`TYPE_VARIANTS` ada terpisah di `Customers/Index.vue` dan `Customers/Show.vue` (tidak di-share lewat util bersama); menambah tipe baru berarti mengubah validasi backend + kedua file Vue secara manual.
- **Total revenue di halaman Riwayat adalah estimasi**, bukan angka invoice riil — dihitung dari `Σ tour_items.line_sell`, beda dengan cara profit tour tipe `tour` dihitung setelah invoice di-approve (lihat [penjualan-tour.md §2](penjualan-tour.md#2-cara-kerja-as-built), "Profit perkiraan vs aktual").
- **Soft delete aktif sejak 20 Juli 2026, tapi UI masih menyebut "dihapus permanen"** (`Customers/Index.vue`, dialog konfirmasi) dan tidak ada halaman/aksi restore — memulihkan customer yang terhapus hanya bisa manual lewat `php artisan tinker` atau query DB langsung.
- **Validasi tipe `buyer` hanya ditegakkan di sisi Tour**, bukan di `CustomerController` — customer bisa disimpan sebagai `buyer` tanpa syarat tambahan apa pun; kewajiban `guest_name` baru muncul saat customer tsb dipakai di form Tour.

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP. Soft delete ditambahkan pertengahan Juli 2026 (lihat §2/§4) — data customer yang dihapus lewat UI kini bisa dipulihkan lewat DB, tapi belum ada jalur pemulihan lewat aplikasi.

## 6. Dokumen terkait

- [penjualan-tour.md](penjualan-tour.md) — pemakaian `customer_id` & masking guest name/phone
- [email-brevo.md](email-brevo.md) — alur push kontak ke Brevo
- [supplier.md](supplier.md) — bedakan Customer `buyer` vs Supplier `is_travel_agent`
- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — soft delete pada data finansial (§2, "Berkembang setelah MVP")
