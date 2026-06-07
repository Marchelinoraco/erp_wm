<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    tour: Object,
})

const ROLE_LABELS = {
    guide:       'Tour Guide',
    driver:      'Driver',
    tour_leader: 'Tour Leader',
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
    <Head :title="`Jadwal — ${tour.code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('my-jobs')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <span class="text-base font-semibold text-gray-800 font-mono">{{ tour.code }}</span>
            </div>
        </template>

        <div class="max-w-lg mx-auto px-4 py-5 space-y-5">

            <!-- Header info -->
            <div class="bg-white rounded-xl border shadow-sm p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p v-if="tour.title" class="text-lg font-bold text-gray-800">{{ tour.title }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ fmtDateShort(tour.start_date) }}
                            <template v-if="tour.end_date"> – {{ fmtDateShort(tour.end_date) }}</template>
                        </p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 shrink-0">
                        {{ tour.pax }} Pax
                    </span>
                </div>
            </div>

            <!-- Tim Lapangan -->
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

            <!-- Program Tour -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Program Tour</p>
                </div>

                <div v-if="!tour.items?.length" class="px-4 py-6 text-center text-gray-400 text-sm">
                    Program belum diisi.
                </div>

                <div v-else>
                    <template v-for="[day, items] in itemsByDay(tour.items)" :key="day">
                        <div class="px-4 py-2 bg-blue-50 border-y border-blue-100">
                            <p class="text-xs font-bold text-blue-700 uppercase tracking-wider">
                                {{ Number(day) > 0 ? `Hari ke-${day}` : 'Program' }}
                            </p>
                        </div>
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

            <!-- Informasi Tamu -->
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

            <!-- Catatan -->
            <div v-if="tour.notes" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 mb-1">Catatan</p>
                <p class="text-sm text-amber-900">{{ tour.notes }}</p>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
