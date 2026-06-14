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
    tours:   Object,
    filters: Object,
    type:    { type: String, default: null },
    types:   { type: Object, default: () => ({}) },
})

const status = ref(props.filters?.status ?? 'all')

const heading    = computed(() => props.type ? typeLabel(props.type) : 'Semua Inquiry')
const buildLabel = computed(() => props.type ? (INQUIRY_TYPES[props.type]?.build ?? 'Buat') : 'Buat Tour')

watch(status, (val) => {
    router.get(route('tours.index'), {
        type:   props.type ?? undefined,
        status: val === 'all' ? undefined : val,
    }, {
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

function profit(tour) {
    return (Number(tour.total_sell ?? 0) - Number(tour.total_cost ?? 0))
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
                                Belum ada {{ type ? typeLabel(type).toLowerCase() : 'inquiry' }}.
                            </TableEmpty>
                            <TableRow v-for="t in tours.data" :key="t.id">
                                <TableCell class="font-mono text-sm font-medium">
                                    {{ t.code }}
                                    <div class="mt-1">
                                        <span :class="[TYPE_BADGE[t.type] ?? 'bg-gray-100 text-gray-600', 'inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold']">
                                            {{ types[t.type] ?? typeLabel(t.type) }}
                                        </span>
                                    </div>
                                </TableCell>
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
                                    {{ fmtRp(t.total_sell) }}
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm"
                                    :class="profit(t) >= 0 ? 'text-green-700' : 'text-red-600'"
                                >
                                    {{ fmtRp(profit(t)) }}
                                </TableCell>
                                <TableCell class="text-right">
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
