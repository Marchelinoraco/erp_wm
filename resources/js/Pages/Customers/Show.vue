<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Button } from '@/Components/ui/button'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'

const props = defineProps({
    customer:  Object,
    tours:     Array,
    histories: Array,
})

const STATUS_CONFIG = {
    inquiry:         { label: 'Inquiry',        class: 'bg-gray-100 text-gray-700' },
    quotation_draft: { label: 'Draft Quotation', class: 'bg-blue-100 text-blue-700' },
    quotation_sent:  { label: 'Sent',            class: 'bg-purple-100 text-purple-700' },
    follow_up:       { label: 'Follow Up',       class: 'bg-yellow-100 text-yellow-700' },
    negotiation:     { label: 'Negosiasi',       class: 'bg-orange-100 text-orange-700' },
    confirmed:       { label: 'Confirmed',       class: 'bg-green-100 text-green-700' },
    cancelled:       { label: 'Cancelled',       class: 'bg-red-100 text-red-700' },
}

const HISTORY_COLORS = {
    inquiry:         'bg-gray-100 text-gray-600',
    quotation_draft: 'bg-blue-100 text-blue-700',
    quotation_sent:  'bg-purple-100 text-purple-700',
    follow_up:       'bg-yellow-100 text-yellow-700',
    negotiation:     'bg-orange-100 text-orange-700',
    confirmed:       'bg-green-100 text-green-700',
    cancelled:       'bg-red-100 text-red-700',
    revision:        'bg-orange-100 text-orange-700',
    note:            'bg-gray-100 text-gray-600',
    call:            'bg-blue-100 text-blue-700',
    meeting:         'bg-purple-100 text-purple-700',
    email:           'bg-sky-100 text-sky-700',
}

const HISTORY_ICONS = {
    inquiry:         '○',
    quotation_draft: '◑',
    quotation_sent:  '◑',
    follow_up:       '↻',
    negotiation:     '⇄',
    confirmed:       '✓',
    cancelled:       '✕',
    revision:        '↺',
    note:            '·',
    call:            '📞',
    meeting:         '👥',
    email:           '✉',
}

const TYPE_LABELS = { agent: 'Agent', corporate: 'Korporat', direct: 'Direct' }

const totalConfirmed = computed(() => props.tours.filter(t => t.status === 'confirmed').length)
const totalRevenue   = computed(() => props.tours.reduce((sum, t) => sum + (t.total_sell ?? 0), 0))

function fmt(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function fmtDateTime(d) {
    if (!d) return ''
    return new Date(d).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <Head :title="`Riwayat — ${customer.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('customers.index')" class="text-muted-foreground hover:text-foreground">
                    ← Customers
                </Link>
                <span class="text-muted-foreground">/</span>
                <h2 class="text-xl font-semibold">{{ customer.name }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Customer Info -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-semibold">{{ customer.name }}</h3>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-muted text-muted-foreground font-medium">
                                    {{ TYPE_LABELS[customer.type] ?? customer.type }}
                                </span>
                            </div>
                            <div class="text-sm text-muted-foreground space-y-0.5">
                                <p v-if="customer.contact_person">Kontak: {{ customer.contact_person }}</p>
                                <p v-if="customer.phone">Telepon: {{ customer.phone }}</p>
                                <p v-if="customer.email">Email: {{ customer.email }}</p>
                                <p v-if="customer.country">Negara: {{ customer.country }}</p>
                            </div>
                            <p v-if="customer.notes" class="text-sm text-muted-foreground italic mt-1">
                                "{{ customer.notes }}"
                            </p>
                        </div>
                        <Link :href="route('customers.edit', customer.id)">
                            <Button variant="outline" size="sm">Edit Customer</Button>
                        </Link>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="text-xs text-muted-foreground">Total Inquiries</p>
                        <p class="text-2xl font-bold mt-1">{{ tours.length }}</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="text-xs text-muted-foreground">Confirmed</p>
                        <p class="text-2xl font-bold mt-1 text-green-700">{{ totalConfirmed }}</p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="text-xs text-muted-foreground">Conversion Rate</p>
                        <p class="text-2xl font-bold mt-1">
                            {{ tours.length ? Math.round(totalConfirmed / tours.length * 100) : 0 }}%
                        </p>
                    </div>
                    <div class="rounded-lg border bg-white p-4 shadow-sm">
                        <p class="text-xs text-muted-foreground">Total Revenue (Est.)</p>
                        <p class="text-lg font-bold mt-1 font-mono">{{ fmt(totalRevenue) }}</p>
                    </div>
                </div>

                <!-- Layout: table kiri + timeline kanan -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">

                    <!-- Tour Table -->
                    <div class="lg:col-span-3 rounded-lg border bg-white shadow-sm">
                        <div class="px-5 py-4 border-b">
                            <h3 class="font-semibold">Daftar Inquiries</h3>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Judul Tour</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Pax</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead class="w-16"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableEmpty v-if="!tours.length" :colspan="6">
                                    Belum ada inquiry untuk customer ini.
                                </TableEmpty>
                                <TableRow v-for="t in tours" :key="t.id">
                                    <TableCell class="font-mono text-xs font-medium">{{ t.code }}</TableCell>
                                    <TableCell class="max-w-[160px] truncate text-sm">{{ t.title || '—' }}</TableCell>
                                    <TableCell>
                                        <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', STATUS_CONFIG[t.status]?.class ?? 'bg-gray-100 text-gray-700']">
                                            {{ STATUS_CONFIG[t.status]?.label ?? t.status }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ t.pax }}</TableCell>
                                    <TableCell class="text-xs">
                                        {{ t.start_date ? fmtDate(t.start_date) : '—' }}
                                    </TableCell>
                                    <TableCell>
                                        <Link :href="route('tours.edit', t.id)">
                                            <Button variant="outline" size="sm">Buka</Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Timeline Riwayat Status -->
                    <div class="lg:col-span-2 rounded-lg border bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b">
                            <h3 class="font-semibold">Riwayat Perubahan</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Otomatis tercatat saat status tour diubah</p>
                        </div>

                        <div v-if="!histories.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                            Belum ada riwayat perubahan status.
                        </div>

                        <div v-else class="relative px-5 py-4 space-y-0 max-h-[520px] overflow-y-auto">
                            <!-- Vertical line -->
                            <div class="absolute left-[2.4rem] top-4 bottom-4 w-px bg-border"></div>

                            <div v-for="(h, idx) in histories" :key="h.id" class="relative flex gap-3 pb-5 last:pb-0">
                                <!-- Dot -->
                                <div class="relative z-10 shrink-0 mt-0.5">
                                    <span :class="['inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold border-2 border-white', HISTORY_COLORS[h.type] ?? 'bg-gray-100 text-gray-600']">
                                        {{ HISTORY_ICONS[h.type] ?? '·' }}
                                    </span>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                        <span class="text-xs font-mono font-medium text-muted-foreground">
                                            {{ h.tour_code }}
                                        </span>
                                        <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', STATUS_CONFIG[h.status_snapshot]?.class ?? 'bg-gray-100 text-gray-700']">
                                            {{ STATUS_CONFIG[h.status_snapshot]?.label ?? h.status_snapshot }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-foreground leading-snug">{{ h.description }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-muted-foreground">{{ fmtDateTime(h.created_at) }}</span>
                                        <span v-if="h.created_by" class="text-xs text-muted-foreground">· {{ h.created_by }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
