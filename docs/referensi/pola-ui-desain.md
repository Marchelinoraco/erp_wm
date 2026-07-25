# Fondasi Desain — Welcome Manado ERP

> Dasar visual & pola UI yang dipakai bersama di seluruh modul. Dokumen modul lain (01-13) tidak mengulang ini — cukup rujuk balik ke sini. Mencerminkan kode per 17 Jul 2026.

## Stack

- **Backend**: Laravel 11, Inertia.js (server-driven SPA, tanpa REST API terpisah)
- **Frontend**: Vue 3 (`<script setup>`), Tailwind CSS, komponen UI dari **shadcn-vue** (`resources/js/Components/ui/*`: button, card, dialog, dropdown-menu, input, label, select, table, textarea, badge)
- **PDF**: mPDF (lewat `App\Support\Pdf` helper) — dipakai untuk invoice, quotation, laporan keuangan
- **Layout utama**: `resources/js/Layouts/AuthenticatedLayout.vue` — sidebar kiri tetap (`w-60`) + top bar + area konten `<slot>`, dibungkus tiap halaman lewat `<AuthenticatedLayout><template #header>...</template>...</AuthenticatedLayout>`

## Warna

Didefinisikan sebagai CSS variable HSL di `resources/css/app.css`, dipakai lewat kelas Tailwind (`bg-primary`, `text-muted-foreground`, dst — bukan warna hardcode) untuk elemen tema (button, ring, border). Untuk badge/indikator status per-entitas, halaman langsung pakai kelas Tailwind literal (lihat pola di bawah).

| Token | Nilai (light) | Dipakai untuk |
|---|---|---|
| `--primary` | `0 72% 51%` (merah, ~`red-600`) | Tombol utama, link aktif sidebar, ring fokus, logo aksen |
| `--background` / `--foreground` | putih / hampir-hitam | Latar halaman & teks dasar |
| `--muted-foreground` | abu `45%` | Teks sekunder/caption |
| `--destructive` | merah `84% 60%` | Aksi hapus/bahaya |
| `--border` | abu terang `89.8%` | Garis pembatas kartu/tabel |
| `--radius` | `0.5rem` | Radius default komponen shadcn |

Variabel `.dark` sudah didefinisikan di `app.css` tapi **belum ada toggle dark mode di UI** — aplikasi saat ini selalu render light mode.

**Badge warna per konteks** — pola berulang di banyak modul: tiap kategori (role, status, tipe) dapat pasangan warna `bg-*-100 text-*-700` (kadang `dot: bg-*-500` untuk indikator titik). Contoh dari `Users/Index.vue`:
```js
{ value: 'admin', color: 'bg-red-100 text-red-700', dot: 'bg-red-500' }
```
Palet yang sudah dipakai: merah (admin/bahaya), biru (sales/info), hijau (akuntansi/lunas/sukses), cyan (operation), pink (travel agent), ungu (guide), kuning (driver/partial/warning), oranye (tour leader), abu (draft/netral).

## Tipografi

- Font: **Figtree** (Google Fonts, weight 400/500/600/700), fallback `--font-sans`
- Skala umum di halaman: judul halaman `text-base font-semibold`, judul kartu/section `text-sm font-semibold`, body `text-sm`, caption/label `text-xs`, angka besar di kartu statistik `text-xl font-bold` s/d `text-2xl`
- Label kolom tabel: `text-xs text-gray-500 uppercase tracking-wide`

## Pola Layout Halaman

Struktur umum yang berulang di hampir semua halaman index (Tours, Customers, Products, Finance, dll):

```
<AuthenticatedLayout>
  <template #header> — judul halaman (h1 text-base font-semibold) + kadang subtitle/tombol aksi utama di kanan
  <div class="max-w-{5xl|6xl|7xl} mx-auto px-4 py-6 space-y-6">
    - Kartu statistik ringkas (grid, bg-white rounded-xl border shadow-sm p-4)
    - Kartu daftar/tabel utama (bg-white rounded-xl border shadow-sm, header px-5 py-4 border-b, isi overflow-x-auto)
  </div>
</AuthenticatedLayout>
```

- Kartu: `bg-white rounded-xl border border-gray-200 shadow-sm` — satuan visual utama di seluruh app (bukan `<Card>` shadcn secara konsisten; banyak halaman lama pakai div biasa dengan kelas ini langsung)
- Tabel: header `bg-gray-50`, baris `divide-y divide-gray-100`, hover `hover:bg-gray-50`
- Status/role/tipe → selalu badge pill kecil: `text-xs px-2 py-0.5 rounded-full font-medium` + pasangan warna dari tabel di atas
- Empty state: teks abu center di tengah kartu, mis. `px-5 py-8 text-center text-sm text-gray-400`
- Aksi per baris: ikon SVG kecil (edit/hapus), bukan teks tombol, biasanya di kolom paling kanan
- Dialog (Add/Edit/Confirm) pakai komponen shadcn `<Dialog>` — **hindari dua `<Dialog>` terbuka bersamaan** (pernah jadi bug nyata: overlay dialog pertama menghalangi klik dialog kedua, lihat catatan di modul Penjualan/Invoice)
- Semua elemen interaktif (kartu yang bisa diklik, baris tabel, tombol ikon) diberi `cursor-pointer` eksplisit

## Format Angka & Tanggal (Indonesia)

- Uang: helper `fmtRp`/`fmtCur` dari `resources/js/lib/fmt.js` — format `Rp` dengan pemisah ribuan gaya Indonesia
- Tanggal: `toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })` → mis. "17 Jul 2026"
- Semua label UI berbahasa Indonesia (termasuk pesan validasi & konfirmasi)

## Kontrol Akses di Level UI

Sidebar (`AuthenticatedLayout.vue`, fungsi `navGroups`) merender menu berbeda **total** per role — bukan menyembunyikan sebagian item, tapi memilih satu dari beberapa daftar menu penuh (`guide/driver/tour_leader` → hanya "Jadwal Saya"; `travel_agent` → hanya "Produk Saya"; `operation` → hanya "Booking"; `admin/sales` → menu lengkap Penjualan+Operasional+Data Master; `accountant` ditambah grup Keuangan). Detail lengkap aturan akses per role ada di [roles-permissions.md](roles-permissions.md) — dokumen itu sumber kebenaran untuk *siapa boleh apa*; dokumen modul di [design-system/](../design-system/README.md) fokus ke *bagaimana fitur bekerja*.

## Daftar Modul

Lihat [README.md](../design-system/README.md) untuk indeks lengkap modul.
