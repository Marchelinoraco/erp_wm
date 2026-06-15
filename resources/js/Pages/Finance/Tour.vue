<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

const props = defineProps({
    tour:      Object,
    suppliers: Array,
})

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

// ── Invoice CRUD ──────────────────────────────────────────────────────────────
const invDialogOpen  = ref(false)
const editingInvoice = ref(null)

const invForm = useForm({
    date:     today(),
    due_date: '',
    total:    '',
    status:   'draft',
    notes:    '',
})

function openAddInvoice() {
    editingInvoice.value = null
    invForm.reset()
    invForm.date   = today()
    invForm.status = 'draft'
    invDialogOpen.value = true
}
function openEditInvoice(inv) {
    editingInvoice.value = inv
    invForm.date     = inv.date?.slice(0, 10) ?? today()
    invForm.due_date = inv.due_date?.slice(0, 10) ?? ''
    invForm.total    = inv.total
    invForm.status   = inv.status
    invForm.notes    = inv.notes ?? ''
    invDialogOpen.value = true
}
function submitInvoice() {
    if (editingInvoice.value) {
        invForm.patch(route('invoices.update', editingInvoice.value.id), {
            preserveScroll: true, only: ['tour'],
            onSuccess: () => { invDialogOpen.value = false },
        })
    } else {
        invForm.post(route('invoices.store', props.tour.id), {
            preserveScroll: true, only: ['tour'],
            onSuccess: () => { invDialogOpen.value = false; invForm.reset() },
        })
    }
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
    date:   today(),
    amount: '',
    method: 'transfer',
    notes:  '',
})

function openAddInvPayment(inv) {
    payingInvoice.value = inv
    invPayForm.reset()
    invPayForm.date   = today()
    invPayForm.method = 'transfer'
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
    supplier_id: null,
    description: '',
    category:    'other',
    date:        today(),
    due_date:    '',
    amount:      '',
    status:      'unpaid',
    notes:       '',
})

function openAddBill() {
    editingBill.value = null
    billForm.reset()
    billForm.date     = today()
    billForm.category = 'other'
    billForm.status   = 'unpaid'
    billDialogOpen.value = true
}
function openEditBill(bill) {
    editingBill.value       = bill
    billForm.supplier_id    = bill.supplier_id ?? null
    billForm.description    = bill.description
    billForm.category       = bill.category
    billForm.date           = bill.date?.slice(0, 10) ?? today()
    billForm.due_date       = bill.due_date?.slice(0, 10) ?? ''
    billForm.amount         = bill.amount
    billForm.status         = bill.status
    billForm.notes          = bill.notes ?? ''
    billDialogOpen.value    = true
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

// ── Bill Payment ──────────────────────────────────────────────────────────────
const billPayDialogOpen = ref(false)
const payingBill        = ref(null)

const billPayForm = useForm({
    date:   today(),
    amount: '',
    method: 'transfer',
    notes:  '',
})

function openAddBillPayment(bill) {
    payingBill.value = bill
    billPayForm.reset()
    billPayForm.date   = today()
    billPayForm.method = 'transfer'
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
                    <Button size="sm" @click="openAddInvoice">+ Invoice</Button>
                </div>

                <div v-if="!tour.invoices?.length" class="px-5 py-6 text-sm text-gray-400 text-center">
                    Belum ada invoice.
                </div>

                <div v-else class="divide-y">
                    <div v-for="inv in tour.invoices" :key="inv.id" class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-sm font-semibold text-gray-800">{{ inv.number }}</span>
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
                                <p class="font-bold text-gray-800">{{ fmtRp(inv.total) }}</p>
                                <p class="text-xs text-orange-600">Sisa: {{ fmtRp(inv.total - invPaid(inv)) }}</p>
                            </div>
                        </div>

                        <!-- Payments -->
                        <div v-if="inv.payments?.length" class="mt-3 ml-2 space-y-1">
                            <div v-for="p in inv.payments" :key="p.id"
                                class="flex items-center gap-2 text-xs text-gray-600 bg-gray-50 rounded px-3 py-1.5">
                                <span class="text-green-600 font-medium">+{{ fmtRp(p.amount) }}</span>
                                <span class="text-gray-400">{{ fmtDate(p.date) }} · {{ p.method }}</span>
                                <span v-if="p.notes" class="text-gray-400 truncate">· {{ p.notes }}</span>
                                <button @click="deleteInvPayment(p.id)"
                                    class="ml-auto text-gray-300 hover:text-red-500 transition-colors text-base leading-none">×</button>
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
                            <Button size="sm" variant="outline" @click="openAddInvPayment(inv)">+ Bayar</Button>
                            <Button size="sm" variant="outline" @click="openEditInvoice(inv)">Edit</Button>
                            <Button size="sm" variant="destructive" @click="deleteInvoice(inv.id)">Hapus</Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── AP — Bill ── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">AP — Bill ke Supplier</h2>
                    <Button size="sm" @click="openAddBill">+ Bill</Button>
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
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    <template v-if="bill.supplier">{{ bill.supplier.name }} · </template>
                                    Tgl: {{ fmtDate(bill.date) }}
                                    <template v-if="bill.due_date"> · Jatuh tempo: {{ fmtDate(bill.due_date) }}</template>
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
                    <DialogTitle>{{ editingInvoice ? 'Edit Invoice' : 'Invoice Baru' }}</DialogTitle>
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
                    <div class="space-y-1.5">
                        <Label>Total (IDR)</Label>
                        <Input type="number" v-model="invForm.total" min="0" step="any"
                            :disabled="editingInvoice && editingInvoice.status === 'draft'"
                            :required="!editingInvoice || editingInvoice.status !== 'draft'" />
                        <p v-if="editingInvoice && editingInvoice.status === 'draft'" class="text-xs text-gray-500">
                            Total otomatis mengikuti item tour selama status <b>Draft</b>. Ubah status ke <b>Dikirim</b> untuk mengunci nilainya.
                        </p>
                    </div>
                    <div v-if="editingInvoice" class="space-y-1.5">
                        <Label>Status</Label>
                        <Select v-model="invForm.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Draft</SelectItem>
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
                        <Button type="submit" :disabled="invForm.processing">
                            {{ editingInvoice ? 'Simpan' : 'Buat' }}
                        </Button>
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

    </AuthenticatedLayout>
</template>
