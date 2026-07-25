# Desain: Dokumentasi Per-Fitur ERP Welcome Manado

> **Status:** Disetujui (brainstorming 2026-07-25). Siap masuk ke rencana implementasi.

## 1. Tujuan

Menyediakan satu dokumen per fitur sehingga **sebelum menambah atau mengubah fitur, konteks kondisi yang sudah ada bisa langsung ditangkap dan dipahami** — apa yang sudah ada, bagaimana bekerjanya, apa yang tersentuh, dan apa yang tidak boleh dirusak. Dengan begitu proyek berkembang dengan rapi: perubahan pada satu fitur tidak diam-diam merusak fitur lain.

Ini **bukan** dokumentasi perencanaan produk (PRD formal). Fokusnya menangkap konteks *as-built* + keterkaitan + batasan, bukan persona/user story.

## 2. Prinsip & non-tujuan

**Prinsip:**
- Ditulis dari pembacaan kode, bukan spekulasi. Kalau kode berubah signifikan, dokumen fitur ikut diperbarui.
- Memakai ulang modal yang sudah ada (`design-system/`, `logika-pembuatan-invoice/`) alih-alih menulis ulang dari nol.
- Invarian lintas-fitur ditulis **sekali** di `ikhtisar-proyek.md` dan diwarisi semua fitur; tiap fitur hanya menambah jebakan khasnya.

**Non-tujuan (YAGNI):**
- Tidak membuat PRD formal (persona, user story, acceptance criteria) untuk sistem yang sudah jalan.
- Tidak melakukan refactor kode apa pun — ini murni dokumentasi.
- Tidak menyalin isi `logika-pembuatan-invoice/` ke dalam `fitur/invoice.md`; cukup ringkas + tautan ke deep-dive.

## 3. Struktur akhir `docs/`

```
docs/
  README.md                  ← peta induk (diperbarui: tambah bagian fitur/)
  ikhtisar-proyek.md         ← gambaran umum + "aturan main proyek" (invarian lintas-fitur)
  fitur/                     ← BARU: satu berkas per fitur
    README.md                ← KATALOG STATUS semua fitur (peta sudah/belum)
    <nama-fitur>.md          ← ~18 berkas
  logika-pembuatan-invoice/  ← tetap: deep-dive invoice (ditaut fitur/invoice.md)
  desain/                    ← tetap: usulan perubahan (ditaut per fitur)
  rencana/                   ← tetap: rencana implementasi (ditaut per fitur)
  referensi/                 ← tetap: acuan lintas-fitur
    roles-permissions.md
    pola-ui-desain.md        ← dari design-system/00-fondasi-desain.md (bukan fitur, tapi acuan UI bersama)
```

Folder `design-system/` **pensiun**: isinya bermigrasi ke `fitur/` (per-modul → per-fitur) dan `referensi/pola-ui-desain.md` (fondasi UI). Tidak ada konten yang dibuang.

## 4. Template tiap `fitur/<nama>.md`

```markdown
# [Nama Fitur]

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [desain/…], [rencana/…], [logika-…]

## 1. Ringkasan
Apa fitur ini & untuk siapa — orientasi cepat (2–4 kalimat).

## 2. Cara kerja (as-built)
Alur utama, model data (tabel & kolom kunci), route/controller, halaman Vue, file kunci.

## 3. Keterkaitan
Fitur / tabel / model lain yang tersentuh bila fitur ini diubah — "radius ledakan".

## 4. Batasan & jebakan ⚠️
Invarian yang WAJIB dijaga saat mengubah. Format: "jangan lakukan X karena Y".
Bagian terpenting untuk tujuan dokumen ini.

## 5. Status & yang belum
Bagian yang sudah jalan / sedang dikerjakan / backlog & ditunda.

## 6. Dokumen terkait
Tautan ke desain/, rencana/, logika-pembuatan-invoice/, referensi/.
```

Dua bagian kunci: **§3 Keterkaitan** (tahu apa lagi yang terpengaruh) dan **§4 Batasan & jebakan** (invarian yang kalau dilanggar merusak diam-diam). Sumber §4 sudah ada: `logika-pembuatan-invoice/10-temuan.md`, konsep inti `ikhtisar-proyek.md`, dan batasan di CHANGELOG.

## 5. Inventaris fitur & sumber migrasi

~18 fitur, dikelompokkan per domain. Kolom "Sumber" = dari mana konten "cara kerja" diambil.

| Domain | Berkas `fitur/` | Sumber utama |
|---|---|---|
| **Penjualan** | penjualan-tour.md | design-system/01 (+ M7 katalog paket, kepemilikan tour → rencana/tour-ownership) |
| | quotation.md | design-system/01 (bagian quotation) |
| | invoice.md | logika-pembuatan-invoice/ (ringkas + tautan), design-system/10 |
| | mice-template.md | design-system/01 (bagian MICE) + kode MiceTemplateController |
| **Master Data** | customer.md | design-system/05 |
| | produk.md | design-system/06 |
| | supplier.md | design-system/07 |
| | rekening-bank.md | design-system/08 |
| **Channel & Agent** | channel-manager-agent.md | design-system/02 |
| **Operasional** | booking.md | design-system/03 |
| | penugasan-lapangan.md | design-system/13 (Assignment/Manifest/MyJobs) |
| **Keuangan** | keuangan-ar-ap.md | design-system/10 |
| | keuangan-pembukuan.md | design-system/11 |
| | keuangan-aset-fiskal-pinjaman.md | design-system/12 |
| **Komunikasi** | email-brevo.md | desain/integrasi-brevo-email + kode TourEmail |
| | reminder-followup.md | design-system/04 |
| **Sistem & Akses** | peran-akses.md | design-system/09 + referensi/roles-permissions |
| | dashboard.md | kode DashboardController (pipeline + profit riil) |

Daftar final berkas bisa bergeser sedikit saat implementasi (mis. mice-template bisa dilebur ke penjualan-tour bila tipis). Keputusan pemecahan/peleburan diambil saat menulis tiap dokumen, dicatat di rencana.

## 6. Katalog status (`fitur/README.md`)

Tabel sekali-lihat, dikelompokkan per domain:

| Fitur | Peran | Status | Dokumen |
|---|---|---|---|
| Invoice | admin, sales, accountant | ✅ Berjalan (refactor per-jenis 🟡 di dev) | [invoice.md](invoice.md) |

**Taksonomi status:**
- **✅ Berjalan** — sudah di production
- **🟡 Sedang dikerjakan** — kode ada, belum rilis penuh (mis. refactor invoice per-jenis Fase 0–2 di `dev`)
- **⬜ Direncanakan** — baru ada desain/plan, belum dibangun

## 7. Eksekusi bertahap

- **Fase 1 — Kerangka + katalog status.** Buat `docs/fitur/README.md` berisi katalog status **semua ~18 fitur** (baris + status + peran + tautan placeholder), tetapkan template final, dan perbarui `docs/README.md` menunjuk `fitur/`. Peta "sudah/belum" langsung utuh sebelum dokumen dalam ditulis. Berkas fitur individual boleh masih kerangka (hanya §1 + status) pada fase ini.
- **Fase 2+ — Isi per domain.** Lengkapi dokumen fitur, satu domain per batch, mulai **Penjualan** (paling inti & paling banyak keterkaitan). Tiap domain: migrasi konten `design-system` terkait → isi §2–§4 dari kode → tautkan desain/rencana. Setelah semua fitur suatu domain lengkap, hapus berkas `design-system` yang sudah bermigrasi.
- Pindahkan `design-system/00-fondasi-desain.md` → `referensi/pola-ui-desain.md` di Fase 1 (acuan UI bersama, dipakai semua fitur).

Urutan domain yang disarankan: Penjualan → Keuangan → Master Data → Operasional → Komunikasi → Channel/Agent → Sistem & Akses.

## 8. Aturan pemeliharaan

- Setiap menamb/mengubah fitur, perbarui `fitur/<nama>.md` terkait (minimal §4 Batasan & §5 Status) dan baris katalog.
- Invarian baru yang berlaku lintas-fitur masuk ke `ikhtisar-proyek.md`, bukan diulang di tiap fitur.
- Katalog `fitur/README.md` adalah sumber kebenaran status; CHANGELOG mencatat perubahan yang terasa pengguna, katalog mencatat keadaan sekarang.

## 9. Kriteria selesai

- `docs/fitur/README.md` memuat seluruh fitur dengan status akurat.
- Tiap fitur punya berkas dengan §1–§6 terisi (kecuali fitur ⬜ Direncanakan yang cukup §1 + §5 + tautan).
- `design-system/` kosong dan dihapus; semua rujukan lamanya diperbarui.
- Nol tautan mati (verifikasi otomatis seperti pada reorganisasi sebelumnya).
