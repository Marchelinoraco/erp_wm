<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tour extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'price_validity' => 'date',
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

    /** Label field `details` per tipe — dipakai untuk render di PDF quotation.
     *  Selaras dengan resources/js/lib/inquiryTypes.js (TYPE_FIELDS). */
    public const DETAIL_LABELS = [
        'rental' => [
            'vehicle'     => 'Kendaraan / Boat',
            'with_driver' => 'Termasuk Sopir/Nahkoda',
            'pickup'      => 'Titik Jemput',
            'dropoff'     => 'Titik Antar',
            'duration'    => 'Durasi Sewa',
        ],
        'guide' => [
            'language' => 'Bahasa',
            'area'     => 'Area / Lokasi',
            'days'     => 'Jumlah Hari',
            'note'     => 'Catatan Tambahan',
        ],
        'document' => [
            'doc_type'    => 'Jenis Dokumen',
            'destination' => 'Negara Tujuan',
            'eta'         => 'Estimasi Selesai',
            'requirement' => 'Syarat / Berkas',
        ],
        'ticketing' => [
            'route_from'  => 'Dari',
            'route_to'    => 'Ke',
            'airline'     => 'Maskapai',
            'trip_type'   => 'Sekali Jalan / PP',
            'depart_date' => 'Tgl Berangkat',
            'return_date' => 'Tgl Pulang',
            'pnr'         => 'Kode Booking / PNR',
        ],
        'mice' => [
            'event_type'  => 'Jenis Acara',
            'room_setup'  => 'Setup Ruangan',
            'venue_name'  => 'Nama Venue',
            'duration'    => 'Durasi Acara',
            'note'        => 'Catatan Tambahan',
        ],
        'hotel' => [
            'hotel_name'  => 'Nama Hotel',
            'room_type'   => 'Tipe Kamar',
            'room_count'  => 'Jumlah Kamar',
            'check_in'    => 'Tgl Check-in',
            'check_out'   => 'Tgl Check-out',
            'guest_count' => 'Jumlah Tamu',
            'note'        => 'Catatan Tambahan',
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

    public function getTotalCostAttribute(): float
    {
        return (float) $this->items->sum('line_cost');
    }

    public function getTotalSellAttribute(): float
    {
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
        // Hanya invoice yang sudah disetujui sales yang dihitung di Keuangan.
        return (float) $this->invoices
            ->whereNotNull('approved_at')
            ->flatMap->payments->sum('amount');
    }

    public function getReceivableAttribute(): float
    {
        return (float) $this->invoices->whereNotNull('approved_at')->sum('total') - $this->received;
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
