<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtNum, fmtRp } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({ tour: Object, products: Array })

const invoices = computed(() => props.tour.invoices ?? [])

// ── Form edit baris (keyed by item id, unik lintas invoice) ─────────────────────
const itemForms = reactive({})

watch(
    () => props.tour.invoices,
    (list) => {
        const ids = []
        ;(list ?? []).forEach(inv => {
            (inv.items ?? []).forEach(item => {
                ids.push(item.id)
                itemForms[item.id] = {
                    qty:         item.qty,
                    nights:      item.nights,
                    description: item.description ?? '',
                    unit_cost:   item.unit_cost,
                    unit_sell:   item.unit_sell,
                }
            })
        })
        Object.keys(itemForms).forEach(id => {
            if (!ids.includes(Number(id))) delete itemForms[id]
        })
    },
    { immediate: true, deep: true }
)

const errorMsg = ref('')
function onError(errors) {
    errorMsg.value = Object.values(errors ?? {})[0] ?? 'Terjadi kesalahan.'
}
const reload = { preserveScroll: true, only: ['tour'], onError }

// ── Helpers status ──────────────────────────────────────────────────────────────
function isApproved(inv) { return !!inv.approved_at }
function stage(inv) {
    if (inv.approved_at) return 'approved'
    return Number(inv.baseline_total) > 0 ? 'detail' : 'baseline'
}
const STAGE_BADGE = {
    baseline: { label: 'Tahap 1 · Patokan', cls: 'bg-blue-100 text-blue-700' },
    detail:   { label: 'Tahap 2 · Rincian', cls: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Sudah di Keuangan', cls: 'bg-green-100 text-green-700' },
}

function invProfit(inv) {
    return (inv.items ?? []).reduce((s, i) => s + (Number(i.line_sell) - Number(i.line_cost)), 0)
}
function invMargin(inv) {
    const sell = Number(inv.total) || 0
    return sell > 0 ? Math.round((invProfit(inv) / sell) * 1000) / 10 : 0
}
function baselineMatched(inv) {
    return Math.abs(Number(inv.total) - Number(inv.baseline_total)) < 0.01
}
function baselineDiff(inv) {
    return Number(inv.total) - Number(inv.baseline_total)
}

// ── Aksi invoice ──────────────────────────────────────────────────────────────
function createInvoice() {
    errorMsg.value = ''
    router.post(route('invoices.store', props.tour.id), {}, reload)
}
function lockBaseline(inv) {
    errorMsg.value = ''
    router.patch(route('invoices.baseline', inv.id), {}, reload)
}
async function approve(inv) {
    if (await confirm({
        title: 'Setujui invoice ini?',
        description: 'Setelah disetujui, invoice masuk ke Keuangan dan tidak bisa diubah lagi.',
        confirmLabel: 'Setujui',
        destructive: false,
    })) {
        errorMsg.value = ''
        router.post(route('invoices.approve', inv.id), {}, reload)
    }
}
async function deleteInvoice(inv) {
    if (await confirm({ title: `Hapus invoice ${inv.number}?`, confirmLabel: 'Hapus' })) {
        errorMsg.value = ''
        router.delete(route('invoices.destroy', inv.id), reload)
    }
}

// ── Aksi baris item ───────────────────────────────────────────────────────────
function saveItem(itemId) {
    errorMsg.value = ''
    router.patch(route('invoice-items.update', itemId), itemForms[itemId], reload)
}
async function deleteItem(itemId) {
    if (await confirm({ title: 'Hapus item ini?', confirmLabel: 'Hapus' })) {
        errorMsg.value = ''
        router.delete(route('invoice-items.destroy', itemId), reload)
    }
}

// ── Dialog pilih produk ────────────────────────────────────────────────────────
const addDialogOpen   = ref(false)
const addTargetInvoice = ref(null)
const productSearch    = ref('')
const addingProductId  = ref(null)

function openAddDialog(inv) {
    addTargetInvoice.value = inv
    productSearch.value    = ''
    addDialogOpen.value    = true
}

const filteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase()
    if (!q) return props.products
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) || p.type.toLowerCase().includes(q)
    )
})
const productsByType = computed(() => {
    const groups = {}
    filteredProducts.value.forEach(p => {
        if (!groups[p.type]) groups[p.type] = []
        groups[p.type].push(p)
    })
    return groups
})

function addProduct(product) {
    addingProductId.value = product.id
    router.post(route('invoice-items.store', addTargetInvoice.value.id), { product_id: product.id }, {
        preserveScroll: true,
        only: ['tour'],
        onError,
        onSuccess: () => {
            addingProductId.value = null
            addDialogOpen.value   = false
            productSearch.value   = ''
        },
        onFinish: () => { addingProductId.value = null },
    })
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 class="font-semibold">Invoice</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Buat patokan → rinci item → setujui untuk kirim ke Keuangan.
                </p>
            </div>
            <Button size="sm" @click="createInvoice">+ Buat Invoice</Button>
        </div>

        <div v-if="errorMsg" class="mx-5 mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {{ errorMsg }}
        </div>

        <div v-if="invoices.length === 0" class="px-5 py-10 text-center text-sm text-muted-foreground">
            Belum ada invoice. Klik "+ Buat Invoice" untuk mulai.
        </div>

        <div v-else class="divide-y">
            <div v-for="inv in invoices" :key="inv.id" class="px-5 py-4 space-y-3">
                <!-- Header invoice -->
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold">{{ inv.number }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="STAGE_BADGE[stage(inv)].cls">
                            {{ STAGE_BADGE[stage(inv)].label }}
                        </span>
                        <span v-if="inv.pax" class="text-xs text-muted-foreground">{{ inv.pax }} pax</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="route('invoices.preview', inv.id)" target="_blank">
                            <Button size="sm" variant="outline">👁 PDF</Button>
                        </a>
                        <a :href="route('invoices.download', inv.id)">
                            <Button size="sm" variant="outline">⬇ Unduh</Button>
                        </a>
                        <Button v-if="!isApproved(inv)" size="sm" variant="ghost"
                            class="text-destructive hover:text-destructive" @click="deleteInvoice(inv)">Hapus</Button>
                    </div>
                </div>

                <!-- Tabel item -->
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30 text-muted-foreground text-xs uppercase">
                                <th class="px-3 py-2 text-left">Deskripsi</th>
                                <th class="px-3 py-2 text-center w-16">Qty</th>
                                <th class="px-3 py-2 text-center w-16">Mlm</th>
                                <th class="px-3 py-2 text-right w-28">Cost/unit</th>
                                <th class="px-3 py-2 text-right w-28">Sell/unit</th>
                                <th class="px-3 py-2 text-right w-28">Total Jual</th>
                                <th v-if="!isApproved(inv)" class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="(inv.items ?? []).length === 0">
                                <td :colspan="isApproved(inv) ? 6 : 7" class="text-center py-6 text-muted-foreground">
                                    Belum ada item.
                                </td>
                            </tr>
                            <!-- Read-only bila sudah disetujui -->
                            <template v-if="isApproved(inv)">
                                <tr v-for="item in inv.items" :key="item.id" class="border-b last:border-0">
                                    <td class="px-3 py-1.5">
                                        {{ item.description || item.product?.name }}
                                        <span class="block text-xs text-muted-foreground">{{ TYPE_LABELS[item.product_type] ?? item.product_type }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">{{ item.qty }}</td>
                                    <td class="px-3 py-1.5 text-center">{{ item.nights }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_cost) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_sell) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-medium">{{ fmtRp(item.line_sell) }}</td>
                                </tr>
                            </template>
                            <!-- Editable -->
                            <template v-else>
                                <tr v-for="item in inv.items" :key="item.id" class="border-b last:border-0 hover:bg-muted/20">
                                    <td class="px-3 py-1.5">
                                        <input type="text" v-model="itemForms[item.id].description" @blur="saveItem(item.id)"
                                            class="border rounded px-2 py-0.5 text-sm w-full focus:outline-none focus:ring-1 focus:ring-primary" />
                                        <span class="text-xs text-muted-foreground">{{ TYPE_LABELS[item.product_type] ?? item.product_type }}</span>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" v-model="itemForms[item.id].qty" @change="saveItem(item.id)" min="1"
                                            class="w-14 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" v-model="itemForms[item.id].nights" @change="saveItem(item.id)" min="1"
                                            class="w-14 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" v-model="itemForms[item.id].unit_cost" @change="saveItem(item.id)" min="0"
                                            class="w-28 border rounded px-2 py-0.5 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" v-model="itemForms[item.id].unit_sell" @change="saveItem(item.id)" min="0"
                                            class="w-28 border rounded px-2 py-0.5 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-mono text-sm font-medium">{{ fmtRp(item.line_sell) }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <button type="button" @click="deleteItem(item.id)"
                                            class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus item">✕</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="!isApproved(inv)">
                    <Button size="sm" variant="outline" @click="openAddDialog(inv)">+ Tambah Produk</Button>
                </div>

                <!-- Ringkasan patokan / total / profit -->
                <div class="flex flex-wrap items-end justify-between gap-3 rounded-md bg-muted/30 px-4 py-3">
                    <div class="text-sm space-y-1">
                        <div v-if="Number(inv.baseline_total) > 0" class="flex items-center gap-2">
                            <span class="text-muted-foreground">Patokan:</span>
                            <span class="font-mono font-medium">{{ fmtRp(inv.baseline_total) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Total Rincian:</span>
                            <span class="font-mono font-medium">{{ fmtRp(inv.total) }}</span>
                            <span v-if="stage(inv) === 'detail' && !baselineMatched(inv)"
                                class="text-xs font-medium" :class="baselineDiff(inv) > 0 ? 'text-red-600' : 'text-amber-600'">
                                ({{ baselineDiff(inv) > 0 ? 'lebih' : 'kurang' }} {{ fmtRp(Math.abs(baselineDiff(inv))) }})
                            </span>
                            <span v-else-if="stage(inv) === 'detail'" class="text-xs font-medium text-green-600">✓ sesuai patokan</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-muted-foreground">Profit:</span>
                            <span class="font-mono" :class="invProfit(inv) >= 0 ? 'text-green-700' : 'text-red-600'">
                                {{ fmtRp(invProfit(inv)) }} ({{ invMargin(inv) }}%)
                            </span>
                        </div>
                    </div>

                    <div v-if="!isApproved(inv)" class="flex items-center gap-2">
                        <Button v-if="stage(inv) === 'baseline'" size="sm"
                            :disabled="Number(inv.total) <= 0" @click="lockBaseline(inv)">
                            Kunci Patokan
                        </Button>
                        <template v-else>
                            <Button size="sm" variant="outline" @click="lockBaseline(inv)" title="Set patokan = total rincian sekarang">
                                Samakan Patokan
                            </Button>
                            <Button size="sm" :disabled="!baselineMatched(inv) || Number(inv.total) <= 0" @click="approve(inv)">
                                Setujui
                            </Button>
                        </template>
                    </div>
                    <div v-else class="text-xs text-green-700 font-medium">
                        ✓ Disetujui — dikelola di Keuangan
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog pilih produk -->
        <Dialog v-model:open="addDialogOpen">
            <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Pilih Produk</DialogTitle>
                </DialogHeader>
                <Input v-model="productSearch" placeholder="Cari produk..." class="mt-1" autofocus />
                <div class="overflow-y-auto flex-1 mt-2 space-y-4 pr-1">
                    <div v-for="(items, type) in productsByType" :key="type">
                        <p class="text-xs font-semibold uppercase text-muted-foreground mb-1 sticky top-0 bg-white py-1">
                            {{ TYPE_LABELS[type] ?? type }}
                        </p>
                        <div class="space-y-1">
                            <button
                                v-for="p in items"
                                :key="p.id"
                                type="button"
                                :disabled="addingProductId === p.id"
                                @click="addProduct(p)"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-muted text-left text-sm transition-colors disabled:opacity-50"
                            >
                                <span class="font-medium">{{ p.name }}</span>
                                <span class="text-muted-foreground text-xs ml-4 shrink-0">
                                    Sell: {{ fmtNum(p.sell) }} {{ p.currency }} / {{ p.unit }}
                                </span>
                            </button>
                        </div>
                    </div>
                    <p v-if="Object.keys(productsByType).length === 0" class="text-sm text-muted-foreground text-center py-8">
                        Produk tidak ditemukan.
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
