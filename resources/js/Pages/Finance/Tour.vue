<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp, fmtCur, fmtNum } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/Components/ui/dialog'

const props = defineProps({
    tour:         Object,
    suppliers:    Array,
    cashAccounts: { type: Array, default: () => [] },
})

const ACCOUNT_ICON = { bank: '🏦', cash: '💵' }

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function today() {
    return new Date().toISOString().slice(0, 10)
}

const STATUS_COLOR = {
    confirmed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
    inquiry:   'bg-gray-100 text-gray-600',
}

// ── Rincian Profit (internal, read-only) ─────────────────────────────────────
const profitOpen = ref({})

function invTotalCost(inv) {
    return (inv.items ?? []).reduce((s, i) => s + Number(i.line_cost), 0)
}
function invTotalSell(inv) {
    return (inv.items ?? []).reduce((s, i) => s + Number(i.line_sell), 0)
}
// Tour inbound/outbound: profit = tagihan customer (IDR) − total cost item.
// Tipe lain: profit per item (sell − cost). Sama dengan rumus di panel sales.
function invProfit(inv) {
    if (props.tour.type === 'tour') return Number(inv.total_idr) - invTotalCost(inv)
    return invTotalSell(inv) - invTotalCost(inv)
}
function invMargin(inv) {
    const base = props.tour.type === 'tour' ? Number(inv.total_idr) : invTotalSell(inv)
    return base > 0 ? Math.round((invProfit(inv) / base) * 1000) / 10 : 0
}

// ── Invoice CRUD ──────────────────────────────────────────────────────────────
const invDialogOpen  = ref(false)
const editingInvoice = ref(null)

const invForm = useForm({
    date:     today(),
    due_date: '',
    status:   'sent',
    notes:    '',
})

function openEditInvoice(inv) {
    editingInvoice.value = inv
    invForm.date     = inv.date?.slice(0, 10) ?? today()
    invForm.due_date = inv.due_date?.slice(0, 10) ?? ''
    invForm.status   = inv.status
    invForm.notes    = inv.notes ?? ''
    invDialogOpen.value = true
}
function submitInvoice() {
    invForm.patch(route('invoices.update', editingInvoice.value.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { invDialogOpen.value = false },
    })
}
async function deleteInvoice(id) {
    if (await confirm({ title: 'Hapus invoice ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('invoices.destroy', id), { preserveScroll: true, only: ['tour'] })
    }
}

// ── Invoice Payment ───────────────────────────────────────────────────────────
const invPayDialogOpen  = ref(false)
const payingInvoice     = ref(null)

const invPayForm = useForm({
    date:            today(),
    amount:          '',
    method:          'transfer',
    cash_account_id: null,
    notes:           '',
})

function openAddInvPayment(inv) {
    payingInvoice.value = inv
    invPayForm.reset()
    invPayForm.date            = today()
    invPayForm.method          = 'transfer'
    invPayForm.cash_account_id = props.cashAccounts[0]?.id ?? null
    invPayDialogOpen.value = true
}
function submitInvPayment() {
    invPayForm.post(route('invoice-payments.store', payingInvoice.value.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { invPayDialogOpen.value = false; invPayForm.reset() },
    })
}
async function deleteInvPayment(id) {
    if (await confirm({ title: 'Hapus pembayaran ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('invoice-payments.destroy', id), { preserveScroll: true, only: ['tour'] })
    }
}

// ── Bill CRUD ─────────────────────────────────────────────────────────────────
const billDialogOpen  = ref(false)
const editingBill     = ref(null)

const billForm = useForm({
    supplier_id:     null,
    invoice_item_id: null,
    description:     '',
    category:        'other',
    date:            today(),
    due_date:        '',
    amount:          '',
    status:          'unpaid',
    notes:           '',
})

/**
 * Bill dari item Rincian Profit bersupplier dibuat OTOMATIS saat sales
 * menyetujui invoice (lihat Bill::createMissingFromInvoice di backend) —
 * tombol ini murni untuk bill manual (tanpa item asal).
 */
function openAddBill() {
    editingBill.value = null
    billForm.reset()
    billForm.date     = today()
    billForm.category = 'other'
    billForm.status   = 'unpaid'
    billDialogOpen.value = true
}
function openEditBill(bill) {
    editingBill.value        = bill
    billForm.supplier_id     = bill.supplier_id ?? null
    billForm.invoice_item_id = bill.invoice_item_id ?? null
    billForm.description     = bill.description
    billForm.category        = bill.category
    billForm.date             = bill.date?.slice(0, 10) ?? today()
    billForm.due_date        = bill.due_date?.slice(0, 10) ?? ''
    billForm.amount          = bill.amount
    billForm.status          = bill.status
    billForm.notes           = bill.notes ?? ''
    billDialogOpen.value     = true
}
function submitBill() {
    if (editingBill.value) {
        billForm.patch(route('bills.update', editingBill.value.id), {
            preserveScroll: true, only: ['tour'],
            onSuccess: () => { billDialogOpen.value = false },
        })
    } else {
        billForm.post(route('bills.store', props.tour.id), {
            preserveScroll: true, only: ['tour'],
            onSuccess: () => { billDialogOpen.value = false; billForm.reset() },
        })
    }
}
async function deleteBill(id) {
    if (await confirm({ title: 'Hapus bill ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('bills.destroy', id), { preserveScroll: true, only: ['tour'] })
    }
}

// ── Review Permintaan Biaya Tambahan ─────────────────────────────────────────
const approveCrDialogOpen = ref(false)
const rejectCrDialogOpen  = ref(false)
const reviewingCr         = ref(null)

const approveCrForm = useForm({ amount: '', date: today(), due_date: '' })
const rejectCrForm  = useForm({ review_notes: '' })

function openApproveCr(cr) {
    reviewingCr.value    = cr
    approveCrForm.reset()
    approveCrForm.amount = cr.amount
    approveCrForm.date   = today()
    approveCrDialogOpen.value = true
}
function submitApproveCr() {
    approveCrForm.post(route('cost-requests.approve', reviewingCr.value.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { approveCrDialogOpen.value = false },
    })
}
function openRejectCr(cr) {
    reviewingCr.value = cr
    rejectCrForm.reset()
    rejectCrDialogOpen.value = true
}
function submitRejectCr() {
    rejectCrForm.post(route('cost-requests.reject', reviewingCr.value.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { rejectCrDialogOpen.value = false },
    })
}

const CR_CATEGORY_LABEL = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Wisata', other: 'Lainnya',
}
const CR_STATUS_BADGE = {
    pending:  { label: '🟡 Menunggu',  cls: 'bg-amber-100 text-amber-700' },
    approved: { label: '🟢 Disetujui', cls: 'bg-green-100 text-green-700' },
    rejected: { label: '🔴 Ditolak',   cls: 'bg-red-100 text-red-600' },
}

// ── Bill Payment ──────────────────────────────────────────────────────────────
const billPayDialogOpen = ref(false)
const payingBill        = ref(null)

const billPayForm = useForm({
    date:            today(),
    amount:          '',
    method:          'transfer',
    cash_account_id: null,
    notes:           '',
})

function openAddBillPayment(bill) {
    payingBill.value = bill
    billPayForm.reset()
    billPayForm.date            = today()
    billPayForm.method          = 'transfer'
    billPayForm.cash_account_id = props.cashAccounts[0]?.id ?? null
    billPayDialogOpen.value = true
}
function submitBillPayment() {
    billPayForm.post(route('bill-payments.store', payingBill.value.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { billPayDialogOpen.value = false; billPayForm.reset() },
    })
}
async function deleteBillPayment(id) {
    if (await confirm({ title: 'Hapus pembayaran ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('bill-payments.destroy', id), { preserveScroll: true, only: ['tour'] })
    }
}

// ── Computed sisa ─────────────────────────────────────────────────────────────
function invPaid(inv) {
    return inv.payments?.reduce((s, p) => s + Number(p.amount), 0) ?? 0
}
function invPaidIdr(inv) {
    return inv.payments?.reduce((s, p) => s + Number(p.amount_idr ?? p.amount), 0) ?? 0
}
function isNonIdr(inv) {
    return inv.currency && inv.currency !== 'IDR'
}
function billPaid(bill) {
    return bill.payments?.reduce((s, p) => s + Number(p.amount), 0) ?? 0
}

const INV_STATUS = {
    draft:   { label: 'Draft',   cls: 'bg-gray-100 text-gray-600' },
    sent:    { label: 'Dikirim', cls: 'bg-blue-100 text-blue-700' },
    partial: { label: 'Partial', cls: 'bg-yellow-100 text-yellow-700' },
    paid:    { label: 'Lunas',   cls: 'bg-green-100 text-green-700' },
}
const BILL_STATUS = {
    unpaid:  { label: 'Belum Bayar', cls: 'bg-red-100 text-red-600' },
    partial: { label: 'Partial',     cls: 'bg-yellow-100 text-yellow-700' },
    paid:    { label: 'Lunas',       cls: 'bg-green-100 text-green-700' },
}
const CAT_LABEL = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Wisata', other: 'Lainnya',
}
</script>

<template>
    <Head :title="`Keuangan — ${tour.code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('finance.index')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <span class="font-mono text-sm font-bold text-gray-800">{{ tour.code }}</span>
                <span v-if="tour.customer" class="text-sm text-gray-500">· {{ tour.customer.name }}</span>
                <span
                    class="text-xs px-2 py-0.5 rounded-full font-medium ml-1"
                    :class="STATUS_COLOR[tour.status] ?? 'bg-gray-100 text-gray-600'"
                >{{ tour.status }}</span>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

            <!-- ── Budget vs Actual ── -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Budget Cost</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ fmtRp(tour.total_cost) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Actual Cost</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ fmtRp(tour.actual_cost) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Variance</p>
                    <p class="text-lg font-bold mt-1"
                        :class="tour.cost_variance > 0 ? 'text-red-600' : tour.cost_variance < 0 ? 'text-green-600' : 'text-gray-800'">
                        {{ tour.cost_variance > 0 ? '+' : '' }}{{ fmtRp(tour.cost_variance) }}
                    </p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Revenue</p>
                    <p class="text-lg font-bold text-gray-800 mt-1">{{ fmtRp(tour.total_sell) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Diterima</p>
                    <p class="text-lg font-bold text-blue-700 mt-1">{{ fmtRp(tour.received) }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">Sisa: {{ fmtRp(tour.receivable) }}</p>
                </div>
                <div class="rounded-xl border shadow-sm p-4"
                    :class="tour.actual_profit >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold">Profit Riil</p>
                    <p class="text-lg font-bold mt-1"
                        :class="tour.actual_profit >= 0 ? 'text-green-700' : 'text-red-600'">
                        {{ fmtRp(tour.actual_profit) }}
                    </p>
                </div>
            </div>

            <!-- ── AR — Invoice ── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">AR — Invoice ke Customer</h2>
                    <span class="text-xs text-gray-400">Dibuat & disetujui sales</span>
                </div>

                <div v-if="!tour.invoices?.length" class="px-5 py-6 text-sm text-gray-400 text-center">
                    Belum ada invoice yang disetujui sales.
                </div>

                <div v-else class="divide-y">
                    <div v-for="inv in tour.invoices" :key="inv.id" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-sm font-semibold text-gray-800">{{ inv.finance_number ?? inv.number }}</span>
                                    <span v-if="inv.finance_number" class="font-mono text-xs text-gray-400">{{ inv.number }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="INV_STATUS[inv.status]?.cls">
                                        {{ INV_STATUS[inv.status]?.label }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Tgl: {{ fmtDate(inv.date) }}
                                    <template v-if="inv.due_date"> · Jatuh tempo: {{ fmtDate(inv.due_date) }}</template>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-gray-800">{{ fmtCur(inv.total, inv.currency) }}</p>
                                <p v-if="isNonIdr(inv)" class="text-xs text-gray-400">≈ {{ fmtRp(inv.total_idr) }}</p>
                                <p class="text-xs text-orange-600">Sisa: {{ fmtCur(inv.total - invPaid(inv), inv.currency) }}</p>
                                <p v-if="isNonIdr(inv)" class="text-xs text-orange-400">≈ {{ fmtRp(inv.total_idr - invPaidIdr(inv)) }}</p>
                            </div>
                        </div>

                        <!-- Payments -->
                        <div v-if="inv.payments?.length" class="mt-3 ml-2 space-y-1">
                            <div v-for="p in inv.payments" :key="p.id"
                                class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 rounded px-3 py-1.5">
                                <span class="text-green-600 font-medium">+{{ fmtCur(p.amount, inv.currency) }}</span>
                                <template v-if="isNonIdr(inv)">
                                    <span class="text-gray-400 text-xs">≈ {{ fmtRp(p.amount_idr) }}</span>
                                </template>
                                <span class="text-gray-400">{{ fmtDate(p.date) }} · {{ p.method }}</span>
                                <span v-if="p.cash_account" class="text-gray-400">· {{ ACCOUNT_ICON[p.cash_account.type] }} {{ p.cash_account.name }}</span>
                                <span v-if="p.notes" class="text-gray-400 truncate">· {{ p.notes }}</span>
                                <button @click="deleteInvPayment(p.id)"
                                    class="ml-auto text-gray-300 hover:text-red-500 transition-colors text-base leading-none">×</button>
                            </div>
                        </div>

                        <!-- Rincian Profit (internal, read-only untuk akuntan) -->
                        <div v-if="inv.items?.length" class="mt-3 rounded-md border">
                            <button type="button" @click="profitOpen[inv.id] = !profitOpen[inv.id]"
                                class="w-full flex items-center justify-between px-3 py-2 text-left text-xs font-semibold uppercase text-gray-400 hover:bg-gray-50">
                                <span>Rincian Profit (internal · IDR)</span>
                                <span class="flex items-center gap-2">
                                    <span class="font-mono normal-case text-[11px]"
                                        :class="invProfit(inv) >= 0 ? 'text-green-700' : 'text-red-600'">
                                        {{ fmtRp(invProfit(inv)) }} ({{ invMargin(inv) }}%)
                                    </span>
                                    <span>{{ profitOpen[inv.id] ? '▾' : '▸' }}</span>
                                </span>
                            </button>
                            <div v-if="profitOpen[inv.id]" class="border-t">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b bg-gray-50 text-gray-400 text-xs uppercase">
                                                <th class="px-3 py-2 text-left">Deskripsi</th>
                                                <th class="px-3 py-2 text-center w-14">Qty</th>
                                                <th class="px-3 py-2 text-center w-14">Mlm</th>
                                                <th class="px-3 py-2 text-right w-28">Cost/unit</th>
                                                <th class="px-3 py-2 text-right w-28">Sell/unit</th>
                                                <th class="px-3 py-2 text-right w-28">Total Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in inv.items" :key="item.id" class="border-b last:border-0">
                                                <td class="px-3 py-1.5">
                                                    {{ item.description }}
                                                    <span class="block text-xs text-gray-400">
                                                        {{ TYPE_LABELS[item.product_type] ?? item.product_type }}
                                                        <template v-if="item.start_date">
                                                            · 📅 {{ fmtDate(item.start_date) }}<template v-if="item.end_date && item.end_date !== item.start_date"> – {{ fmtDate(item.end_date) }}</template>
                                                        </template>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-1.5 text-center">{{ item.qty }}</td>
                                                <td class="px-3 py-1.5 text-center">{{ item.nights }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_cost) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_sell) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-medium">{{ fmtRp(item.line_cost) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="border-t bg-gray-50/50 px-4 py-3 text-sm space-y-1">
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>Total Cost (Modal)</span>
                                        <span class="font-mono">{{ fmtRp(invTotalCost(inv)) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500">
                                        <span>{{ tour.type === 'tour' ? 'Total Tagihan Customer (IDR)' : 'Total Jual Item' }}</span>
                                        <span class="font-mono">{{ fmtRp(tour.type === 'tour' ? inv.total_idr : invTotalSell(inv)) }}</span>
                                    </div>
                                    <div class="flex justify-between font-semibold"
                                        :class="invProfit(inv) >= 0 ? 'text-green-700' : 'text-red-600'">
                                        <span>Profit</span>
                                        <span class="font-mono">{{ fmtRp(invProfit(inv)) }} ({{ invMargin(inv) }}%)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-3 flex gap-2 flex-wrap">
                            <a :href="route('invoices.preview', inv.id)" target="_blank">
                                <Button size="sm" variant="outline">👁 PDF</Button>
                            </a>
                            <a :href="route('invoices.download', inv.id)">
                                <Button size="sm" variant="outline">⬇ Unduh</Button>
                            </a>
                            <a v-if="inv.items?.length" :href="route('invoices.profit-pdf', inv.id)" target="_blank">
                                <Button size="sm" variant="outline">📊 Rincian Profit PDF</Button>
                            </a>
                            <Button size="sm" variant="outline" @click="openAddInvPayment(inv)">+ Bayar</Button>
                            <Button size="sm" variant="outline" @click="openEditInvoice(inv)">Edit</Button>
                            <Button size="sm" variant="destructive" @click="deleteInvoice(inv.id)">Hapus</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Permintaan Biaya Tambahan (dari sales) ── -->
            <div v-if="tour.cost_requests?.length" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b">
                    <h2 class="text-sm font-semibold text-gray-800">Permintaan Biaya Tambahan</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Biaya tak terduga yang diajukan sales saat tour berjalan.</p>
                </div>
                <div class="divide-y">
                    <div v-for="cr in tour.cost_requests" :key="cr.id" class="px-5 py-4"
                        :class="cr.status === 'pending' ? 'bg-amber-50/50' : ''">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-gray-800">{{ cr.description }}</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                        {{ CR_CATEGORY_LABEL[cr.category] ?? cr.category }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="CR_STATUS_BADGE[cr.status]?.cls">
                                        {{ CR_STATUS_BADGE[cr.status]?.label }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Diajukan {{ cr.requested_by?.name }}
                                    <template v-if="cr.supplier"> · {{ cr.supplier.name }}</template>
                                    · {{ fmtDate(cr.created_at) }}
                                </p>
                                <p v-if="cr.notes" class="text-xs text-gray-500 mt-1">"{{ cr.notes }}"</p>
                                <p v-if="cr.status !== 'pending'" class="text-xs text-gray-400 mt-1">
                                    Direview {{ cr.reviewed_by?.name }} · {{ fmtDate(cr.reviewed_at) }}
                                    <template v-if="cr.review_notes"> — {{ cr.review_notes }}</template>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-gray-800">{{ fmtRp(cr.amount) }}</p>
                                <p class="text-xs text-gray-400">perkiraan</p>
                            </div>
                        </div>
                        <div v-if="cr.status === 'pending'" class="mt-3 flex gap-2">
                            <Button size="sm" @click="openApproveCr(cr)">Setujui</Button>
                            <Button size="sm" variant="outline" @click="openRejectCr(cr)">Tolak</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── AP — Bill ── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">AP — Bill ke Supplier</h2>
                    <Button size="sm" @click="openAddBill()">+ Bill</Button>
                </div>

                <div v-if="!tour.bills?.length" class="px-5 py-6 text-sm text-gray-400 text-center">
                    Belum ada bill.
                </div>

                <div v-else class="divide-y">
                    <div v-for="bill in tour.bills" :key="bill.id" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-gray-800">{{ bill.description }}</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                        {{ CAT_LABEL[bill.category] ?? bill.category }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="BILL_STATUS[bill.status]?.cls">
                                        {{ BILL_STATUS[bill.status]?.label }}
                                    </span>
                                    <span v-if="Number(bill.amount) === 0" title="Dibuat otomatis dari Rincian Profit, nominal belum diisi"
                                        class="text-xs px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-700">
                                        ⚠ Perlu diisi nominal
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    <template v-if="bill.supplier">{{ bill.supplier.name }} · </template>
                                    Tgl: {{ fmtDate(bill.date) }}
                                    <template v-if="bill.due_date"> · Jatuh tempo: {{ fmtDate(bill.due_date) }}</template>
                                    <template v-if="bill.invoice_item?.invoice">
                                        · 📋 Dari Rincian Profit {{ bill.invoice_item.invoice.finance_number ?? bill.invoice_item.invoice.number }}
                                    </template>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-gray-800">{{ fmtRp(bill.amount) }}</p>
                                <p class="text-xs text-red-600">Sisa: {{ fmtRp(bill.amount - billPaid(bill)) }}</p>
                            </div>
                        </div>

                        <!-- Payments -->
                        <div v-if="bill.payments?.length" class="mt-3 ml-2 space-y-1">
                            <div v-for="p in bill.payments" :key="p.id"
                                class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 rounded px-3 py-1.5">
                                <span class="text-blue-600 font-medium">-{{ fmtRp(p.amount) }}</span>
                                <span class="text-gray-400">{{ fmtDate(p.date) }} · {{ p.method }}</span>
                                <span v-if="p.cash_account" class="text-gray-400">· {{ ACCOUNT_ICON[p.cash_account.type] }} {{ p.cash_account.name }}</span>
                                <span v-if="p.notes" class="text-gray-400 truncate">· {{ p.notes }}</span>
                                <button @click="deleteBillPayment(p.id)"
                                    class="ml-auto text-gray-300 hover:text-red-500 transition-colors text-base leading-none">×</button>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-3 flex gap-2">
                            <Button size="sm" variant="outline" @click="openAddBillPayment(bill)">+ Bayar</Button>
                            <Button size="sm" variant="outline" @click="openEditBill(bill)">Edit</Button>
                            <Button size="sm" variant="destructive" @click="deleteBill(bill.id)">Hapus</Button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Dialog: Invoice ── -->
        <Dialog v-model:open="invDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Edit Invoice</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitInvoice" class="space-y-3 mt-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="invForm.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jatuh Tempo</Label>
                            <Input type="date" v-model="invForm.due_date" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        Nilai invoice mengikuti rincian item yang disetujui sales dan tidak bisa diubah di sini.
                    </p>
                    <div class="space-y-1.5">
                        <Label>Status</Label>
                        <Select v-model="invForm.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="sent">Dikirim</SelectItem>
                                <SelectItem value="partial">Partial</SelectItem>
                                <SelectItem value="paid">Lunas</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Input v-model="invForm.notes" placeholder="Opsional..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="invDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="invForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Invoice Payment ── -->
        <Dialog v-model:open="invPayDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Catat Penerimaan</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitInvPayment" class="space-y-3 mt-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="invPayForm.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Metode</Label>
                            <Select v-model="invPayForm.method">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="transfer">Transfer</SelectItem>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="other">Lainnya</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Akun Kas <span class="text-destructive">*</span></Label>
                        <Select v-model="invPayForm.cash_account_id">
                            <SelectTrigger><SelectValue placeholder="Pilih akun kas..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">
                                    {{ ACCOUNT_ICON[a.type] }} {{ a.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="invPayForm.errors.cash_account_id" class="text-xs text-destructive">{{ invPayForm.errors.cash_account_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jumlah (IDR)</Label>
                        <Input type="number" v-model="invPayForm.amount" min="1" step="any" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Input v-model="invPayForm.notes" placeholder="Opsional..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="invPayDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="invPayForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Bill ── -->
        <Dialog v-model:open="billDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editingBill ? 'Edit Bill' : 'Bill Baru' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitBill" class="space-y-3 mt-2">
                    <div class="space-y-1.5">
                        <Label>Deskripsi</Label>
                        <Input v-model="billForm.description" placeholder="Mis. Hotel Aryaduta 3 malam" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Kategori</Label>
                            <Select v-model="billForm.category">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="hotel">Hotel</SelectItem>
                                    <SelectItem value="transport">Transport</SelectItem>
                                    <SelectItem value="guide">Guide</SelectItem>
                                    <SelectItem value="restaurant">Restaurant</SelectItem>
                                    <SelectItem value="attraction">Wisata</SelectItem>
                                    <SelectItem value="other">Lainnya</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Supplier</Label>
                            <Select :model-value="billForm.supplier_id?.toString() ?? ''"
                                @update:model-value="v => billForm.supplier_id = v ? Number(v) : null">
                                <SelectTrigger><SelectValue placeholder="— Opsional —" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in suppliers" :key="s.id" :value="s.id.toString()">
                                        {{ s.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="billForm.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jatuh Tempo</Label>
                            <Input type="date" v-model="billForm.due_date" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jumlah (IDR)</Label>
                        <Input type="number" v-model="billForm.amount" min="0" step="any" required />
                    </div>
                    <div v-if="editingBill" class="space-y-1.5">
                        <Label>Status</Label>
                        <Select v-model="billForm.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unpaid">Belum Bayar</SelectItem>
                                <SelectItem value="partial">Partial</SelectItem>
                                <SelectItem value="paid">Lunas</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Input v-model="billForm.notes" placeholder="Opsional..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="billDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="billForm.processing">
                            {{ editingBill ? 'Simpan' : 'Buat' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Bill Payment ── -->
        <Dialog v-model:open="billPayDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Catat Pembayaran</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitBillPayment" class="space-y-3 mt-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="billPayForm.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Metode</Label>
                            <Select v-model="billPayForm.method">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="transfer">Transfer</SelectItem>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="other">Lainnya</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Akun Kas <span class="text-destructive">*</span></Label>
                        <Select v-model="billPayForm.cash_account_id">
                            <SelectTrigger><SelectValue placeholder="Pilih akun kas..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">
                                    {{ ACCOUNT_ICON[a.type] }} {{ a.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="billPayForm.errors.cash_account_id" class="text-xs text-destructive">{{ billPayForm.errors.cash_account_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jumlah (IDR)</Label>
                        <Input type="number" v-model="billPayForm.amount" min="1" step="any" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Input v-model="billPayForm.notes" placeholder="Opsional..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="billPayDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="billPayForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Setujui Permintaan Biaya Tambahan ── -->
        <Dialog v-model:open="approveCrDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Setujui Biaya Tambahan</DialogTitle>
                </DialogHeader>
                <p class="text-xs text-gray-500">
                    {{ reviewingCr?.description }} — nominal akan tercatat sebagai Bill baru untuk tour ini.
                </p>
                <form @submit.prevent="submitApproveCr" class="space-y-3 mt-2">
                    <div class="space-y-1.5">
                        <Label>Nominal Final (IDR)</Label>
                        <Input type="number" v-model="approveCrForm.amount" min="0" step="1000" required />
                        <p v-if="approveCrForm.errors.amount" class="text-xs text-destructive">{{ approveCrForm.errors.amount }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="approveCrForm.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Jatuh Tempo</Label>
                            <Input type="date" v-model="approveCrForm.due_date" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="approveCrDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="approveCrForm.processing">Setujui & Buat Bill</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Tolak Permintaan Biaya Tambahan ── -->
        <Dialog v-model:open="rejectCrDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Tolak Biaya Tambahan</DialogTitle>
                </DialogHeader>
                <p class="text-xs text-gray-500">{{ reviewingCr?.description }}</p>
                <form @submit.prevent="submitRejectCr" class="space-y-3 mt-2">
                    <div class="space-y-1.5">
                        <Label>Alasan Penolakan</Label>
                        <Textarea v-model="rejectCrForm.review_notes" rows="3" required placeholder="Jelaskan alasannya..." />
                        <p v-if="rejectCrForm.errors.review_notes" class="text-xs text-destructive">{{ rejectCrForm.errors.review_notes }}</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="rejectCrDialogOpen = false">Batal</Button>
                        <Button type="submit" variant="destructive" :disabled="rejectCrForm.processing">Tolak</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

    </AuthenticatedLayout>
</template>
