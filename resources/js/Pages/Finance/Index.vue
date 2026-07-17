<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { fmtRp, fmtCur } from '@/lib/fmt'

const props = defineProps({
    ar_total:             Number,
    ar_received:          Number,
    ap_total:             Number,
    ap_paid:              Number,
    outstanding_invoices: Array,
    paid_invoices:        { type: Array, default: () => [] },
    unpaid_bills:         Array,
    confirmed_tours:      { type: Array, default: () => [] },
})

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const arOutstanding = props.ar_total - props.ar_received
const apOutstanding = props.ap_total - props.ap_paid

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
</script>

<template>
    <Head title="Keuangan" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Keuangan</h1>
        </template>

        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Invoice</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ fmtRp(ar_total) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Piutang (AR)</p>
                    <p class="text-xl font-bold mt-1" :class="arOutstanding > 0 ? 'text-orange-600' : 'text-gray-900'">
                        {{ fmtRp(arOutstanding) }}
                    </p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Bill</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ fmtRp(ap_total) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Hutang (AP)</p>
                    <p class="text-xl font-bold mt-1" :class="apOutstanding > 0 ? 'text-red-600' : 'text-gray-900'">
                        {{ fmtRp(apOutstanding) }}
                    </p>
                </div>
            </div>

            <!-- Tour Confirmed — pintu masuk pencatatan keuangan -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800">Tour Confirmed</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Buat invoice (tagihan customer) &amp; bill (bayar supplier) untuk catat profit riil</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ confirmed_tours.length }} tour</span>
                </div>
                <div v-if="!confirmed_tours.length" class="px-5 py-8 text-center text-sm text-gray-400">
                    Belum ada tour berstatus confirmed.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Tour</th>
                                <th class="px-4 py-2.5 text-left">Customer</th>
                                <th class="px-4 py-2.5 text-right">Est. Profit</th>
                                <th class="px-4 py-2.5 text-center">Invoice</th>
                                <th class="px-4 py-2.5 text-center">Bill</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in confirmed_tours" :key="t.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-mono text-xs font-medium text-gray-700">{{ t.code }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-[180px]">{{ t.title || '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ t.customer ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold"
                                    :class="t.est_profit >= 0 ? 'text-green-700' : 'text-red-600'">
                                    {{ fmtRp(t.est_profit) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="t.invoices_count" class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                                        {{ fmtRp(t.invoiced_total) }}
                                    </span>
                                    <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">
                                        Belum ada
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="t.bills_count" class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600 font-medium">
                                        {{ fmtRp(t.billed_total) }}
                                    </span>
                                    <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">
                                        Belum ada
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('finance.tour', t.id)"
                                        class="text-xs font-medium text-blue-600 hover:underline"
                                    >
                                        {{ (t.invoices_count || t.bills_count) ? 'Detail' : 'Catat Keuangan →' }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoice Belum Lunas -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Invoice Belum Lunas</h2>
                    <span class="text-xs text-gray-400">{{ outstanding_invoices.length }} invoice</span>
                </div>
                <div v-if="!outstanding_invoices.length" class="px-5 py-8 text-center text-sm text-gray-400">
                    Tidak ada invoice outstanding.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Nomor</th>
                                <th class="px-4 py-2.5 text-left">Tour</th>
                                <th class="px-4 py-2.5 text-left">Customer</th>
                                <th class="px-4 py-2.5 text-left">Tanggal</th>
                                <th class="px-4 py-2.5 text-right">Total</th>
                                <th class="px-4 py-2.5 text-right">Sisa</th>
                                <th class="px-4 py-2.5 text-center">Status</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="inv in outstanding_invoices" :key="inv.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <span class="font-medium text-gray-700">{{ inv.finance_number ?? inv.number }}</span>
                                    <span v-if="inv.finance_number" class="block text-[11px] text-gray-400">{{ inv.number }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ inv.tour?.code ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ inv.tour?.customer?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ fmtDate(inv.date) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800">
                                    {{ fmtCur(inv.total, inv.currency) }}
                                    <span v-if="inv.currency && inv.currency !== 'IDR'" class="block text-xs text-gray-400">
                                        ≈ {{ fmtRp(inv.total_idr) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-orange-600">
                                    {{ fmtCur(inv.total - inv.payments.reduce((s, p) => s + Number(p.amount), 0), inv.currency) }}
                                    <span v-if="inv.currency && inv.currency !== 'IDR'" class="block text-xs text-orange-400">
                                        ≈ {{ fmtRp(inv.total_idr - inv.payments.reduce((s, p) => s + Number(p.amount_idr ?? p.amount), 0)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="INV_STATUS[inv.status]?.cls ?? 'bg-gray-100 text-gray-600'">
                                        {{ INV_STATUS[inv.status]?.label ?? inv.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        v-if="inv.tour"
                                        :href="route('finance.tour', inv.tour.id)"
                                        class="text-xs text-blue-600 hover:underline"
                                    >Detail</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoice Lunas -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Invoice Lunas</h2>
                    <span class="text-xs text-gray-400">{{ paid_invoices.length }} invoice</span>
                </div>
                <div v-if="!paid_invoices.length" class="px-5 py-8 text-center text-sm text-gray-400">
                    Belum ada invoice lunas.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Nomor</th>
                                <th class="px-4 py-2.5 text-left">Tour</th>
                                <th class="px-4 py-2.5 text-left">Customer</th>
                                <th class="px-4 py-2.5 text-left">Tanggal</th>
                                <th class="px-4 py-2.5 text-right">Total</th>
                                <th class="px-4 py-2.5 text-center">Status</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="inv in paid_invoices" :key="inv.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs">
                                    <span class="font-medium text-gray-700">{{ inv.finance_number ?? inv.number }}</span>
                                    <span v-if="inv.finance_number" class="block text-[11px] text-gray-400">{{ inv.number }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ inv.tour?.code ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ inv.tour?.customer?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ fmtDate(inv.date) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800">
                                    {{ fmtCur(inv.total, inv.currency) }}
                                    <span v-if="inv.currency && inv.currency !== 'IDR'" class="block text-xs text-gray-400">
                                        ≈ {{ fmtRp(inv.total_idr) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="INV_STATUS[inv.status]?.cls ?? 'bg-gray-100 text-gray-600'">
                                        {{ INV_STATUS[inv.status]?.label ?? inv.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        v-if="inv.tour"
                                        :href="route('finance.tour', inv.tour.id)"
                                        class="text-xs text-blue-600 hover:underline"
                                    >Detail</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bill Belum Dibayar -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Bill Belum Dibayar</h2>
                    <span class="text-xs text-gray-400">{{ unpaid_bills.length }} bill</span>
                </div>
                <div v-if="!unpaid_bills.length" class="px-5 py-8 text-center text-sm text-gray-400">
                    Tidak ada bill outstanding.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 text-left">Deskripsi</th>
                                <th class="px-4 py-2.5 text-left">Tour</th>
                                <th class="px-4 py-2.5 text-left">Supplier</th>
                                <th class="px-4 py-2.5 text-left">Tanggal</th>
                                <th class="px-4 py-2.5 text-right">Jumlah</th>
                                <th class="px-4 py-2.5 text-right">Sisa</th>
                                <th class="px-4 py-2.5 text-center">Status</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="bill in unpaid_bills" :key="bill.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-800 max-w-xs truncate">{{ bill.description }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ bill.tour?.code ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ bill.supplier?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ fmtDate(bill.date) }}</td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800">{{ fmtRp(bill.amount) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-red-600">
                                    {{ fmtRp(bill.amount - bill.payments.reduce((s, p) => s + Number(p.amount), 0)) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="BILL_STATUS[bill.status]?.cls ?? 'bg-gray-100 text-gray-600'">
                                        {{ BILL_STATUS[bill.status]?.label ?? bill.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        v-if="bill.tour"
                                        :href="route('finance.tour', bill.tour.id)"
                                        class="text-xs text-blue-600 hover:underline"
                                    >Detail</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
