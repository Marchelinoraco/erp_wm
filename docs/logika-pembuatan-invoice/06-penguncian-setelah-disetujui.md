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

Dua penjaga memakainya:

| Penjaga | Berkas | Pesan |
|---|---|---|
| `ensureNotApproved()` | `InvoiceController` | "Invoice sudah disetujui, tidak bisa diubah lagi." |
| `ensureEditable()` | `InvoiceItemController` | "Invoice sudah disetujui dan masuk Keuangan, tidak bisa diubah. Buat invoice tambahan bila ada perubahan." |

## 6.2 Matriks izin

| Aksi | Endpoint | Terkunci? | Penjaga |
|---|---|:---:|---|
| Ubah proforma | `PATCH /invoices/{id}/proforma` | 🔒 | `ensureNotApproved` |
| Kunci patokan | `PATCH /invoices/{id}/baseline` | 🔒 | `ensureNotApproved` |
| Ubah jatuh tempo (sales) | `PATCH /invoices/{id}/due-date` | 🔒 | `ensureNotApproved` |
| Setujui | `POST /invoices/{id}/approve` | 🔒 | `ensureNotApproved` |
| Hapus invoice | `DELETE /invoices/{id}` | 🔒 | cek `is_approved` terpisah |
| Tambah item | `POST /invoices/{id}/items` | 🔒 | `ensureEditable` |
| Tempel massal item | `POST /invoices/{id}/items/bulk` | 🔒 | `ensureEditable` |
| Autosave item | `PATCH /invoices/{id}/items/bulk` | 🔒 | `ensureEditable` |
| Ubah satu item | `PATCH /invoice-items/{id}` | 🔒 | `ensureEditable` |
| Hapus item | `DELETE /invoice-items/{id}` | 🔒 | `ensureEditable` |
| **Update oleh akuntan** | `PATCH /finance/invoices/{id}` | ✅ terbuka | — |
| **Catat pembayaran** | `POST /invoices/{id}/deposits` | ⚠️ terbuka | tidak ada penjaga |
| **Hapus pembayaran** | `DELETE /invoice-deposits/{id}` | ⚠️ terbuka | tidak ada penjaga |
| Unduh PDF invoice | `GET /invoices/{id}/download` | ✅ terbuka | — |
| PDF Rincian Profit | `GET /invoices/{id}/profit-pdf` | ✅ **hanya** setelah disetujui | `abort_unless(approved_at, 403)` |

Baris bertanda ⚠️ dibahas di [10-temuan.md §10.2](10-temuan.md).

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

Tidak ada mekanisme revisi. Bila nominal ternyata salah setelah invoice masuk Keuangan:

- Sales tidak bisa mengubahnya
- Sales tidak bisa menghapusnya
- Sales tidak bisa membuat invoice pengganti (satu tour satu invoice)
- Akuntan hanya bisa menyesuaikan tanggal/status/catatan

Satu-satunya jalan adalah intervensi langsung di database. Ini konsekuensi desain yang disengaja — dan alasan mengapa persetujuan non-IDR diberi dua lapis konfirmasi.
