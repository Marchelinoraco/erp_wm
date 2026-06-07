<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    tours: Array,
})

const ROLE_LABELS = {
    guide:       'Tour Guide',
    driver:      'Driver',
    tour_leader: 'Tour Leader',
}

const STATUS_LABEL = {
    inquiry:          'Inquiry',
    quotation_draft:  'Draft Quotation',
    quotation_sent:   'Quotation Dikirim',
    follow_up:        'Follow Up',
    negotiation:      'Negosiasi',
    confirmed:        'Confirmed',
    cancelled:        'Cancelled',
}

const STATUS_COLOR = {
    inquiry:          'bg-gray-100 text-gray-600',
    quotation_draft:  'bg-blue-50 text-blue-600',
    quotation_sent:   'bg-blue-100 text-blue-700',
    follow_up:        'bg-yellow-100 text-yellow-700',
    negotiation:      'bg-orange-100 text-orange-700',
    confirmed:        'bg-green-100 text-green-700',
    cancelled:        'bg-red-100 text-red-600',
}

function fmtDateRange(start, end) {
    if (!start) return '—'
    const opts = { day: 'numeric', month: 'short', year: 'numeric' }
    const s = new Date(start).toLocaleDateString('id-ID', opts)
    const e = end ? new Date(end).toLocaleDateString('id-ID', opts) : null
    return e && e !== s ? `${s} – ${e}` : s
}
</script>

<template>
    <Head title="Jadwal Saya" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Jadwal Saya</h1>
        </template>

        <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

            <div v-if="!tours.length" class="bg-white rounded-xl border p-10 text-center text-gray-400">
                <p class="text-sm">Belum ada tour yang ditugaskan ke Anda.</p>
            </div>

            <Link
                v-for="tour in tours"
                :key="tour.id"
                :href="route('my-jobs.show', tour.id)"
                class="block bg-white rounded-xl border shadow-sm hover:shadow-md transition-shadow p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-sm font-bold text-gray-800">{{ tour.code }}</span>
                            <span
                                class="text-xs px-2 py-0.5 rounded-full font-medium"
                                :class="STATUS_COLOR[tour.status] ?? 'bg-gray-100 text-gray-600'"
                            >
                                {{ STATUS_LABEL[tour.status] ?? tour.status }}
                            </span>
                        </div>
                        <p v-if="tour.title" class="mt-1 text-sm font-medium text-gray-700 truncate">{{ tour.title }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ fmtDateRange(tour.start_date, tour.end_date) }}
                            <template v-if="tour.customer?.name"> · {{ tour.customer.name }}</template>
                        </p>
                    </div>
                    <div class="shrink-0 space-y-1 text-right">
                        <span
                            v-for="a in tour.assignments"
                            :key="a.id"
                            class="block text-xs px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-700"
                        >
                            {{ ROLE_LABELS[a.role] ?? a.role }}
                        </span>
                    </div>
                </div>
            </Link>

        </div>
    </AuthenticatedLayout>
</template>
