<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    pipeline:          Array,
    totalTours:        Number,
    totalConfirmed:    Number,
    confirmedSell:     Number,
    actualCost:        Number,
    realProfit:        Number,
    arOutstanding:     Number,
    apOutstanding:     Number,
    cashInMonth:       Number,
    recentTours:       Array,
    upcomingConfirmed: Array,
})

const STATUS_CONFIG = {
    inquiry:         { label: 'Inquiry',         color: 'bg-gray-100 text-gray-700',     bar: 'bg-gray-400' },
    quotation_draft: { label: 'Draft Quotation', color: 'bg-blue-100 text-blue-700',     bar: 'bg-blue-400' },
    quotation_sent:  { label: 'Sent',            color: 'bg-purple-100 text-purple-700', bar: 'bg-purple-400' },
    follow_up:       { label: 'Follow Up',       color: 'bg-yellow-100 text-yellow-700', bar: 'bg-yellow-400' },
    negotiation:     { label: 'Negosiasi',       color: 'bg-orange-100 text-orange-700', bar: 'bg-orange-400' },
    confirmed:       { label: 'Confirmed',       color: 'bg-green-100 text-green-700',   bar: 'bg-green-500' },
    cancelled:       { label: 'Cancelled',       color: 'bg-red-100 text-red-600',       bar: 'bg-red-400' },
}

const totalPipeline = props.pipeline?.reduce((s, p) => s + p.total, 0) ?? 1

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">Pipeline Dashboard</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ── Stats Cards ── -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg border p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Tours</p>
                        <p class="text-3xl font-bold mt-1">{{ totalTours }}</p>
                        <p class="text-xs text-muted-foreground mt-1">semua status</p>
                    </div>
                    <div class="bg-white rounded-lg border p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Confirmed</p>
                        <p class="text-3xl font-bold mt-1 text-green-700">{{ totalConfirmed }}</p>
                        <p class="text-xs text-muted-foreground mt-1">total terkonfirmasi</p>
                    </div>
                    <div class="bg-white rounded-lg border p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Profit Riil</p>
                        <p class="text-2xl font-bold mt-1" :class="realProfit >= 0 ? 'text-green-700' : 'text-red-600'">
                            {{ fmtRp(realProfit) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">confirmed: jual − biaya aktual</p>
                    </div>
                    <div class="bg-white rounded-lg border p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Diterima Bulan Ini</p>
                        <p class="text-2xl font-bold mt-1 text-blue-700">{{ fmtRp(cashInMonth) }}</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            uang masuk  {{ new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}
                        </p>
                    </div>
                </div>

                <!-- Booking pending alert -->
                <Link
                    v-if="$page.props.pendingBookings > 0"
                    :href="route('bookings.index')"
                    class="flex items-center justify-between gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm shadow-sm transition-colors hover:bg-orange-100"
                >
                    <span class="flex items-center gap-2 text-orange-800">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <span><b>{{ $page.props.pendingBookings }} supplier</b> belum di-booking untuk tour confirmed.</span>
                    </span>
                    <span class="shrink-0 font-medium text-orange-700">Buka Booking →</span>
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- ── Pipeline Funnel ── -->
                    <div class="lg:col-span-1 bg-white rounded-lg border shadow-sm p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">Pipeline Status</h3>
                            <Link :href="route('tours.index')" class="text-xs text-primary hover:underline">
                                Lihat semua →
                            </Link>
                        </div>
                        <div class="space-y-3">
                            <div v-for="item in pipeline" :key="item.status" class="flex items-center gap-3">
                                <div class="w-28 shrink-0">
                                    <span :class="[STATUS_CONFIG[item.status]?.color, 'px-2 py-0.5 rounded-full text-xs font-medium']">
                                        {{ STATUS_CONFIG[item.status]?.label }}
                                    </span>
                                </div>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        :class="STATUS_CONFIG[item.status]?.bar"
                                        class="h-full rounded-full transition-all"
                                        :style="{ width: totalPipeline > 0 ? (item.total / totalPipeline * 100) + '%' : '0%' }"
                                    ></div>
                                </div>
                                <span class="w-6 text-right text-sm font-semibold tabular-nums">{{ item.total }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t flex justify-between text-sm">
                            <span class="text-muted-foreground">Total</span>
                            <span class="font-semibold">{{ totalPipeline }}</span>
                        </div>
                    </div>

                    <!-- ── Right column ── -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Upcoming confirmed tours -->
                        <div class="bg-white rounded-lg border shadow-sm p-5">
                            <h3 class="font-semibold mb-3">Tour Confirmed Mendatang</h3>
                            <div v-if="upcomingConfirmed.length === 0"
                                class="text-sm text-muted-foreground py-4 text-center">
                                Belum ada tour confirmed mendatang.
                            </div>
                            <div v-else class="space-y-0">
                                <div v-for="t in upcomingConfirmed" :key="t.id"
                                    class="flex items-center justify-between py-2.5 border-b last:border-0">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs text-muted-foreground">{{ t.code }}</span>
                                            <span class="font-medium text-sm">{{ t.title ?? t.customer?.name ?? '—' }}</span>
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-0.5">
                                            {{ t.pax }} pax &nbsp;·&nbsp; {{ fmtDate(t.start_date) }}
                                        </div>
                                    </div>
                                    <Link :href="route('tours.edit', t.id)"
                                        class="text-xs text-primary hover:underline shrink-0">
                                        Edit →
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Keuangan (M6 — Riil) -->
                        <div class="bg-white rounded-lg border shadow-sm p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold">Ringkasan Keuangan</h3>
                                <Link :href="route('finance.index')" class="text-xs text-primary hover:underline">
                                    Buka Keuangan →
                                </Link>
                            </div>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-muted-foreground">Nilai Confirmed <span class="text-[10px]">(perkiraan)</span></span>
                                    <span class="font-mono">{{ fmtRp(confirmedSell) }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-muted-foreground">Biaya Aktual <span class="text-[10px]">(bills)</span></span>
                                    <span class="font-mono">{{ fmtRp(actualCost) }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-muted-foreground font-medium">Profit Riil</span>
                                    <span class="font-mono font-semibold" :class="realProfit >= 0 ? 'text-green-700' : 'text-red-600'">
                                        {{ fmtRp(realProfit) }}
                                    </span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-muted-foreground">Diterima Bln Ini</span>
                                    <span class="font-mono text-blue-700">{{ fmtRp(cashInMonth) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Piutang (AR)</span>
                                    <span class="font-mono" :class="arOutstanding > 0 ? 'text-orange-600' : ''">{{ fmtRp(arOutstanding) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Hutang (AP)</span>
                                    <span class="font-mono" :class="apOutstanding > 0 ? 'text-red-600' : ''">{{ fmtRp(apOutstanding) }}</span>
                                </div>
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-3 pt-3 border-t">
                                Profit riil = nilai jual confirmed − biaya aktual (bills). Catat bill di menu Keuangan agar akurat.
                            </p>
                        </div>

                        <!-- Recent tours -->
                        <div class="bg-white rounded-lg border shadow-sm p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold">Tour Terbaru</h3>
                                <Link :href="route('tours.create')"
                                    class="text-xs bg-primary text-primary-foreground px-3 py-1 rounded-md hover:opacity-90 transition-opacity">
                                    + Buat Tour
                                </Link>
                            </div>
                            <div v-if="recentTours.length === 0"
                                class="text-sm text-muted-foreground py-4 text-center">
                                Belum ada tour.
                                <Link :href="route('tours.create')" class="text-primary hover:underline">
                                    Buat sekarang →
                                </Link>
                            </div>
                            <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-muted-foreground border-b">
                                        <th class="text-left pb-2 font-medium">Kode</th>
                                        <th class="text-left pb-2 font-medium">Customer</th>
                                        <th class="text-left pb-2 font-medium">Status</th>
                                        <th class="text-right pb-2 font-medium">Nilai Jual</th>
                                        <th class="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in recentTours" :key="t.id" class="border-b last:border-0">
                                        <td class="py-2 font-mono text-xs">{{ t.code }}</td>
                                        <td class="py-2">{{ t.customer?.name ?? '—' }}</td>
                                        <td class="py-2">
                                            <span :class="[STATUS_CONFIG[t.status]?.color, 'px-2 py-0.5 rounded-full text-xs font-medium']">
                                                {{ STATUS_CONFIG[t.status]?.label }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-right font-mono text-xs">{{ fmtRp(t.total_sell) }}</td>
                                        <td class="py-2 text-right">
                                            <Link :href="route('tours.edit', t.id)"
                                                class="text-xs text-primary hover:underline">
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
