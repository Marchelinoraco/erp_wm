<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'

const props = defineProps({
    tours:   Object,
    filters: Object,
})

const status = ref(props.filters?.status ?? 'all')

watch(status, (val) => {
    router.get(route('tours.index'), { status: val === 'all' ? undefined : val }, {
        preserveState: true, replace: true,
    })
})

const STATUS_CONFIG = {
    inquiry:           { label: 'Inquiry',        class: 'bg-gray-100 text-gray-700' },
    quotation_draft:   { label: 'Draft Quotation', class: 'bg-blue-100 text-blue-700' },
    quotation_sent:    { label: 'Sent',            class: 'bg-purple-100 text-purple-700' },
    follow_up:         { label: 'Follow Up',       class: 'bg-yellow-100 text-yellow-700' },
    negotiation:       { label: 'Negosiasi',       class: 'bg-orange-100 text-orange-700' },
    confirmed:         { label: 'Confirmed',       class: 'bg-green-100 text-green-700' },
    cancelled:         { label: 'Cancelled',       class: 'bg-red-100 text-red-700' },
}

function fmt(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

function profit(tour) {
    return (Number(tour.total_sell ?? 0) - Number(tour.total_cost ?? 0))
}

function confirmDelete(tour) {
    if (confirm(`Hapus tour ${tour.code}? Semua item akan ikut terhapus.`)) {
        router.delete(route('tours.destroy', tour.id))
    }
}
</script>

<template>
    <Head title="Tours" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Tours</h2>
                <Link :href="route('tours.create')">
                    <Button>+ Buat Tour</Button>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
                <!-- Filter status -->
                <div class="flex items-center gap-3">
                    <span class="text-sm text-muted-foreground">Status:</span>
                    <Select v-model="status">
                        <SelectTrigger class="w-52">
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

                <div class="rounded-lg border bg-white shadow-sm">
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
                                <TableHead class="w-44"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="tours.data.length === 0" :colspan="8">
                                Belum ada tour.
                            </TableEmpty>
                            <TableRow v-for="t in tours.data" :key="t.id">
                                <TableCell class="font-mono text-sm font-medium">{{ t.code }}</TableCell>
                                <TableCell>{{ t.customer?.name ?? '—' }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ t.title ?? '—' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ t.pax }} pax</div>
                                </TableCell>
                                <TableCell class="text-sm">
                                    <template v-if="t.start_date">
                                        {{ t.start_date }}<br/>
                                        <span class="text-muted-foreground">s/d {{ t.end_date }}</span>
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell>
                                    <span
                                        :class="[STATUS_CONFIG[t.status]?.class, 'inline-flex px-2 py-0.5 rounded-full text-xs font-medium']"
                                    >
                                        {{ STATUS_CONFIG[t.status]?.label ?? t.status }}
                                    </span>
                                    <div v-if="t.status === 'confirmed'" class="mt-1">
                                        <span v-if="t.invoices_count > 0"
                                            class="inline-flex items-center gap-1 text-xs text-green-700">
                                            ✓ Invoice dibuat
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center gap-1 text-xs text-orange-600">
                                            ⚠ Belum ada invoice
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm">
                                    {{ fmt(t.total_sell) }}
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm"
                                    :class="profit(t) >= 0 ? 'text-green-700' : 'text-red-600'"
                                >
                                    {{ fmt(profit(t)) }}
                                </TableCell>
                                <TableCell class="text-right space-x-1 whitespace-nowrap">
                                    <a :href="route('quotation.preview', t.id)" target="_blank" title="Lihat Quotation PDF">
                                        <Button variant="ghost" size="sm">PDF</Button>
                                    </a>
                                    <Link :href="route('tours.edit', t.id)">
                                        <Button variant="outline" size="sm">Edit</Button>
                                    </Link>
                                    <Button variant="destructive" size="sm" @click="confirmDelete(t)">
                                        Hapus
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="tours.last_page > 1" class="flex justify-center gap-2">
                    <Link
                        v-for="link in tours.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 rounded border text-sm',
                            link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-white',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
