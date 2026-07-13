<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date', 'due_date' => 'date'];

    /**
     * Tipe produk item → kategori Bill. Enum kategori Bill hanya hotel/transport/
     * guide/restaurant/attraction/other, jadi tipe di luar itu (venue, equipment) jatuh ke "other".
     */
    private const PRODUCT_TYPE_TO_CATEGORY = [
        'hotel' => 'hotel', 'transport' => 'transport', 'guide' => 'guide',
        'restaurant' => 'restaurant', 'attraction' => 'attraction',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Item Rincian Profit asal bill ini, bila dibuat otomatis saat invoice disetujui. */
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function getPaidAttribute(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->amount - $this->paid;
    }

    /**
     * Catat item Rincian Profit yang produknya punya supplier sebagai Bill draft
     * (nominal 0, diisi manual oleh akuntan). Idempotent — aman dipanggil ulang,
     * baik saat invoice disetujui maupun lewat backfill untuk invoice lama.
     */
    public static function createMissingFromInvoice(Invoice $invoice): int
    {
        $invoice->loadMissing('items.product.supplier');
        $created = 0;

        foreach ($invoice->items as $item) {
            $supplierId = $item->product?->supplier_id;
            if (! $supplierId) {
                continue;
            }

            $bill = static::firstOrCreate(
                ['invoice_item_id' => $item->id],
                [
                    'tour_id'     => $invoice->tour_id,
                    'supplier_id' => $supplierId,
                    'description' => $item->description,
                    'category'    => self::PRODUCT_TYPE_TO_CATEGORY[$item->product_type] ?? 'other',
                    'date'        => $invoice->date,
                    'amount'      => 0,
                    'status'      => 'unpaid',
                ]
            );

            if ($bill->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
