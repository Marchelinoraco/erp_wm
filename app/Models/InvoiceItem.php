<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    // line_cost & line_sell adalah stored generated columns — jangan diisi manual
    protected $guarded = ['id', 'line_cost', 'line_sell'];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_sell' => 'decimal:2',
        'line_cost' => 'decimal:2',
        'line_sell' => 'decimal:2',
    ];

    /**
     * Setiap perubahan baris → samakan total invoice induk dengan jumlah jual terkini.
     * (Berbeda dari tour_items: invoice TIDAK menyentuh costing tour.)
     */
    protected static function booted(): void
    {
        static::saved(fn (self $item) => $item->invoice?->recalcTotal());
        static::deleted(fn (self $item) => $item->invoice?->recalcTotal());
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function fromProduct(Product $p, array $extra = []): self
    {
        return new self(array_merge([
            'product_id'   => $p->id,
            'product_type' => $p->type,
            'description'  => $p->name,
            'unit_cost'    => $p->cost,
            'unit_sell'    => $p->sell,
            'currency'     => $p->currency,
            'qty'          => 1,
            'nights'       => 1,
        ], $extra));
    }
}
