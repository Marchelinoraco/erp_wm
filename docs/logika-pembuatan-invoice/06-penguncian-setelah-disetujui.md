# 06 — Penguncian Setelah Disetujui

[← 05 Persetujuan](05-tahap-3-persetujuan.md) · [Peta](README.md) · Berikutnya: [07 — Pembayaran](07-pembayaran.md)

---

## 6.1 Pemicu penguncian

Satu-satunya penanda adalah `approved_at`:

```php
public function getIsApprovedAttribute(): bool
{
    return ! is_null($this->approved_at);
}
```

Satu penjaga memakainya:

| Penjaga | Berkas | Pesan |
|---|---|---|
| `ensureNotApproved()` | `InvoiceController` | "Invoice sudah disetujui, tidak bisa diubah lagi." |

`InvoiceItemController` (item Rincian Profit) **tidak lagi** punya penjaga serupa. Sejak keputusan D1 (lihat [spec Rincian Profit Tetap Terbuka](../superpowers/specs/2026-08-05-rincian-profit-tetap-terbuka-design.md)), `ensureEditable()` dihapus seluruhnya — item bisa ditambah/diubah/dihapus di status invoice apa pun, oleh sales **dan** akuntan/admin (D2). Lihat [04-rincian-profit.md §4.3](04-rincian-profit.md) dan §6.5 di bawah.

## 6.2 Matriks izin

| Aksi | Endpoint | Terkunci? | Penjaga |
|---|---|:---:|---|
| Ubah proforma | `PATCH /invoices/{id}/proforma` | 🔒 | `ensureNotApproved` |
| Kunci patokan | `PATCH /invoices/{id}/baseline` | 🔒 | `ensureNotApproved` |
| Ubah jatuh tempo (sales) | `PATCH /invoices/{id}/due-date` | 🔒 | `ensureNotApproved` |
| Setujui | `POST /invoices/{id}/approve` | 🔒 | `ensureNotApproved` |
| Hapus invoice | `DELETE /invoices/{id}` | 🔒 | cek `is_approved` terpisah |
| Tambah item | `POST /invoices/{id}/items` | ✅ terbuka selamanya | — (sales + akuntan/admin; dicatat ke riwayat tour bila pasca-approve) |
| Tempel massal item | `POST /invoices/{id}/items/bulk` | ✅ terbuka selamanya | — (sales + akuntan/admin; dicatat ke riwayat tour bila pasca-approve) |
| Autosave item | `PATCH /invoices/{id}/items/bulk` | ✅ terbuka selamanya | — (sales + akuntan/admin; dicatat ke riwayat tour bila pasca-approve) |
| Ubah satu item | `PATCH /invoice-items/{id}` | ✅ terbuka selamanya | — (sales + akuntan/admin; dicatat ke riwayat tour bila pasca-approve) |
| Hapus item | `DELETE /invoice-items/{id}` | ⚠️ terbuka, kecuali sudah ber-Bill | ditolak hanya bila item punya `Bill` terkait (D3) |
| **Update oleh akuntan** | `PATCH /finance/invoices/{id}` | ✅ terbuka | — |
| **Catat pembayaran** | `POST /invoices/{id}/deposits` | ⚠️ terbuka | tidak ada penjaga |
| **Hapus pembayaran** | `DELETE /invoice-deposits/{id}` | ⚠️ terbuka | tidak ada penjaga |
| Unduh PDF invoice | `GET /invoices/{id}/download` | ✅ terbuka | — |
| PDF Rincian Profit | `GET /invoices/{id}/profit-pdf` | ✅ **hanya** setelah disetujui | `abort_unless(approved_at, 403)` |

Baris bertanda ⚠️ tanpa keterangan D3 dibahas di [10-temuan.md §10.2](10-temuan.md).

## 6.3 Penghapusan invoice

```php
public function destroy(Invoice $invoice)
{
    if ($invoice->is_approved) {
        throw ValidationException::withMessages([
            'invoice' => 'Invoice sudah disetujui dan masuk Keuangan, tidak bisa dihapus.',
        ]);
    }

    $invoice->delete();
}
```

| # | Kondisi | Perilaku |
|---|---|---|
| K-80 | Invoice sudah disetujui | ditolak |
| K-81 | Belum disetujui | dihapus **soft delete** (`Invoice` memakai `SoftDeletes`) |
| K-82 | Setelah dihapus | tour boleh dibuatkan invoice baru — `exists()` mengabaikan baris ter-soft-delete |
| K-83 | Nomor invoice lama | tetap terpakai selamanya; `nextNumber()` memakai `withTrashed()` |

Jadi menghapus invoice draft dan membuat ulang akan menghasilkan **nomor berikutnya**, bukan nomor yang sama.

## 6.4 Yang boleh diubah akuntan

Endpoint: `PATCH /finance/invoices/{invoice}` → `InvoiceController::update()`

| Field | Aturan |
|---|---|
| `date` | `required\|date` |
| `due_date` | `nullable\|date` |
| `status` | `required\|in:sent,partial,paid` |
| `notes` | `nullable\|string` |

Method ini **tidak** memanggil `ensureNotApproved()` — memang dirancang untuk invoice yang sudah disetujui. Yang membatasinya adalah penempatan route di grup middleware keuangan, bukan status invoice.

Perhatikan: akuntan tidak dapat mengubah nominal, mata uang, kurs, maupun item. Hanya tanggal, status, dan catatan.

## 6.5 Perubahan setelah disetujui

Yang **tidak berubah**: tidak ada mekanisme revisi untuk invoice itu sendiri. Bila nominal tagihan (`unit_price × pax`) ternyata salah setelah invoice masuk Keuangan:

- Sales tidak bisa mengubah proforma/patokan
- Sales tidak bisa menghapus invoice
- Sales tidak bisa membuat invoice pengganti (satu tour satu invoice)
- Akuntan hanya bisa menyesuaikan tanggal/status/catatan lewat `PATCH /finance/invoices/{id}`

Satu-satunya jalan untuk memperbaiki tagihan customer adalah intervensi langsung di database. Ini konsekuensi desain yang disengaja — dan alasan mengapa persetujuan non-IDR diberi dua lapis konfirmasi.

Yang **berubah** sejak fitur Rincian Profit Tetap Terbuka: item Rincian Profit (cost/sell internal, tidak memengaruhi tagihan customer) **bukan** bagian dari invoice yang terkunci di atas. Sales **dan** akuntan/admin bisa menambah, mengubah, atau menghapus item kapan pun — sebelum maupun sesudah invoice disetujui. Satu-satunya batasan: item yang sudah punya `Bill` terkait tidak bisa dihapus (harus hapus Bill-nya dulu), meski tetap bisa diedit. Setiap tambah/ubah/hapus item **setelah** invoice disetujui dicatat satu baris di riwayat tour (lihat [04-rincian-profit.md](04-rincian-profit.md)); edit sebelum approve tidak dicatat.
