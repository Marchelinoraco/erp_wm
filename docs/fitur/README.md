# Peta Fitur — Katalog Status

Katalog status seluruh fitur ERP Welcome Manado, satu baris per fitur, menaut ke berkas `fitur/<nama>.md` masing-masing. Dipakai sebagai titik masuk **sebelum menambah atau mengubah fitur** — cek dulu apakah fitur itu sudah berjalan dan siapa yang memakainya, lalu baca berkasnya untuk memahami cara kerja, keterkaitan ("radius ledakan" ke fitur lain), dan batasan/jebakan yang wajib dijaga.

## Cara baca

- Kolom **Peran** = role (`users.role`) yang memakai fitur ini. Detail hak akses penuh per role ada di [referensi/roles-permissions.md](../referensi/roles-permissions.md).
- Kolom **Status** memakai taksonomi di bawah. Sub-status dalam kurung (mis. pada Invoice) menandai ada pekerjaan lanjutan yang belum rilis penuh, meski fitur intinya sudah berjalan.
- Kolom **Dokumen** menaut ke berkas fitur. Tiap berkas memakai template 6-bagian (Ringkasan · Cara kerja · Keterkaitan · Batasan & jebakan · Status & yang belum · Dokumen terkait). Berkas yang baru berisi §1+§5 (belum §2–§4/§6) ditandai **(kerangka)** — dilengkapi bertahap per domain, lihat [rencana implementasi](../superpowers/plans/2026-07-25-dokumentasi-per-fitur.md).

## Taksonomi status

| Status | Arti |
|---|---|
| ✅ Berjalan | Sudah di production |
| 🟡 Sedang dikerjakan | Kode ada, belum rilis penuh |
| ⬜ Direncanakan | Baru ada desain/plan, belum dibangun |

## Penjualan

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Penjualan Tour (Tour/Rental/Guide/Visa-Paspor/Ticketing/MICE/Hotel) | admin, sales | ✅ Berjalan | [penjualan-tour.md](penjualan-tour.md) |
| Quotation | admin, sales | ✅ Berjalan | [quotation.md](quotation.md) |
| Invoice | admin, sales, accountant | ✅ Berjalan (refactor per-jenis 🟡 di `dev`) | [invoice.md](invoice.md) |
| Template MICE | admin, sales | ✅ Berjalan | [mice-template.md](mice-template.md) |

## Master Data

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Customer | admin, sales | ✅ Berjalan | [customer.md](customer.md) |
| Produk | admin, sales | ✅ Berjalan | [produk.md](produk.md) |
| Supplier | admin, sales | ✅ Berjalan | [supplier.md](supplier.md) |
| Rekening Bank | admin, sales, accountant | ✅ Berjalan | [rekening-bank.md](rekening-bank.md) |

## Channel & Agent

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Channel Manager & Produk Agent | admin, sales, travel_agent | ✅ Berjalan | [channel-manager-agent.md](channel-manager-agent.md) *(kerangka)* |

## Operasional

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Booking | admin, sales, operation | ✅ Berjalan | [booking.md](booking.md) *(kerangka)* |
| Penugasan Lapangan (Assignment / My Jobs / Manifest) | admin, sales, guide, driver, tour_leader | ✅ Berjalan | [penugasan-lapangan.md](penugasan-lapangan.md) *(kerangka)* |

## Keuangan

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Keuangan — AR/AP | admin, accountant | ✅ Berjalan | [keuangan-ar-ap.md](keuangan-ar-ap.md) |
| Keuangan — Pembukuan | admin, accountant | ✅ Berjalan | [keuangan-pembukuan.md](keuangan-pembukuan.md) |
| Keuangan — Aset Tetap, Fiskal & Pinjaman | admin, accountant | ✅ Berjalan | [keuangan-aset-fiskal-pinjaman.md](keuangan-aset-fiskal-pinjaman.md) |

## Komunikasi

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Email (Brevo) | admin, sales | ✅ Berjalan | [email-brevo.md](email-brevo.md) *(kerangka)* |
| Reminder & Follow-up | admin, sales | ✅ Berjalan | [reminder-followup.md](reminder-followup.md) *(kerangka)* |

## Sistem & Akses

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Peran & Akses (Kelola Akun) | admin | ✅ Berjalan | [peran-akses.md](peran-akses.md) *(kerangka)* |
| Dashboard | admin, sales | ✅ Berjalan | [dashboard.md](dashboard.md) *(kerangka)* |
