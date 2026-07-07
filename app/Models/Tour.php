<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tour extends Model
{
    protected $guarded = [];

    protected $casts = [
        // date:Y-m-d — agar terkirim ke frontend sebagai "2026-07-13" (bukan ISO
        // panjang berjam) sehingga <input type="date"> bisa menampilkannya
        'start_date'     => 'date:Y-m-d',
        'end_date'       => 'date:Y-m-d',
        'price_validity' => 'date:Y-m-d',
        'pricing'        => 'array',
        'details'        => 'array',
        'budget'         => 'decimal:2',
    ];

    /** Jenis inquiry/order yang didukung → label tampilan. */
    public const TYPES = [
        'tour'      => 'Tour',
        'rental'    => 'Rental Mobil/Boat',
        'guide'     => 'Jasa Guide',
        'document'  => 'Visa/Paspor',
        'ticketing' => 'Ticketing',
        'mice'      => 'MICE / Event',
        'hotel'     => 'Hotel',
    ];

    /** Kode angka per tipe untuk penomoran kode tour: WM-<tahun>-<kode>-<urut>.
     *  Tipe `tour` khusus: inbound => 11, outbound => 12 (lihat resolveTypeCode). */
    public const TYPE_CODES = [
        'tour'      => '11',
        'rental'    => '13',
        'guide'     => '14',
        'mice'      => '15',
        'hotel'     => '16',
        'document'  => '17',
        'ticketing' => '18',
    ];

    /** Label tipe versi Inggris — dipakai di PDF quotation (customer-facing). */
    public const TYPES_EN = [
        'tour'      => 'Tour',
        'rental'    => 'Car/Boat Rental',
        'guide'     => 'Tour Guide',
        'document'  => 'Visa/Passport',
        'ticketing' => 'Ticketing',
        'mice'      => 'MICE / Event',
        'hotel'     => 'Hotel',
    ];

    /** Label field `details` per tipe — dipakai untuk render di PDF quotation
     *  (bahasa Inggris karena customer-facing).
     *  Selaras dengan resources/js/lib/inquiryTypes.js (TYPE_FIELDS). */
    public const DETAIL_LABELS = [
        'rental' => [
            'vehicle'     => 'Vehicle / Boat',
            'with_driver' => 'Driver/Skipper Included',
            'pickup'      => 'Pick-up Point',
            'dropoff'     => 'Drop-off Point',
            'duration'    => 'Rental Duration',
        ],
        'guide' => [
            'language' => 'Language',
            'area'     => 'Area / Location',
            'days'     => 'Number of Days',
            'note'     => 'Additional Notes',
        ],
        'document' => [
            'doc_type'    => 'Document Type',
            'destination' => 'Destination Country',
            'eta'         => 'Estimated Completion',
            'requirement' => 'Requirements / Documents',
        ],
        'ticketing' => [
            'route_from'  => 'From',
            'route_to'    => 'To',
            'airline'     => 'Airline',
            'trip_type'   => 'One Way / Round Trip',
            'depart_date' => 'Departure Date',
            'return_date' => 'Return Date',
            'pnr'         => 'Booking Code / PNR',
        ],
        'mice' => [
            'event_type'  => 'Event Type',
            'room_setup'  => 'Room Setup',
            'venue_name'  => 'Venue Name',
            'duration'    => 'Event Duration',
            'note'        => 'Additional Notes',
        ],
        'hotel' => [
            'hotel_name'  => 'Hotel Name',
            'room_type'   => 'Room Type',
            'room_count'  => 'Number of Rooms',
            'check_in'    => 'Check-in Date',
            'check_out'   => 'Check-out Date',
            'guest_count' => 'Number of Guests',
            'note'        => 'Additional Notes',
        ],
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? 'Tour';
    }

    /** Kode angka tipe untuk penomoran kode tour. Tour dibedakan per arah. */
    public function resolveTypeCode(): string
    {
        if ($this->type === 'tour') {
            return $this->tour_direction === 'outbound' ? '12' : '11';
        }

        return self::TYPE_CODES[$this->type] ?? '11';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    public function items()
    {
        return $this->hasMany(TourItem::class)->orderBy('sort_order');
    }

    public function quotationItems()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function itineraryDays()
    {
        return $this->hasMany(TourItineraryDay::class)->orderBy('day_number');
    }

    public function itineraryHours()
    {
        return $this->hasMany(TourItineraryHour::class)->orderBy('day_number')->orderBy('start_time');
    }

    public function histories()
    {
        return $this->hasMany(TourHistory::class)->latest();
    }

    public function getItineraryPdfUrlAttribute(): ?string
    {
        return $this->itinerary_pdf
            ? Storage::disk('public')->url($this->itinerary_pdf)
            : null;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** Jadwal layanan berjadwal (hotel/transport/guide) dari item invoice untuk
     *  tim lapangan (MyJobs & manifest publik) — hanya field aman, TANPA harga. */
    public function fieldSchedule()
    {
        return $this->invoices()->with('items')->get()
            ->flatMap(fn ($inv) => $inv->items)
            ->filter(fn ($i) => in_array($i->product_type, InvoiceItem::DATED_TYPES, true) && $i->start_date)
            ->unique(fn ($i) => $i->product_type . '|' . $i->description . '|' . $i->start_date . '|' . $i->end_date)
            ->sortBy('start_date')
            ->map(fn ($i) => [
                'id'           => $i->id,
                'product_type' => $i->product_type,
                'description'  => $i->description,
                'qty'          => $i->qty,
                'nights'       => $i->nights,
                'start_date'   => $i->start_date?->format('Y-m-d'),
                'end_date'     => $i->end_date?->format('Y-m-d'),
            ])
            ->values();
    }

    /** Hanya invoice yang sudah disetujui sales — yang dipakai di Keuangan. */
    public function approvedInvoices()
    {
        return $this->invoices()->whereNotNull('approved_at');
    }

    public function bookings()
    {
        return $this->hasMany(TourBooking::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    // --- PERKIRAAN (snapshot tour_items) ---
    // Khusus tipe "tour" (inbound/outbound) yang invoicenya sudah disetujui:
    // sell = tagihan customer (total_idr invoice), cost = item invoice (rincian
    // profit) — sesuai rumus Profit = tagihan − cost, bukan sell/unit per item.

    /** Tipe tour dengan invoice approved → profit berbasis tagihan invoice. */
    private function usesInvoiceProfit(): bool
    {
        return $this->type === 'tour'
            && $this->invoices->whereNotNull('approved_at')->isNotEmpty();
    }

    public function getTotalCostAttribute(): float
    {
        if ($this->usesInvoiceProfit()) {
            return (float) $this->invoices->flatMap->items->sum('line_cost');
        }

        return (float) $this->items->sum('line_cost');
    }

    public function getTotalSellAttribute(): float
    {
        if ($this->usesInvoiceProfit()) {
            return (float) $this->invoices->whereNotNull('approved_at')->sum('total_idr');
        }

        return (float) $this->items->sum('line_sell');
    }

    public function getProfitAttribute(): float
    {
        return $this->total_sell - $this->total_cost;
    }

    public function getMarginAttribute(): float
    {
        return $this->total_sell > 0
            ? round($this->profit / $this->total_sell * 100, 1)
            : 0;
    }

    // --- AKTUAL (M6 — dari tabel bills & invoices) ---

    public function getActualCostAttribute(): float
    {
        return (float) $this->bills->sum('amount');
    }

    public function getActualProfitAttribute(): float
    {
        return $this->total_sell - $this->actual_cost;
    }

    public function getCostVarianceAttribute(): float
    {
        return $this->actual_cost - $this->total_cost; // + = boros
    }

    public function getReceivedAttribute(): float
    {
        // Hanya invoice yang sudah disetujui sales yang dihitung di Keuangan (IDR).
        return (float) $this->invoices
            ->whereNotNull('approved_at')
            ->flatMap->payments->sum('amount_idr');
    }

    public function getReceivableAttribute(): float
    {
        return (float) $this->invoices->whereNotNull('approved_at')->sum('total_idr') - $this->received;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tour) {
            if (empty($tour->code)) {
                $year   = now()->year;
                $prefix = 'WM-' . $year . '-' . $tour->resolveTypeCode() . '-';
                $latest = static::where('code', 'like', $prefix . '%')
                    ->orderByDesc('code')
                    ->value('code');
                $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;
                $tour->code = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
