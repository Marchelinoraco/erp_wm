// Definisi jenis inquiry/order. Sumber kebenaran tunggal untuk frontend.
// Backend padanannya: App\Models\Tour::TYPES (label) + kolom tours.type & tours.details.

export const INQUIRY_TYPES = {
    tour:      { label: 'Tour',              build: 'Tour Baru',                noun: 'tour' },
    rental:    { label: 'Rental Mobil/Boat', build: 'Rental Baru',              noun: 'rental' },
    guide:     { label: 'Jasa Guide',        build: 'Jasa Guide Baru',          noun: 'jasa guide' },
    document:  { label: 'Visa/Paspor',       build: 'Pengurusan Dokumen Baru',  noun: 'dokumen' },
    ticketing: { label: 'Ticketing',         build: 'Ticketing Baru',           noun: 'tiket' },
    mice:      { label: 'MICE / Event',      build: 'Event Baru',               noun: 'event' },
    hotel:     { label: 'Hotel',             build: 'Reservasi Hotel Baru',     noun: 'hotel' },
}

// Warna badge per tipe (Tailwind).
export const TYPE_BADGE = {
    tour:      'bg-blue-100 text-blue-700',
    rental:    'bg-amber-100 text-amber-700',
    guide:     'bg-emerald-100 text-emerald-700',
    document:  'bg-violet-100 text-violet-700',
    ticketing: 'bg-rose-100 text-rose-700',
    mice:      'bg-pink-100 text-pink-700',
    hotel:     'bg-cyan-100 text-cyan-700',
}

// Field khusus per tipe → dirender ke tours.details (JSON).
// type: 'text' | 'date' | 'checkbox' | 'textarea'
export const TYPE_FIELDS = {
    tour: [],
    rental: [
        { key: 'vehicle',     label: 'Kendaraan / Boat',      type: 'text',     placeholder: 'mis. Innova Reborn / Speedboat 15 seat' },
        { key: 'with_driver', label: 'Termasuk Sopir/Nahkoda', type: 'checkbox' },
        { key: 'pickup',      label: 'Titik Jemput',          type: 'text',     placeholder: 'mis. Bandara Sam Ratulangi' },
        { key: 'dropoff',     label: 'Titik Antar',           type: 'text' },
        { key: 'duration',    label: 'Durasi Sewa',           type: 'text',     placeholder: 'mis. 3 hari / 8 jam' },
    ],
    guide: [
        { key: 'language', label: 'Bahasa',       type: 'text',     placeholder: 'mis. Indonesia, Inggris, Mandarin' },
        { key: 'area',     label: 'Area / Lokasi', type: 'text',    placeholder: 'mis. Manado & Minahasa' },
        { key: 'days',     label: 'Jumlah Hari',  type: 'text' },
        { key: 'note',     label: 'Catatan Tambahan', type: 'textarea' },
    ],
    document: [
        { key: 'doc_type',    label: 'Jenis Dokumen',  type: 'text',     placeholder: 'mis. Visa Turis Jepang / Paspor Baru' },
        { key: 'destination', label: 'Negara Tujuan',  type: 'text' },
        { key: 'eta',         label: 'Estimasi Selesai', type: 'text',   placeholder: 'mis. 7-14 hari kerja' },
        { key: 'requirement', label: 'Syarat / Berkas', type: 'textarea' },
    ],
    ticketing: [
        { key: 'route_from',   label: 'Dari',             type: 'text', placeholder: 'mis. Manado (MDC)' },
        { key: 'route_to',     label: 'Ke',               type: 'text', placeholder: 'mis. Jakarta (CGK)' },
        { key: 'airline',      label: 'Maskapai',         type: 'text' },
        { key: 'trip_type',    label: 'Sekali Jalan / PP', type: 'text', placeholder: 'mis. PP' },
        { key: 'depart_date',  label: 'Tgl Berangkat',    type: 'date' },
        { key: 'return_date',  label: 'Tgl Pulang',       type: 'date' },
        { key: 'pnr',          label: 'Kode Booking / PNR', type: 'text' },
    ],
    mice: [
        { key: 'event_type', label: 'Jenis Acara',    type: 'text', placeholder: 'mis. Corporate Gathering / Seminar / Meeting' },
        { key: 'room_setup', label: 'Setup Ruangan',  type: 'text', placeholder: 'mis. Theater / Classroom / Round Table / U-Shape' },
        { key: 'venue_name', label: 'Nama Venue',     type: 'text', placeholder: 'mis. Ballroom Hotel Aryaduta Manado' },
        { key: 'duration',   label: 'Durasi Acara',   type: 'text', placeholder: 'mis. 1 hari / Half-day / 2 hari' },
        { key: 'note',       label: 'Catatan Tambahan', type: 'textarea' },
    ],
    hotel: [
        { key: 'hotel_name',  label: 'Nama Hotel',     type: 'text', placeholder: 'mis. Hotel Aryaduta Manado' },
        { key: 'room_type',   label: 'Tipe Kamar',     type: 'text', placeholder: 'mis. Deluxe / Suite' },
        { key: 'room_count',  label: 'Jumlah Kamar',   type: 'text', placeholder: 'mis. 5 kamar' },
        { key: 'check_in',    label: 'Tgl Check-in',   type: 'date' },
        { key: 'check_out',   label: 'Tgl Check-out',  type: 'date' },
        { key: 'guest_count', label: 'Jumlah Tamu',    type: 'text' },
        { key: 'note',        label: 'Catatan Tambahan', type: 'textarea' },
    ],
}

export function typeLabel(type) {
    return INQUIRY_TYPES[type]?.label ?? INQUIRY_TYPES.tour.label
}

export function emptyDetails(type) {
    const out = {}
    for (const f of (TYPE_FIELDS[type] ?? [])) {
        out[f.key] = f.type === 'checkbox' ? false : ''
    }
    return out
}
