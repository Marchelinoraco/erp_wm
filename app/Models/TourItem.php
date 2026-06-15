<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourItem extends Model
{
    // line_cost & line_sell adalah stored generated columns — jangan diisi manual
    protected $guarded = ['id', 'line_cost', 'line_sell'];

    /**
     * Setiap perubahan item → samakan total invoice draft tour terkait.
     * Membuat invoice 'draft' selalu sinkron dengan jumlah item, dari sumber mana pun.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $item) => $item->tour?->syncDraftInvoiceTotals());
        static::deleted(fn (self $item) => $item->tour?->syncDraftInvoiceTotals());
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
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
