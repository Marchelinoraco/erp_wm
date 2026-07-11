<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { Button } from '@/Components/ui/button'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu'
import RowActions from '@/Components/RowActions.vue'
import { confirm } from '@/lib/confirm'
import { INQUIRY_TYPES, TYPE_BADGE, typeLabel } from '@/lib/inquiryTypes'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    tours:       Object,
    filters:     Object,
    type:        { type: String, default: null },
    types:       { type: Object, default: () => ({}) },
    salesPeople: { type: Array, default: () => [] },
})

const status   = ref(props.filters?.status ?? 'all')
const q        = ref(props.filters?.q ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo   = ref(props.filters?.date_to ?? '')
const sales    = ref(props.filters?.sales ?? 'all')

const heading    = computed(() => props.type ? typeLabel(props.type) : 'Semua Inquiry')
const buildLabel = computed(() => props.type ? (INQUIRY_TYPES[props.type]?.build ?? 'Buat') : 'Buat Tour')

function applyFilters() {
    router.get(route('tours.index'), {
        type:      props.type ?? undefined,
        status:    status.value === 'all' ? undefined : status.value,
        q:         q.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value || undefined,
        sales:     sales.value === 'all' ? undefined : sales.value,
    }, {
        preserveState: true, replace: true,
    })
}

// Pencarian di-debounce agar tidak request tiap ketikan
let searchTimer = null
watch(q, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 400)
})
watch([status, dateFrom, dateTo, sales], applyFilters)

const hasFilter = computed(() =>
    status.value !== 'all' || q.value !== '' || dateFrom.value !== '' || dateTo.value !== '' || sales.value !== 'all'
)
function resetFilters() {
    status.value = 'all'; q.value = ''; dateFrom.value = ''; dateTo.value = ''; sales.value = 'all'
}

const STATUS_CONFIG = {
    inquiry:           { label: 'Inquiry',        class: 'bg-gray-100 text-gray-700' },
    quotation_draft:   { label: 'Draft Quotation', class: 'bg-blue-100 text-blue-700' },
    quotation_sent:    { label: 'Sent',            class: 'bg-purple-100 text-purple-700' },
    follow_up:         { label: 'Follow Up',       class: 'bg-yellow-100 text-yellow-700' },
    negotiation:       { label: 'Negosiasi',       class: 'bg-orange-100 text-orange-700' },
    confirmed:         { label: 'Confirmed',       class: 'bg-green-100 text-green-700' },
    cancelled:         { label: 'Cancelled',       class: 'bg-red-100 text-red-700' },
}

function profit(tour) {
    return (Number(tour.total_sell ?? 0) - Number(tour.total_cost ?? 0))
}

function fmtDate(d) {
    return d ? String(d).slice(0, 10) : ''
}

async function confirmDelete(tour) {
    if (await confirm({
        title: `Hapus ${tour.code}?`,
        description: 'Semua item pada inquiry ini akan ikut terhapus permanen.',
        confirmLabel: 'Hapus',
    })) {
        router.delete(route('tours.destroy', tour.id))
    }
}
</script>

<template>
    <Head :title="heading" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">{{ heading }}</h2>
                <Link :href="route('tours.create', { type: type ?? 'tour' })">
                    <Button>+ {{ buildLabel }}</Button>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 space-y-4">
                <!-- Filter -->
                <div class="rounded-lg border bg-white shadow-sm p-3 sm:p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2">
                        <div class="lg:col-span-4">
                            <input type="search" v-model="q" placeholder="Cari kode, judul, atau customer..."
                                class="w-full h-10 border rounded-md px-3 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="lg:col-span-2">
                            <Select v-model="status">
                                <SelectTrigger class="w-full h-10">
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua status</SelectItem>
                                    <SelectItem v-for="(cfg, key) in STATUS_CONFIG" :key="key" :value="key">
                                        {{ cfg.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="lg:col-span-2">
                            <Select v-model="sales">
                                <SelectTrigger class="w-full h-10">
                                    <SelectValue placeholder="Semua sales" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua sales</SelectItem>
                                    <SelectItem v-for="s in salesPeople" :key="s" :value="s">{{ s }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="lg:col-span-3 flex items-center gap-2">
                            <label class="sr-only" for="filter-date-from">Berangkat dari</label>
                            <input id="filter-date-from" type="date" v-model="dateFrom" title="Berangkat dari"
                                class="w-full h-10 border rounded-md px-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                            <span class="text-muted-foreground text-sm shrink-0">s/d</span>
                            <label class="sr-only" for="filter-date-to">Berangkat sampai</label>
                            <input id="filter-date-to" type="date" v-model="dateTo" title="Berangkat sampai"
                                class="w-full h-10 border rounded-md px-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="lg:col-span-1 flex items-center">
                            <Button v-if="hasFilter" variant="ghost" class="w-full h-10 text-muted-foreground" @click="resetFilters">
                                Reset
                            </Button>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-muted-foreground">Tanggal = tanggal keberangkatan tour.</p>
                </div>

                <div class="rounded-lg border bg-white shadow-sm">
                    <!-- ── Mobile: daftar kartu ── -->
                    <div class="md:hidden">
                        <div v-if="tours.data.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                            Belum ada {{ type ? typeLabel(type).toLowerCase() : 'inquiry' }}.
                        </div>
                        <div v-else class="divide-y">
                            <div v-for="t in tours.data" :key="t.id" class="p-4 space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <Link :href="route('tours.edit', t.id)" class="min-w-0 flex-1">
                                        <div class="font-mono text-sm font-semibold">{{ t.code }}</div>
                                        <div class="text-sm font-medium truncate">{{ t.title ?? '—' }}</div>
                                        <div class="text-xs text-muted-foreground truncate">
                                            {{ t.customer?.name ?? '—' }} · {{ t.pax }} pax
                                        </div>
                                    </Link>
                                    <RowActions>
                                        <DropdownMenuItem as-child>
                                            <a :href="route('quotation.preview', t.id)" target="_blank">Lihat Quotation PDF</a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('tours.edit', t.id)">Edit</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem variant="destructive" @click="confirmDelete(t)">
                                            Hapus
                                        </DropdownMenuItem>
                                    </RowActions>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span :class="[TYPE_BADGE[t.type] ?? 'bg-gray-100 text-gray-600', 'inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold']">
                                        {{ types[t.type] ?? typeLabel(t.type) }}
                                    </span>
                                    <span :class="[STATUS_CONFIG[t.status]?.class, 'inline-flex px-2 py-0.5 rounded-full text-xs font-medium']">
                                        {{ STATUS_CONFIG[t.status]?.label ?? t.status }}
                                    </span>
                                    <span v-if="t.status === 'confirmed' && t.invoices_count > 0" class="text-xs text-green-700">✓ Invoice dibuat</span>
                                    <span v-else-if="t.status === 'confirmed'" class="text-xs text-orange-600">⚠ Belum ada invoice</span>
                                </div>

                                <div class="flex items-end justify-between gap-2 text-sm">
                                    <div class="text-xs text-muted-foreground">
                                        <template v-if="t.start_date">{{ fmtDate(t.start_date) }} s/d {{ fmtDate(t.end_date) }}</template>
                                        <template v-else>Tanggal belum diisi</template>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono">{{ fmtRp(t.total_sell) }}</div>
                                        <div class="font-mono text-xs" :class="profit(t) >= 0 ? 'text-green-700' : 'text-red-600'">
                                            {{ fmtRp(profit(t)) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Desktop: tabel ── -->
                    <div class="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Judul / Pax</TableHead>
                                <TableHead>Tanggal</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Nilai Jual</TableHead>
                                <TableHead class="text-right">Profit</TableHead>
                                <TableHead class="w-12"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="tours.data.length === 0" :colspan="8">
                                Belum ada {{ type ? typeLabel(type).toLowerCase() : 'inquiry' }}.
                            </TableEmpty>
                            <TableRow v-for="t in tours.data" :key="t.id">
                                <TableCell class="py-2 font-mono text-sm font-medium whitespace-nowrap">
                                    {{ t.code }}
                                    <span :class="[TYPE_BADGE[t.type] ?? 'bg-gray-100 text-gray-600', 'ml-1.5 align-middle font-sans inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold']">
                                        {{ types[t.type] ?? typeLabel(t.type) }}
                                    </span>
                                </TableCell>
                                <TableCell class="py-2">
                                    <div class="max-w-[13rem] truncate" :title="t.customer?.name">{{ t.customer?.name ?? '—' }}</div>
                                </TableCell>
                                <TableCell class="py-2">
                                    <div class="max-w-[30rem] truncate font-medium" :title="t.title">
                                        {{ t.title ?? '—' }}
                                        <span class="text-xs text-muted-foreground font-normal">· {{ t.pax }} pax</span>
                                    </div>
                                </TableCell>
                                <TableCell class="py-2 text-sm whitespace-nowrap">
                                    <template v-if="t.start_date">
                                        {{ fmtDate(t.start_date) }} <span class="text-muted-foreground">s/d {{ fmtDate(t.end_date) }}</span>
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell class="py-2 whitespace-nowrap">
                                    <span
                                        :class="[STATUS_CONFIG[t.status]?.class, 'inline-flex px-2 py-0.5 rounded-full text-xs font-medium']"
                                    >
                                        {{ STATUS_CONFIG[t.status]?.label ?? t.status }}
                                    </span>
                                    <template v-if="t.status === 'confirmed'">
                                        <span v-if="t.invoices_count > 0" title="Invoice dibuat"
                                            class="ml-1.5 align-middle text-xs text-green-700">✓</span>
                                        <span v-else title="Belum ada invoice"
                                            class="ml-1.5 align-middle text-xs text-orange-600">⚠ Belum ada invoice</span>
                                    </template>
                                </TableCell>
                                <TableCell class="py-2 text-right font-mono text-sm">
                                    {{ fmtRp(t.total_sell) }}
                                </TableCell>
                                <TableCell class="py-2 text-right font-mono text-sm"
                                    :class="profit(t) >= 0 ? 'text-green-700' : 'text-red-600'"
                                >
                                    {{ fmtRp(profit(t)) }}
                                </TableCell>
                                <TableCell class="py-2 text-right">
                                    <RowActions>
                                        <DropdownMenuItem as-child>
                                            <a :href="route('quotation.preview', t.id)" target="_blank">Lihat Quotation PDF</a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('tours.edit', t.id)">Edit</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem variant="destructive" @click="confirmDelete(t)">
                                            Hapus
                                        </DropdownMenuItem>
                                    </RowActions>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    </div>
                </div>

                <div v-if="tours.last_page > 1" class="flex flex-wrap justify-center gap-2">
                    <Link
                        v-for="link in tours.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'min-h-10 min-w-10 inline-flex items-center justify-center px-3 rounded border text-sm',
                            link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-white',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
