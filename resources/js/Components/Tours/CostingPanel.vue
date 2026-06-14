<script setup>
import { router } from '@inertiajs/vue3'
import { fmtRp } from '@/lib/fmt'
import { STATUS_CONFIG } from '@/lib/tourConstants'

const props = defineProps({ tour: Object })

function changeStatus(status) {
    router.patch(route('tours.update', props.tour.id), { status }, {
        preserveScroll: true, only: ['tour'],
    })
}
</script>

<template>
    <div class="lg:sticky lg:top-6 space-y-4">
        <!-- Costing Summary -->
        <div class="rounded-lg border bg-white p-5 shadow-sm">
            <h3 class="font-semibold mb-4">Ringkasan Biaya</h3>
            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Total Cost (Modal)</span>
                    <span class="font-mono">{{ fmtRp(tour.total_cost) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Total Sell (Jual)</span>
                    <span class="font-mono font-medium">{{ fmtRp(tour.total_sell) }}</span>
                </div>
                <div class="border-t pt-3">
                    <div class="flex justify-between">
                        <span class="font-semibold">Profit</span>
                        <span class="font-mono font-bold text-lg" :class="tour.profit >= 0 ? 'text-green-700' : 'text-red-600'">
                            {{ fmtRp(tour.profit) }}
                        </span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-sm text-muted-foreground">Margin</span>
                        <span class="text-sm font-semibold" :class="tour.margin >= 0 ? 'text-green-700' : 'text-red-600'">
                            {{ tour.margin }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tour info -->
        <div class="rounded-lg border bg-muted/30 p-4 text-sm space-y-2">
            <div class="flex justify-between">
                <span class="text-muted-foreground">Kode</span>
                <span class="font-mono font-medium">{{ tour.code }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Pax</span>
                <span>{{ tour.pax }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-muted-foreground">Item</span>
                <span>{{ tour.items.length }}</span>
            </div>
            <div v-if="tour.start_date" class="flex justify-between">
                <span class="text-muted-foreground">Tanggal</span>
                <span>{{ tour.start_date }} – {{ tour.end_date }}</span>
            </div>
        </div>

        <!-- Quick status change -->
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <p class="text-xs text-muted-foreground mb-2 font-medium">GANTI STATUS CEPAT</p>
            <div class="space-y-1.5">
                <button v-for="(cfg, key) in STATUS_CONFIG" :key="key" type="button"
                    @click="changeStatus(key)"
                    :class="[
                        'w-full text-left px-3 py-1.5 rounded-md text-xs transition-colors',
                        tour.status === key
                            ? cfg.class + ' font-semibold'
                            : 'hover:bg-muted text-muted-foreground',
                    ]">
                    {{ cfg.label }}
                </button>
            </div>
        </div>
    </div>
</template>
