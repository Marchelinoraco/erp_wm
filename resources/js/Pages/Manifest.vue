<script setup>
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    tour: Object,
})

const ROLE_LABELS = {
    guide:       'Tour Guide',
    driver:      'Driver',
    tour_leader: 'Tour Leader',
}

const TYPE_LABELS = {
    hotel:       '🏨 Hotel',
    transport:   '🚐 Transport',
    guide:       '👤 Guide',
    restaurant:  '🍽️ Restaurant',
    attraction:  '🎯 Wisata',
    other:       '📌 Lainnya',
}

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    })
}

function fmtDateShort(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
    })
}

// Group items by day_number
function itemsByDay(items) {
    const groups = {}
    items.forEach(item => {
        const key = item.day_number ?? 0
        if (!groups[key]) groups[key] = []
        groups[key].push(item)
    })
    return Object.entries(groups).sort(([a], [b]) => Number(a) - Number(b))
}
</script>

<template>
    <Head :title="`Manifest — ${tour.code}`" />

    <div class="min-h-screen bg-gray-50 font-sans">

        <!-- ── Header ── -->
        <div style="background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);" class="text-white">
            <div class="max-w-lg mx-auto px-4 py-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs tracking-widest uppercase opacity-70 mb-1">Welcome Manado</p>
                        <h1 class="text-xl font-bold">Tour Manifest</h1>
                        <p class="font-mono text-sm opacity-80 mt-0.5">{{ tour.code }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold"
                            style="background: rgba(232,196,122,0.25); color: #e8c47a; border: 1px solid rgba(232,196,122,0.4);">
                            {{ tour.status?.replace('_', ' ').toUpperCase() }}
                        </span>
                    </div>
                </div>

                <!-- Tour info pills -->
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="bg-white/10 px-3 py-1 rounded-full">
                        👤 {{ tour.pax }} Pax
                    </span>
                    <span v-if="tour.start_date" class="bg-white/10 px-3 py-1 rounded-full">
                        📅 {{ fmtDateShort(tour.start_date) }} – {{ fmtDateShort(tour.end_date) }}
                    </span>
                    <span v-if="tour.customer?.name" class="bg-white/10 px-3 py-1 rounded-full">
                        🌏 {{ tour.customer.name }}
                        <template v-if="tour.customer.country"> · {{ tour.customer.country }}</template>
                    </span>
                </div>
            </div>
        </div>

        <div class="max-w-lg mx-auto px-4 py-5 space-y-5">

            <!-- ── Judul Tour ── -->
            <div v-if="tour.title" class="bg-white rounded-xl border p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Paket Tour</p>
                <p class="text-lg font-bold text-gray-800">{{ tour.title }}</p>
            </div>

            <!-- ── Assignments (guide & driver) ── -->
            <div v-if="tour.assignments?.length" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tim Lapangan</p>
                </div>
                <div class="divide-y">
                    <div v-for="a in tour.assignments" :key="a.id" class="px-4 py-4">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0"
                                :style="a.role === 'guide' ? 'background:#dbeafe' : a.role === 'driver' ? 'background:#dcfce7' : 'background:#fef3c7'">
                                {{ a.role === 'guide' ? '👤' : a.role === 'driver' ? '🚗' : '⭐' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900">{{ a.person_name ?? 'TBD' }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="a.role === 'guide' ? 'bg-blue-100 text-blue-700' : a.role === 'driver' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                                        {{ ROLE_LABELS[a.role] ?? a.role }}
                                    </span>
                                </div>
                                <div class="mt-1.5 space-y-0.5 text-sm text-gray-600">
                                    <p v-if="a.phone">
                                        <a :href="`https://wa.me/${a.phone.replace(/[^0-9]/g, '')}`"
                                            class="text-green-600 font-medium inline-flex items-center gap-1">
                                            <span>📱</span> {{ a.phone }}
                                        </a>
                                    </p>
                                    <p v-if="a.vehicle">🚐 {{ a.vehicle }}</p>
                                    <p v-if="a.pickup_time">🕐 Pickup: <strong>{{ a.pickup_time }}</strong></p>
                                    <p v-if="a.notes" class="text-gray-500 text-xs mt-1">{{ a.notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Program / Itinerary ── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Program Tour</p>
                </div>

                <div v-if="!tour.items?.length" class="px-4 py-6 text-center text-gray-400 text-sm">
                    Program belum diisi.
                </div>

                <div v-else>
                    <template v-for="[day, items] in itemsByDay(tour.items)" :key="day">
                        <!-- Day header -->
                        <div class="px-4 py-2 bg-blue-50 border-y border-blue-100">
                            <p class="text-xs font-bold text-blue-700 uppercase tracking-wider">
                                {{ Number(day) > 0 ? `Hari ke-${day}` : 'Program' }}
                            </p>
                        </div>
                        <!-- Items -->
                        <div v-for="item in items" :key="item.id"
                            class="px-4 py-3 border-b last:border-0 flex items-start gap-3">
                            <span class="text-base mt-0.5 shrink-0">
                                {{ item.product_type === 'hotel' ? '🏨'
                                 : item.product_type === 'transport' ? '🚐'
                                 : item.product_type === 'guide' ? '👤'
                                 : item.product_type === 'restaurant' ? '🍽️'
                                 : item.product_type === 'attraction' ? '🎯'
                                 : '📌' }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 text-sm">{{ item.description }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ item.qty }} unit
                                    <template v-if="item.nights > 1"> · {{ item.nights }} malam</template>
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ── Customer Info ── -->
            <div v-if="tour.customer" class="bg-white rounded-xl border shadow-sm p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Informasi Tamu</p>
                <div class="space-y-1.5 text-sm">
                    <div class="flex gap-2">
                        <span class="text-gray-400 w-20 shrink-0">Nama</span>
                        <span class="font-medium text-gray-900">{{ tour.customer.name }}</span>
                    </div>
                    <div v-if="tour.customer.country" class="flex gap-2">
                        <span class="text-gray-400 w-20 shrink-0">Negara</span>
                        <span class="text-gray-700">{{ tour.customer.country }}</span>
                    </div>
                    <div v-if="tour.customer.contact_person" class="flex gap-2">
                        <span class="text-gray-400 w-20 shrink-0">Kontak</span>
                        <span class="text-gray-700">{{ tour.customer.contact_person }}</span>
                    </div>
                    <div v-if="tour.customer.phone" class="flex gap-2">
                        <span class="text-gray-400 w-20 shrink-0">WA/Telp</span>
                        <a :href="`https://wa.me/${tour.customer.phone.replace(/[^0-9]/g,'')}`"
                            class="text-green-600 font-medium">
                            {{ tour.customer.phone }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- ── Notes ── -->
            <div v-if="tour.notes" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 mb-1">Catatan</p>
                <p class="text-sm text-amber-900">{{ tour.notes }}</p>
            </div>

            <!-- ── Footer ── -->
            <div class="text-center text-xs text-gray-400 pb-6">
                <p class="font-semibold text-gray-500">Welcome Manado Tour & Travel</p>
                <p>welcomemanado.com</p>
                <p class="mt-1">Manifest ini hanya untuk penggunaan internal.</p>
            </div>

        </div>
    </div>
</template>
