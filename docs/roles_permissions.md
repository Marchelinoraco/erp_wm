# Role Rules & Permissions — Welcome Manado ERP

> Disusun dari `routes/web.php`, `app/Models/User.php`, dan `app/Http/Middleware/EnsureUserHasRole.php` per 17 Jul 2026. Dokumen ini murni mencerminkan kode saat ini — kalau ada perubahan role/route, perbarui juga file ini.

## Cara kerja pembatasan akses

- Setiap route (di luar `/profile` dan login) dibungkus middleware `role:<daftar-role>` ([`EnsureUserHasRole`](app/Http/Middleware/EnsureUserHasRole.php)) — kalau `$user->role` tidak ada di daftar, request JSON/Inertia ditolak **403**, request biasa **diarahkan ke halaman utama role tersebut** (`$user->homePath()`), bukan pesan error.
- **8 role** terdaftar di enum kolom `users.role`: `admin`, `sales`, `accountant`, `guide`, `driver`, `tour_leader`, `travel_agent`, `operation`.
- Halaman utama per role (`User::homePath()`, [User.php:49-58](app/Models/User.php#L49-L58)):

| Role | Halaman utama setelah login |
|---|---|
| `admin`, `sales` | Dashboard |
| `accountant` | Keuangan |
| `operation` | Booking |
| `travel_agent` | Produk Saya |
| `guide`, `driver`, `tour_leader` | My Jobs |

- **`guide`/`driver`/`tour_leader` selalu diperlakukan sebagai satu grup "field"** — tidak ada pembeda hak akses antar ketiganya di level route; bedanya cuma di data (`assignments.role`) dan tampilan.

---

## 1. Admin

Akses penuh ke **semua route berikut** (selalu ikut disertakan di setiap grup role) — admin adalah superset dari seluruh role lain. Tidak ada halaman yang admin **tidak** bisa akses.

## 2. Sales

- **Dashboard** & Pipeline
- **Master Data**: Suppliers, Products (+ harga periode, unduh/ekspor template), Customers (CRUD penuh)
- **Channel Manager** — review/approve harga produk dari travel agent
- **Tours** — CRUD penuh (kecuali `show`, yang dipakai `edit`): itinerary, item, histori, kirim email, assignment guide/driver
- **Invoice** — alur 2 tahap penuh: buat, isi proforma, kunci patokan, **setujui** (masuk Keuangan), hapus (sebelum approve), kelola item Rincian Profit, catat DP/pembayaran langsung dari panel tour, PDF (preview/download/rincian profit)
- **Biaya Tambahan** — ajukan (`cost-requests.store`) & batalkan pengajuan sendiri yang masih pending — **tidak bisa approve/reject** (itu hak akuntan)
- **Quotation** — buat/kelola item, download/preview PDF & Word
- **MICE Templates** — kelola & terapkan ke tour
- **Reminders** — CRUD penuh (miliknya sendiri, dijaga di level controller, bukan route)
- **Booking operasional** — akses bersama admin+operation
- **Rekening pembayaran** — bisa **tambah & edit**, **tidak bisa hapus** (khusus admin+akuntan)
- **PDF invoice customer** — preview/download/rincian profit (bersama admin+akuntan)

**Tidak bisa**: apa pun di bawah `/finance/*` kecuali rekening pembayaran & PDF invoice di atas (jurnal, buku besar, neraca, laba rugi, aset tetap, fiskal, pinjaman, transaksi manual, approve/reject biaya tambahan, catat pembayaran AR/AP resmi) — itu murni wilayah akuntan. Tidak bisa Kelola Akun (users).

> ⚠️ **Belum aktif**: rencana "sales hanya melihat tour miliknya sendiri" (lihat [docs/plan_tour_ownership.md](plan_tour_ownership.md)) sudah disetujui tapi **belum diimplementasikan** — saat ini semua sales melihat & bisa mengedit **semua** tour tanpa kecuali.

## 3. Accountant (Akuntansi)

- **Keuangan** (halaman utama) — daftar AR/AP, detail per tour
- **Laporan**: Arus Kas, Jurnal, Buku Besar, Rekap, Neraca, Laba Rugi, Saldo Akun Kas (+ semua unduhan PDF-nya)
- **Aset Tetap, Koreksi Fiskal (PPh Badan), Hutang & Pinjaman** — CRUD penuh
- **Transaksi manual, Kategori, Akun Kas** — CRUD penuh (kelola akun kas & kategori pembukuan)
- **Invoice** (dari sisi Keuangan) — hanya update status/tanggal/catatan (`invoices.update`) dan catat/hapus pembayaran AR — **tidak bisa membuat/menyetujui/mengubah rincian item invoice**, itu dikunci ke sales
- **Bill (AP ke supplier)** — CRUD penuh, catat/hapus pembayaran
- **Biaya Tambahan** — **approve/reject** pengajuan sales (satu-satunya role yang bisa, selain admin)
- **Rekening pembayaran** — tambah, edit, **dan hapus** (satu-satunya non-admin yang boleh hapus)
- **PDF invoice customer** — preview/download/rincian profit (bersama admin+sales)

**Tidak bisa**: apa pun di menu Penjualan (Tours, Customers, Products, Channel Manager), Booking, Reminders, Kelola Akun.

## 4. Operation

- **Booking operasional** — akses bersama admin+sales (index, buat, update, hapus booking eksekusi ke supplier)

Itu saja — role paling sempit setelah field. **Tidak** bisa akses Keuangan, Tours, Reminders, ataupun My Jobs.

## 5. Travel Agent (eksternal)

- **Produk Saya** (halaman utama) — kelola produk supplier miliknya sendiri (CRUD) + harga periode (ajukan, hapus)
- Harga yang diajukan masuk antrean **Channel Manager** untuk direview admin/sales

**Tidak bisa**: akses menu internal apa pun (Tours, Keuangan, Customers, dll).

## 6. Guide / Driver / Tour Leader (Field)

- **My Jobs** (halaman utama) — daftar tugas & detail tour yang **ditugaskan ke dirinya** (dicek di controller via tabel `assignments`, bukan cuma role gate)
- Melihat jadwal item berjadwal (hotel/transport/guide) **tanpa harga**
- Untuk tour dengan customer bertipe **Buyer**: melihat **nama & telepon tamu**, bukan data buyer/travel agent (kontak buyer disembunyikan — lihat `Tour::maskCustomerForField()`)

**Tidak bisa**: akses menu apa pun selain My Jobs — tidak ada Dashboard, Tours, Keuangan, dst.

---

## Akses lintas-role (bukan role gate biasa)

| Route/aksi | Siapa |
|---|---|
| `/profile` (edit profil sendiri) | **Semua role yang login** |
| PDF invoice customer (preview/download/rincian profit) | admin, sales, **accountant** |
| Rekening pembayaran — tambah/edit | admin, sales, **accountant** |
| Rekening pembayaran — **hapus** | admin, **accountant** saja (sales dikecualikan sejak commit `b972b50`) |
| Booking operasional | admin, sales, **operation** |
| Biaya Tambahan — ajukan/batalkan | admin, **sales** |
| Biaya Tambahan — approve/reject | admin, **accountant** |
| Manifest tour (`/manifest/{tour}`) | **Publik, tanpa login** — diamankan lewat signed URL (`middleware('signed')`), bukan role. Info kontak buyer ikut dimasking sama seperti My Jobs. |

## Yang perlu diperhatikan (potensi bug/gap, bukan disengaja)

- ~~`resources/js/Pages/Users/Index.vue` hanya mendaftar 6 role, `operation`/`travel_agent` tidak muncul~~ — **sudah diperbaiki (17 Jul 2026)**: `ROLES`/`GROUP_ORDER` sekarang mendaftar seluruh 8 role, termasuk `operation` dan `travel_agent`. Halaman Kelola Akun juga dirombak sekaligus (kartu statistik role bisa diklik untuk filter, kolom pencarian, empty state yang lebih jelas).
