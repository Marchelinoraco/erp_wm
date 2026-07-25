# Rencana Implementasi: Dokumentasi Per-Fitur ERP Welcome Manado

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun `docs/fitur/` — satu dokumen per fitur (~18) plus katalog status induk — sehingga konteks kondisi yang sudah ada dapat ditangkap sebelum menambah/mengubah fitur.

**Architecture:** Struktur per-fitur menggantikan `design-system/` (per-modul). Tiap fitur satu berkas dengan template 6-bagian (Ringkasan · Cara kerja · Keterkaitan · Batasan & jebakan · Status · Dokumen terkait). Konten `design-system/` bermigrasi masuk; folder itu akhirnya dihapus. Katalog `fitur/README.md` = peta status sekali-lihat.

**Tech Stack:** Markdown murni. Tidak ada perubahan kode aplikasi. Verifikasi via `grep`/bash (cek tautan & kelengkapan bagian).

**Spec:** [`docs/superpowers/specs/2026-07-25-dokumentasi-per-fitur-design.md`](../specs/2026-07-25-dokumentasi-per-fitur-design.md)

## Global Constraints

- Bahasa Indonesia, konsisten dengan dokumen `docs/` yang ada.
- Ditulis dari pembacaan kode/dokumen nyata — **bukan spekulasi**. Kalau suatu perilaku tidak bisa dipastikan dari kode/dokumen sumber, tandai eksplisit ("belum diverifikasi"), jangan mengarang.
- Nama berkas **kebab-case**.
- Template fitur **wajib 6 bagian** persis (§1–§6 seperti di spec §4). Fitur berstatus ⬜ Direncanakan cukup §1 + §5 + §6.
- **Invarian lintas-fitur** (snapshot harga, profit=query, generated column, kunci invoice setelah approve) ditulis hanya di `docs/ikhtisar-proyek.md`; berkas fitur menautkannya, tidak menyalinnya. Tiap fitur §4 hanya menambah jebakan khasnya.
- **Nol tautan mati** setelah tiap task (jalankan Verification Helper).
- Berkas `design-system/*` dihapus **hanya setelah** kontennya bermigrasi ke fitur terkait dalam task yang sama.
- Isi `logika-pembuatan-invoice/` **tidak disalin** ke `fitur/invoice.md`; cukup ringkas + tautan.
- Setiap task diakhiri commit dengan footer `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

---

## Verification Helper (dipakai di banyak task)

Cek tautan `.md` relatif yang menunjuk berkas tak ada, di seluruh `docs/`:

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
while IFS= read -r line; do
  file="${line%%:*}"; rest="${line#*:}"
  echo "$rest" | grep -oE '\]\([^)]+\.md[^)]*\)' | sed -E 's/\]\(([^)#]+)(#[^)]*)?\)/\1/' | while read -r tgt; do
    case "$tgt" in http*) continue;; "") continue;; esac
    dir=$(dirname "$file")
    [ -f "$dir/$tgt" ] || echo "MATI: $file -> $tgt"
  done
done < <(grep -rn --include="*.md" -E '\]\([^)]+\.md' docs/ 2>/dev/null)
echo "SELESAI (tidak ada baris MATI = semua tautan valid)"
```

Cek kelengkapan 6 bagian pada satu berkas fitur:

```bash
f=docs/fitur/<nama>.md
for h in "## 1. Ringkasan" "## 2. Cara kerja" "## 3. Keterkaitan" "## 4. Batasan" "## 5. Status" "## 6. Dokumen terkait"; do
  grep -q "$h" "$f" && echo "OK  $h" || echo "HILANG  $h"
done
```

---

## Task 1: Pindahkan fondasi UI + perbarui peta induk

Fondasi UI (warna/layout/pola sidebar) bukan fitur, tapi acuan lintas-fitur. Pindahkan ke `referensi/`.

**Files:**
- Rename: `docs/design-system/00-fondasi-desain.md` → `docs/referensi/pola-ui-desain.md`
- Modify: `docs/referensi/README.md`, `docs/README.md`
- Modify (rujukan): berkas mana pun yang menaut `design-system/00-fondasi-desain.md`

- [ ] **Step 1: Cari rujukan ke berkas 00**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
grep -rn --include="*.md" "00-fondasi-desain" docs/
```
Catat setiap hasil untuk diperbarui di Step 3.

- [ ] **Step 2: Pindahkan berkas (git mv)**

```bash
git mv docs/design-system/00-fondasi-desain.md docs/referensi/pola-ui-desain.md
```

- [ ] **Step 3: Perbarui rujukan**

Ganti setiap tautan `…/00-fondasi-desain.md` menjadi path baru ke `referensi/pola-ui-desain.md` (sesuaikan `../` relatif dari lokasi tiap berkas). Untuk berkas di `docs/design-system/` yang masih ada, tautannya menjadi `../referensi/pola-ui-desain.md`. Perbarui juga baris di `docs/referensi/README.md` (tambah baris `pola-ui-desain.md`) dan judul di dalam berkas yang dipindah bila menyebut "design-system".

- [ ] **Step 4: Perbarui `docs/README.md`**

Tambahkan baris `fitur/` (peta fitur — katalog status & dokumen per fitur) ke tabel struktur, di atas `design-system/`. Tandai `design-system/` sebagai "sedang dimigrasi ke `fitur/`".

- [ ] **Step 5: Verifikasi tautan**

Jalankan Verification Helper (cek tautan mati). Harapkan `SELESAI` tanpa baris `MATI`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "docs: pindahkan fondasi UI ke referensi/pola-ui-desain + tunjuk fitur/ di README

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Katalog status + kerangka semua fitur (Fase 1)

Buat peta "sudah/belum" utuh lebih dulu: katalog induk + berkas kerangka tiap fitur (baru §1 Ringkasan + header status). Domain diisi penuh di task berikutnya.

**Files:**
- Create: `docs/fitur/README.md` (katalog)
- Create: 18 berkas kerangka `docs/fitur/<nama>.md` (daftar di bawah)

**Daftar berkas fitur** (nama final):
`penjualan-tour.md`, `quotation.md`, `invoice.md`, `mice-template.md`, `customer.md`, `produk.md`, `supplier.md`, `rekening-bank.md`, `channel-manager-agent.md`, `booking.md`, `penugasan-lapangan.md`, `keuangan-ar-ap.md`, `keuangan-pembukuan.md`, `keuangan-aset-fiskal-pinjaman.md`, `email-brevo.md`, `reminder-followup.md`, `peran-akses.md`, `dashboard.md`

- [ ] **Step 1: Tentukan status tiap fitur dari sumber otoritatif**

Baca `docs/ikhtisar-proyek.md` (status M1–M7 + tambahan), `CHANGELOG.md`, dan `docs/rencana/README.md`. Tetapkan status tiap fitur: ✅ Berjalan / 🟡 Sedang dikerjakan / ⬜ Direncanakan. Hampir semua ✅; invoice punya sub-status refactor per-jenis 🟡 (Fase 0–2 di `dev`).

- [ ] **Step 2: Tulis `docs/fitur/README.md`**

Katalog: paragraf pembuka (untuk apa dokumen ini + cara baca + taksonomi status), lalu tabel dikelompokkan per domain dengan kolom **Fitur | Peran | Status | Dokumen**. Setiap baris menaut ke berkas fitur-nya. Taksonomi status persis seperti spec §6.

- [ ] **Step 3: Tulis 18 berkas kerangka**

Tiap berkas: header blockquote (Status · Peran · Sejak · Terkait) + `## 1. Ringkasan` (2–4 kalimat, dari pengetahuan domain yang sudah ada) + `## 5. Status & yang belum` (ringkas). Bagian §2, §3, §4, §6 ditulis sebagai heading kosong dengan penanda `> _Dilengkapi pada batch domain — lihat rencana._` supaya struktur konsisten sejak awal.

- [ ] **Step 4: Verifikasi**

Jalankan Verification Helper — semua tautan katalog harus valid (berkas kerangka sudah ada). Harapkan tanpa `MATI`.

- [ ] **Step 5: Commit**

```bash
git add docs/fitur/
git commit -m "docs: katalog status fitur + kerangka 18 berkas fitur (Fase 1)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Domain Penjualan (fitur inti)

Isi penuh 4 fitur penjualan, lalu hapus sumber `design-system/01` setelah kontennya bermigrasi.

**Files:**
- Fill: `docs/fitur/penjualan-tour.md`, `quotation.md`, `invoice.md`, `mice-template.md`
- Sumber baca: `docs/design-system/01-penjualan-tour.md`, `docs/logika-pembuatan-invoice/` (untuk invoice), kode: `app/Http/Controllers/{TourController,TourItemController,TourItineraryController,TourHistoryController,QuotationController,QuotationItemController,InvoiceController,InvoiceItemController,InvoicePaymentController,CostRequestController,MiceTemplateController}.php`, `resources/js/Pages/Tours/`
- Delete (akhir task): `docs/design-system/01-penjualan-tour.md`

- [ ] **Step 1: Baca sumber** — `design-system/01`, `logika-pembuatan-invoice/README.md` + `10-temuan.md`, dan controller yang relevan untuk tiap fitur.

- [ ] **Step 2: Tulis `penjualan-tour.md`** — §2 cara kerja (Tour builder, item produk snapshot, itinerary, tipe penjualan, kode WM, katalog paket M7, kepemilikan tour per sales), §3 keterkaitan (Quotation, Invoice, Booking, Keuangan, Assignment, Customer, Product), §4 batasan (snapshot harga; kepemilikan tour → tautan `rencana/tour-ownership.md`; generated column MySQL 8 → tautan ikhtisar), §5 status, §6 dokumen terkait.

- [ ] **Step 3: Tulis `quotation.md`** — §2 (PDF branded, kalkulator harga per-pax, ekspor Word, itinerary di PDF), §3 (Tour, Product, Customer), §4 (field sengaja dikosongkan dihormati di PDF), §5, §6.

- [ ] **Step 4: Tulis `invoice.md`** — §2 RINGKAS (invoice 2-tahap patokan→rincian→setujui, nomor gapless, multi-mata-uang, biaya tambahan) + tautan ke `../logika-pembuatan-invoice/README.md` sebagai deep-dive; §3 (Tour, Bill/biaya tambahan, InvoicePayment, Keuangan AR); §4 (**kunci setelah approve — jangan hitung ulang**; `total ≠ unit_price × pengali` karena biaya tambahan; refactor per-jenis 🟡 → tautan `desain/pemisahan-invoice-per-jenis.md`); §5; §6.

- [ ] **Step 5: Tulis `mice-template.md`** — §2 (template MICE dapat dipakai ulang), §3 (Tour/penjualan), §4, §5, §6. Bila konten terlalu tipis untuk berdiri sendiri, lebur ke `penjualan-tour.md` sebagai sub-bagian, hapus berkas `mice-template.md`, dan hapus barisnya dari katalog + perbarui tautan. Catat keputusan ini di commit.

- [ ] **Step 6: Hapus sumber & perbarui rujukan**

```bash
grep -rn --include="*.md" "01-penjualan-tour" docs/    # temukan rujukan
git rm docs/design-system/01-penjualan-tour.md
```
Perbarui setiap rujukan `01-penjualan-tour.md` → berkas fitur yang menggantikannya.

- [ ] **Step 7: Verifikasi** — jalankan cek 6-bagian untuk tiap berkas terisi + Verification Helper (tautan). Harapkan semua `OK` dan tanpa `MATI`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "docs: fitur domain Penjualan (tour, quotation, invoice, mice) + pensiunkan design-system/01

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Domain Keuangan

**Files:**
- Fill: `docs/fitur/keuangan-ar-ap.md`, `keuangan-pembukuan.md`, `keuangan-aset-fiskal-pinjaman.md`
- Sumber: `docs/design-system/{10-keuangan-ar-ap,11-keuangan-pembukuan,12-keuangan-aset-fiskal-pinjaman}.md`, kode: `app/Http/Controllers/{FinanceController,FinanceLedgerController,FinanceReportController,FiscalController,FixedAssetController,LoanController,BillController,BillPaymentController}.php`, `resources/js/Pages/Finance/`
- Delete: `docs/design-system/10-*.md`, `11-*.md`, `12-*.md`

- [ ] **Step 1: Baca** ketiga sumber design-system + controller Keuangan.
- [ ] **Step 2: Tulis `keuangan-ar-ap.md`** — §2 (AR/AP, profit riil, Budget vs Actual, pembayaran multi-kurs, akun kas), §3 (Invoice, Bill, Tour, Rekening), §4 (profit riil = total_sell − SUM(bills); tautan invarian ikhtisar), §5, §6.
- [ ] **Step 3: Tulis `keuangan-pembukuan.md`** — §2 (Jurnal, Buku Besar, Laba/Rugi, Neraca, Rekap, Saldo Akun, Cashflow, PDF), §3, §4, §5, §6.
- [ ] **Step 4: Tulis `keuangan-aset-fiskal-pinjaman.md`** — §2 (Aset Tetap, Koreksi Fiskal, Pinjaman/Hutang), §3, §4, §5, §6.
- [ ] **Step 5: Hapus sumber & perbarui rujukan** (pola Task 3 Step 6 untuk `10/11/12-*`).
- [ ] **Step 6: Verifikasi** (cek 6-bagian + Verification Helper).
- [ ] **Step 7: Commit** — `docs: fitur domain Keuangan + pensiunkan design-system/10-12` + footer.

---

## Task 5: Domain Master Data

**Files:**
- Fill: `docs/fitur/customer.md`, `produk.md`, `supplier.md`, `rekening-bank.md`
- Sumber: `docs/design-system/{05-customers,06-produk,07-suppliers,08-rekening}.md`, kode: `app/Http/Controllers/{CustomerController,ProductController,ProductPriceController,SupplierController,BankAccountController}.php`, `resources/js/Pages/{Customers,Products,Suppliers}/`
- Delete: `docs/design-system/05-*.md`, `06-*.md`, `07-*.md`, `08-*.md`

- [ ] **Step 1: Baca** keempat sumber + controller.
- [ ] **Step 2–5: Tulis tiap berkas** mengikuti template 6-bagian. Poin khusus: `customer.md` (tipe agent/corporate/direct + Buyer/travel-agent guest); `produk.md` (cost/sell sumber snapshot + harga berperiode); `supplier.md` (hotel/transport/guide/resto/attraction); `rekening-bank.md` (akun kas, batas hapus admin/akuntan). §4 tiap fitur: kaitan snapshot (mengubah harga produk tidak mengubah tour lama) → tautan ikhtisar.
- [ ] **Step 6: Hapus sumber & perbarui rujukan** (`05/06/07/08-*`).
- [ ] **Step 7: Verifikasi** + **Step 8: Commit** — `docs: fitur domain Master Data + pensiunkan design-system/05-08` + footer.

---

## Task 6: Domain Operasional

**Files:**
- Fill: `docs/fitur/booking.md`, `penugasan-lapangan.md`
- Sumber: `docs/design-system/{03-booking,13-my-jobs-manifest}.md`, kode: `app/Http/Controllers/{BookingController,AssignmentController,ManifestController,MyJobsController}.php`, `resources/js/Pages/{Bookings,MyJobs}/`, `resources/js/Pages/Manifest.vue`
- Delete: `docs/design-system/03-*.md`, `13-*.md`

- [ ] **Step 1: Baca** kedua sumber + controller.
- [ ] **Step 2: Tulis `booking.md`** — §2 (booking supplier, booking terkonfirmasi → hutang usaha/Bill), §3 (Supplier, Bill, Tour), §4, §5, §6.
- [ ] **Step 3: Tulis `penugasan-lapangan.md`** — §2 (Assignment guide/driver/tour_leader, link ke akun user, My Jobs, Manifest publik signed), §3 (Tour, User, Assignment), §4 (field hanya lihat jadwal sendiri tanpa cost/profit → tautan peran-akses), §5, §6.
- [ ] **Step 4: Hapus sumber & perbarui rujukan** (`03/13-*`).
- [ ] **Step 5: Verifikasi** + **Step 6: Commit** — `docs: fitur domain Operasional + pensiunkan design-system/03,13` + footer.

---

## Task 7: Domain Komunikasi

**Files:**
- Fill: `docs/fitur/email-brevo.md`, `reminder-followup.md`
- Sumber: `docs/desain/integrasi-brevo-email.md` (tautan, JANGAN dihapus — ini desain), `docs/design-system/04-reminder.md`, kode: `app/Http/Controllers/{TourEmailController,TourEmailStatusController,MarketingContactController,ReminderController}.php`, `resources/js/Pages/{Marketing,Reminders}/`
- Delete: `docs/design-system/04-*.md` (hanya ini; `desain/integrasi-brevo-email.md` tetap)

- [ ] **Step 1: Baca** sumber + controller.
- [ ] **Step 2: Tulis `email-brevo.md`** — §2 (Brevo SMTP pengiriman email quotation/invoice di latar belakang, status terkirim, kontak marketing, digest follow-up harian ke Sales), §3 (Tour, Customer, Reminder), §4 (Brevo = pipa pengiriman, bukan pengganti sistem reminder; bug laten `TourEmail $subject` yang sudah diperbaiki → tautan `desain/integrasi-brevo-email.md`), §5 (L1+L2 selesai lokal, L3 ditunda), §6.
- [ ] **Step 3: Tulis `reminder-followup.md`** — §2 (reminder follow-up otomatis H+1, berantai mengikuti status tour, berhenti di confirmed/cancelled), §3 (Tour, kepemilikan tour, Email), §4, §5, §6.
- [ ] **Step 4: Hapus `04-reminder.md` & perbarui rujukan.**
- [ ] **Step 5: Verifikasi** + **Step 6: Commit** — `docs: fitur domain Komunikasi + pensiunkan design-system/04` + footer.

---

## Task 8: Domain Channel & Agent

**Files:**
- Fill: `docs/fitur/channel-manager-agent.md`
- Sumber: `docs/design-system/02-channel-manager-produk-agent.md`, kode: `app/Http/Controllers/{ChannelManagerController,AgentProductController,AgentProductPriceController}.php`, `resources/js/Pages/{ChannelManager,AgentProducts}/`
- Delete: `docs/design-system/02-*.md`

- [ ] **Step 1: Baca** sumber + controller.
- [ ] **Step 2: Tulis `channel-manager-agent.md`** — §2 (Channel Manager, produk agent + harga agent, peran travel_agent "Produk Saya"), §3 (Product, Customer/agent, peran-akses), §4, §5, §6.
- [ ] **Step 3: Hapus `02-*.md` & perbarui rujukan.**
- [ ] **Step 4: Verifikasi** + **Step 5: Commit** — `docs: fitur Channel & Agent + pensiunkan design-system/02` + footer.

---

## Task 9: Domain Sistem & Akses

**Files:**
- Fill: `docs/fitur/peran-akses.md`, `dashboard.md`
- Sumber: `docs/design-system/09-kelola-akun.md`, `docs/referensi/roles-permissions.md` (tautan, JANGAN dihapus), kode: `app/Http/Controllers/{UserController,DashboardController}.php`, `app/Http/Middleware/EnsureUserHasRole.php`, `app/Models/User.php`, `resources/js/Pages/Users/`, `resources/js/Pages/Dashboard.vue`
- Delete: `docs/design-system/09-*.md` (hanya ini; `referensi/roles-permissions.md` tetap)

- [ ] **Step 1: Baca** sumber + kode.
- [ ] **Step 2: Tulis `peran-akses.md`** — §2 (CRUD user, 8 role, middleware `role:`, `homePath()`, sidebar per role), §3 (semua fitur bergantung pada role ini), §4 (menambah role wajib update enum + middleware + sidebar → tautan `referensi/roles-permissions.md` sebagai sumber kebenaran), §5, §6.
- [ ] **Step 3: Tulis `dashboard.md`** — §2 (pipeline stats, funnel, upcoming, Profit Riil + ringkasan keuangan, ter-scope per sales vs admin), §3 (Tour, kepemilikan tour, Keuangan), §4, §5, §6.
- [ ] **Step 4: Hapus `09-*.md` & perbarui rujukan.**
- [ ] **Step 5: Verifikasi** + **Step 6: Commit** — `docs: fitur Sistem & Akses + pensiunkan design-system/09` + footer.

---

## Task 10: Bersihkan & finalisasi

**Files:**
- Delete: folder `docs/design-system/` (harus tinggal `README.md`)
- Modify: `docs/README.md`, `docs/design-system/README.md` (dihapus), memori proyek

- [ ] **Step 1: Pastikan design-system kosong**

```bash
ls docs/design-system/
```
Harus hanya tersisa `README.md`. Bila ada berkas modul lain tersisa, berarti ada domain yang terlewat — kembali ke task domainnya.

- [ ] **Step 2: Hapus folder design-system**

```bash
git rm docs/design-system/README.md
rmdir docs/design-system 2>/dev/null || true
```

- [ ] **Step 3: Perbarui `docs/README.md`** — hapus baris `design-system/`, pastikan bagian `fitur/` menjelaskan perannya sebagai dokumentasi as-built per fitur. Perbarui juga penjelasan "design-system vs desain" yang kini tak relevan.

- [ ] **Step 4: Sapu rujukan `design-system` sisa**

```bash
grep -rn --include="*.md" "design-system" docs/ || echo "BERSIH — tak ada rujukan design-system tersisa"
```
Perbaiki bila ada.

- [ ] **Step 5: Verifikasi akhir** — jalankan Verification Helper (nol `MATI`) + cek 6-bagian pada beberapa berkas fitur sampel.

- [ ] **Step 6: Perbarui memori proyek**

Perbarui `~/.claude/projects/-Users-marchelinoraco-Documents-2026-welcomeManado-client-wm/memory/erp-invoice-per-jenis-project.md` bila menyebut path lama; tambah pointer ke `docs/fitur/` sebagai peta fitur. Perbarui `MEMORY.md` bila perlu.

- [ ] **Step 7: Commit** — `docs: hapus design-system (bermigrasi ke fitur/) + finalisasi peta dokumentasi` + footer.

---

## Catatan eksekusi

- Task 1–2 = Fase 1 (kerangka + katalog); menghasilkan peta status utuh. Aman berhenti di sini bila diperlukan.
- Task 3–9 = Fase 2 (isi per domain), bisa dicicil; tiap task berdiri sendiri & bisa di-review terpisah.
- Task 10 = penutup, hanya dijalankan setelah semua domain (Task 3–9) selesai.
- Bila suatu fitur ternyata terlalu tipis (mis. `mice-template`), keputusan lebur/tetap diambil saat task-nya, dicatat di commit dan katalog diperbarui.
