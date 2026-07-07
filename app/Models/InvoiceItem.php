<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    // line_cost & line_sell adalah stored generated columns — jangan diisi manual
    protected $guarded = ['id', 'line_cost', 'line_sell'];

    protected $casts = [
        'unit_cost'  => 'decimal:2',
        'unit_sell'  => 'decimal:2',
        'line_cost'  => 'decimal:2',
        'line_sell'  => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    /** Tipe produk yang butuh tanggal mulai & selesai layanan. */
    public const DATED_TYPES = ['hotel', 'transport', 'guide'];

    /**
     * Item invoice hanya untuk pantauan profit internal (cost vs sell, IDR) dan
     * TIDAK lagi menentukan total invoice — total berasal dari proforma
     * (unit_price × pax). Karena itu tak ada sinkronisasi total di sini.
     */
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
