# Template MICE

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [quotation.md](quotation.md)

## 1. Ringkasan

Sub-fitur khusus tipe penjualan MICE — template paket (`mice_templates`, kolom `items` JSON) yang bisa diterapkan cepat ke tour baru, atau sebaliknya tour MICE yang sudah diisi bisa disimpan sebagai template baru untuk dipakai ulang di event serupa. Tour tipe MICE juga satu-satunya yang punya kolom `budget` untuk dibandingkan terhadap estimasi biaya. Dipakai oleh admin & sales dari panel Tour saat tipe penjualan MICE.

## 2. Cara kerja (as-built)

`mice_templates` adalah tabel lepas (tidak terikat tour manapun) dengan kolom `items` (JSON) — daftar item siap pakai, tiap item punya `label`, `pax_mode` (`shared`/`per_pax`), `unit_sell`, `qty`, `nights`, `notes`. `MiceTemplatePanel.vue` (dirender hanya kalau `tour.type === 'mice'`) menyediakan tiga aksi lewat `MiceTemplateController`:

- **Terapkan ke tour** (`mice-templates.apply`) — bulk-insert seluruh `items` template sebagai `quotation_items` baru (status `proposed`, `sort_order` disambung dari item quotation yang sudah ada) — **bukan** langsung ke `tour_items`. Ditolak 403 kalau `tour.status === 'confirmed'` (sejalan dengan quotation yang terkunci setelah deal — lihat [quotation.md](quotation.md)).
- **Simpan dari tour** (`mice-templates.save-from-tour`) — sebaliknya, membaca `quotationItems` tour aktif yang statusnya bukan `rejected` (bukan `tour_items`) lalu menyimpannya sebagai template baru. Ditolak 422 kalau tidak ada item untuk disimpan.
- **Buat manual / kelola** (`mice-templates.store/update/destroy`) — CRUD template langsung lewat form, tanpa harus berangkat dari tour tertentu.

Template dan tour yang menerapkannya **tidak saling terhubung** setelah `apply` — hasilnya adalah baris `quotation_items` biasa yang independen dan bisa diedit/dihapus lepas dari template asalnya.

**Budget gauge** — meski nampak seperti bagian dari fitur ini, gauge budget (`tour.budget` vs total `quotation_items` approved, dengan warna hijau/kuning/merah) sebenarnya dirender di panel **Quotation Items** (`QItemsPanel.vue`, hanya tampil `isMice && budget > 0`), bukan di `MiceTemplatePanel.vue` — lihat [quotation.md §2](quotation.md#budget-gauge-khusus-tipe-mice).

## 3. Keterkaitan

- **Penjualan Tour** ([penjualan-tour.md](penjualan-tour.md)) — hanya aktif untuk tipe `mice`; panel dirender kondisional di `Tours/Edit.vue`.
- **Quotation** ([quotation.md](quotation.md)) — template beroperasi di atas `quotation_items`, bukan `tour_items`; budget gauge juga tinggal di panel Quotation Items.

## 4. Batasan & jebakan ⚠️

- **Template menulis ke `quotation_items`, bukan `tour_items`.** Menerapkan template ke tour yang sudah `confirmed` ditolak (403) karena quotation sudah terkunci saat itu — kalau perlu menambah item serupa template ke tour yang sudah deal, harus lewat panel Item Produk (`tour_items`) secara manual, fitur ini tidak menjangkau tahap itu.
- **Template lepas dari sumbernya setelah diterapkan** — tidak ada `template_id` yang disimpan di `quotation_items`. Mengubah template setelah diterapkan ke suatu tour tidak memengaruhi tour yang sudah memakainya.
- **"Simpan dari tour" mengabaikan item `rejected`** tapi tetap menyertakan `proposed` (belum tentu disetujui customer) — pastikan quotation sudah cukup matang sebelum disimpan sebagai template agar tidak menyebarkan item draft yang belum final.

## 5. Status & yang belum

Berjalan di production sejak akhir Juni 2026. Diputuskan **tetap berdiri sendiri** (tidak dilebur ke [penjualan-tour.md](penjualan-tour.md)) — kontennya cukup tebal untuk halaman sendiri: tabel `mice_templates`, lima route/method `MiceTemplateController`, panel Vue ~300 baris, dan keterkaitan tersendiri ke `quotation_items` yang berbeda dari alur item tour biasa.

## 6. Dokumen terkait

- [penjualan-tour.md](penjualan-tour.md) — modul induk, tipe penjualan MICE
- [quotation.md](quotation.md) — `quotation_items` (target apply/save-from-tour) dan budget gauge
- [pola-ui-desain.md](../referensi/pola-ui-desain.md) — fondasi UI/warna/layout bersama
