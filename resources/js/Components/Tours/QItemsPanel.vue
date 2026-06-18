<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({ tour: Object, products: Array })

const QITEM_STATUS = {
    proposed: { label: 'Diajukan',  cls: 'bg-blue-100 text-blue-700' },
    approved: { label: 'Disetujui', cls: 'bg-green-100 text-green-700' },
    rejected: { label: 'Ditolak',   cls: 'bg-red-100 text-red-700' },
}

const qItemStep       = ref('pick')
const qItemDialogOpen = ref(false)
const qItemSearch     = ref('')
const editingQItemId  = ref(null)

const qItemForm = useForm({
    product_id: null, label: '', qty: 1, nights: 1, pax_mode: 'per_pax', unit_sell: '', notes: '',
})

const filteredQProducts = computed(() => {
    const q = qItemSearch.value.toLowerCase()
    if (!q) return props.products
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) || p.type.toLowerCase().includes(q)
    )
})

const qProductsByType = computed(() => {
    const groups = {}
    filteredQProducts.value.forEach(p => {
        if (!groups[p.type]) groups[p.type] = []
        groups[p.type].push(p)
    })
    return groups
})

function openQItemDialog() {
    qItemStep.value       = 'pick'
    qItemSearch.value     = ''
    qItemDialogOpen.value = true
    qItemForm.reset()
    qItemForm.qty    = 1
    qItemForm.nights = 1
    qItemForm.pax_mode = 'per_pax'
}

function selectQProduct(p) {
    qItemForm.product_id = p.id
    qItemForm.label      = p.name
    qItemForm.unit_sell  = Number(p.sell)
    qItemForm.pax_mode   = ['transport', 'guide'].includes(p.type) ? 'shared' : 'per_pax'
    qItemStep.value      = 'form'
}

function useManualQItem() {
    qItemForm.product_id = null
    qItemForm.label      = ''
    qItemForm.unit_sell  = ''
    qItemForm.pax_mode   = 'per_pax'
    qItemStep.value      = 'form'
}

function submitQItem() {
    qItemForm.post(route('quotation-items.store', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            qItemDialogOpen.value = false
            qItemForm.reset()
            qItemForm.qty    = 1
            qItemForm.nights = 1
            qItemForm.pax_mode = 'per_pax'
        },
    })
}

function startEditQItem(qi) {
    editingQItemId.value = qi.id
    qItemForm.product_id = qi.product_id
    qItemForm.label      = qi.label
    qItemForm.qty        = qi.qty
    qItemForm.nights     = qi.nights
    qItemForm.pax_mode   = qi.pax_mode ?? 'per_pax'
    qItemForm.unit_sell  = Number(qi.unit_sell)
    qItemForm.notes      = qi.notes ?? ''
}

function cancelEditQItem() {
    editingQItemId.value = null
    qItemForm.reset()
    qItemForm.qty    = 1
    qItemForm.nights = 1
    qItemForm.pax_mode = 'per_pax'
}

function saveQItem(id) {
    qItemForm.patch(route('quotation-items.update', id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => cancelEditQItem(),
    })
}

function setQItemStatus(id, status) {
    router.patch(route('quotation-items.update', id), { status }, {
        preserveScroll: true, only: ['tour'],
    })
}

function setPaxMode(qi) {
    router.patch(route('quotation-items.update', qi.id), {
        pax_mode: qi.pax_mode === 'shared' ? 'per_pax' : 'shared',
    }, { preserveScroll: true, only: ['tour'] })
}

async function deleteQItem(id) {
    if (await confirm({ title: 'Hapus item penawaran?', confirmLabel: 'Hapus' })) {
        router.delete(route('quotation-items.destroy', id), {
            preserveScroll: true, only: ['tour'],
        })
    }
}

const qItemTotal = computed(() =>
    (props.tour.quotation_items ?? [])
        .filter(qi => qi.status === 'approved')
        .reduce((s, qi) => s + qi.qty * qi.nights * Number(qi.unit_sell), 0)
)

// ── Kalkulator Harga per Pax ──────────────────────────────────────────────
const compareMode = ref('pax') // 'pax' = jumlah pax · 'vehicle' = per kendaraan
const markup = ref(Number(props.tour.default_markup) || 0)

const lineOf = (i) => i.qty * i.nights * Number(i.unit_sell)
const calcItems = computed(() => (props.tour.quotation_items ?? []).filter(qi => qi.status !== 'rejected'))
const sharedTotal = computed(() => calcItems.value.filter(i => i.pax_mode === 'shared').reduce((s, i) => s + lineOf(i), 0))
const perPaxTotal = computed(() => calcItems.value.filter(i => i.pax_mode === 'per_pax').reduce((s, i) => s + lineOf(i), 0))

const withMarkup = (raw) => Math.ceil(raw * (1 + (Number(markup.value) || 0) / 100) / 1000) * 1000

// Mode jumlah pax
const paxCounts = ref([2, 4, 6])
function addPax() { paxCounts.value.push(2) }
function removePax(i) { paxCounts.value.splice(i, 1) }
function perPaxPrice(n) {
    return withMarkup((n > 0 ? sharedTotal.value / n : 0) + perPaxTotal.value)
}

// Mode per kendaraan — tiap mobil: biaya + muat pax. Item transport katalog
// dikecualikan dari "shared" agar tak dobel (biaya mobil dari sini).
const transportIds = computed(() => new Set(props.products.filter(p => p.type === 'transport').map(p => p.id)))
const sharedNonCarTotal = computed(() =>
    calcItems.value.filter(i => i.pax_mode === 'shared' && !transportIds.value.has(i.product_id)).reduce((s, i) => s + lineOf(i), 0)
)
const transportProducts = computed(() => props.products.filter(p => p.type === 'transport'))
const vehicles = ref([
    { label: 'Toyota Avanza', cost: null, pax: 4 },
    { label: 'Innova Reborn', cost: null, pax: 6 },
    { label: 'Hiace', cost: null, pax: 12 },
])
function addVehicle() { vehicles.value.push({ label: '', cost: null, pax: 4 }) }
function removeVehicle(i) { vehicles.value.splice(i, 1) }
function pickVehicle(i, e) {
    const p = props.products.find(x => x.id === Number(e.target.value))
    if (p) { vehicles.value[i].label = p.name; vehicles.value[i].cost = Number(p.sell) }
    e.target.value = ''
}
function vehiclePerPax(v) {
    const n = Number(v.pax) || 1
    return withMarkup(((Number(v.cost) || 0) + sharedNonCarTotal.value) / n + perPaxTotal.value)
}

const applying = ref(false)
function applyToQuotation() {
    let tiers, base
    if (compareMode.value === 'vehicle') {
        const list = vehicles.value.filter(v => Number(v.pax) > 0)
        if (!list.length) return
        tiers = list.map((v, i) => ({ id: `v${i + 1}_${v.pax}`, pax: Number(v.pax), vehicle: v.label, group_cost: null, label: `Min ${v.pax} pax`, note: v.label }))
        base = { label: 'Harga per Pax', enabled: true, prices: {} }
        list.forEach((v, i) => { base.prices[tiers[i].id] = vehiclePerPax(v) })
    } else {
        const counts = paxCounts.value.filter(p => p > 0)
        if (!counts.length) return
        tiers = counts.map((p, i) => ({ id: `t${i + 1}_${p}`, pax: p, vehicle: '', group_cost: null, label: `Min ${p} pax`, note: '' }))
        base = { label: 'Harga per Pax', enabled: true, prices: {} }
        tiers.forEach(t => { base.prices[t.id] = perPaxPrice(t.pax) })
    }
    const pricing = { mode: 'manual', tiers, base, hotels: [], optionals: props.tour.pricing?.optionals ?? [] }

    applying.value = true
    router.patch(route('tours.update', props.tour.id), { pricing }, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
        onFinish: () => { applying.value = false },
    })
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 class="font-semibold">Produk Penawaran</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Pilih dari katalog → tandai disetujui/ditolak → saat Confirmed otomatis jadi biaya tour.</p>
            </div>
            <Button size="sm" variant="outline" @click="openQItemDialog">+ Tambah Produk</Button>
        </div>

        <div v-if="!tour.quotation_items?.length" class="px-5 py-10 text-center text-sm text-muted-foreground">
            Belum ada produk penawaran. Klik "+ Tambah Produk" untuk memilih dari katalog atau tambah manual.
        </div>

        <div v-else class="divide-y">
            <div v-for="qi in tour.quotation_items" :key="qi.id">
                <!-- Edit mode -->
                <template v-if="editingQItemId === qi.id">
                    <div class="px-5 py-4 bg-muted/20">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs">Label / Nama</Label>
                                <Input v-model="qItemForm.label" class="h-8 text-sm" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Qty</Label>
                                <Input type="number" v-model.number="qItemForm.qty" min="1" class="h-8 text-sm text-right" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Mlm/Unit</Label>
                                <Input type="number" v-model.number="qItemForm.nights" min="1" class="h-8 text-sm text-right" />
                            </div>
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs">Harga Jual / unit</Label>
                                <Input type="number" v-model.number="qItemForm.unit_sell" min="0" step="1000" class="h-8 text-sm text-right" />
                            </div>
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs">Perhitungan Pax</Label>
                                <div class="flex rounded-md border p-0.5 h-8">
                                    <button type="button" class="flex-1 rounded text-xs transition-colors" :class="qItemForm.pax_mode === 'per_pax' ? 'bg-teal-600 text-white' : 'text-gray-500'" @click="qItemForm.pax_mode = 'per_pax'">Per Pax</button>
                                    <button type="button" class="flex-1 rounded text-xs transition-colors" :class="qItemForm.pax_mode === 'shared' ? 'bg-purple-600 text-white' : 'text-gray-500'" @click="qItemForm.pax_mode = 'shared'">Dibagi pax</button>
                                </div>
                            </div>
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs">Catatan</Label>
                                <Input v-model="qItemForm.notes" class="h-8 text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-3">
                            <Button type="button" size="sm" variant="outline" @click="cancelEditQItem" class="h-7 text-xs">Batal</Button>
                            <Button type="button" size="sm" @click="saveQItem(qi.id)" :disabled="qItemForm.processing" class="h-7 text-xs">Simpan</Button>
                        </div>
                    </div>
                </template>

                <!-- View mode -->
                <template v-else>
                    <div class="px-5 py-3 flex items-center gap-3 flex-wrap"
                        :class="qi.status === 'rejected' ? 'opacity-50' : ''">
                        <span :class="['text-[10px] px-2 py-0.5 rounded-full font-semibold shrink-0', QITEM_STATUS[qi.status]?.cls]">
                            {{ QITEM_STATUS[qi.status]?.label }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium leading-tight">{{ qi.label }}</span>
                                <button type="button" @click="setPaxMode(qi)" :title="qi.pax_mode === 'shared' ? 'Biaya grup, dibagi jumlah pax' : 'Biaya per orang'"
                                    class="text-[10px] px-1.5 py-0.5 rounded border transition-colors"
                                    :class="qi.pax_mode === 'shared' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-teal-50 text-teal-700 border-teal-200'">
                                    {{ qi.pax_mode === 'shared' ? '÷ Dibagi pax' : '∕ Per pax' }}
                                </button>
                            </div>
                            <div class="text-xs text-muted-foreground mt-0.5">
                                {{ qi.qty }} × {{ qi.nights }} mlm × {{ fmtRp(qi.unit_sell) }}
                                = <span class="font-medium text-foreground font-mono">{{ fmtRp(qi.qty * qi.nights * Number(qi.unit_sell)) }}</span>
                            </div>
                            <div v-if="qi.notes" class="text-xs text-muted-foreground italic">{{ qi.notes }}</div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 flex-wrap">
                            <button v-if="qi.status !== 'approved'" type="button"
                                class="text-xs px-2 py-1 rounded border border-green-200 text-green-700 hover:bg-green-50 transition-colors"
                                @click="setQItemStatus(qi.id, 'approved')">✓ Setujui</button>
                            <button v-if="qi.status !== 'rejected'" type="button"
                                class="text-xs px-2 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50 transition-colors"
                                @click="setQItemStatus(qi.id, 'rejected')">✕ Tolak</button>
                            <button v-if="qi.status !== 'proposed'" type="button"
                                class="text-xs px-2 py-1 rounded border text-muted-foreground hover:bg-muted transition-colors"
                                title="Reset ke Diajukan"
                                @click="setQItemStatus(qi.id, 'proposed')">↺</button>
                            <button type="button"
                                class="text-xs px-2 py-1 rounded border hover:bg-muted transition-colors"
                                @click="startEditQItem(qi)">Edit</button>
                            <button type="button"
                                class="text-xs px-2 py-1 rounded border border-red-200 text-red-700 hover:bg-red-50 transition-colors"
                                @click="deleteQItem(qi.id)">Hapus</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="qItemTotal > 0" class="border-t px-5 py-3 bg-green-50/50 flex items-center justify-between">
            <span class="text-xs text-muted-foreground">Total item disetujui:</span>
            <span class="font-mono font-semibold text-green-800">{{ fmtRp(qItemTotal) }}</span>
        </div>
    </div>

    <!-- Kalkulator Harga per Pax -->
    <div v-if="calcItems.length" class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b">
            <h3 class="font-semibold">Kalkulator Harga per Pax</h3>
            <p class="text-xs text-muted-foreground mt-0.5">
                <b class="text-teal-700">Per pax</b> = tetap; <b class="text-purple-700">Dibagi pax</b> = total grup ÷ jumlah pax.
            </p>
        </div>
        <div class="p-5 space-y-4">
            <!-- Mode & markup -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-md border p-0.5 text-sm">
                    <button type="button" @click="compareMode = 'pax'" :class="compareMode === 'pax' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" class="px-3 py-1 rounded transition-colors">Per Jumlah Pax</button>
                    <button type="button" @click="compareMode = 'vehicle'" :class="compareMode === 'vehicle' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" class="px-3 py-1 rounded transition-colors">Per Kendaraan</button>
                </div>
                <div class="flex items-center gap-2">
                    <Label class="text-xs">Markup %</Label>
                    <Input type="number" v-model.number="markup" min="0" step="any" class="h-8 w-20 text-sm" />
                </div>
            </div>

            <!-- Subtotal komponen -->
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-md border p-3">
                    <p class="text-xs text-purple-700">{{ compareMode === 'vehicle' ? 'Biaya Dibagi (selain mobil)' : 'Biaya Dibagi (grup)' }}</p>
                    <p class="font-mono font-semibold">{{ fmtRp(compareMode === 'vehicle' ? sharedNonCarTotal : sharedTotal) }}</p>
                    <p class="text-[11px] text-muted-foreground">÷ jumlah pax</p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-xs text-teal-700">Biaya per Pax</p>
                    <p class="font-mono font-semibold">{{ fmtRp(perPaxTotal) }}</p>
                    <p class="text-[11px] text-muted-foreground">tetap per orang</p>
                </div>
            </div>

            <!-- ══ Mode: Per Jumlah Pax ══ -->
            <template v-if="compareMode === 'pax'">
                <div class="space-y-1">
                    <Label class="text-xs">Bandingkan jumlah pax</Label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div v-for="(p, i) in paxCounts" :key="i" class="flex items-center">
                            <Input type="number" v-model.number="paxCounts[i]" min="1" class="h-8 w-16 text-sm text-center" />
                            <button type="button" class="text-gray-300 hover:text-red-500 ml-0.5" @click="removePax(i)">×</button>
                        </div>
                        <Button type="button" size="sm" variant="outline" class="h-8" @click="addPax">+ pax</Button>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr><th v-for="p in paxCounts.filter(x => x > 0)" :key="p" class="px-3 py-2 text-center font-medium">{{ p }} pax</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td v-for="p in paxCounts.filter(x => x > 0)" :key="p" class="px-3 py-3 text-center">
                                    <span class="font-mono font-bold text-base text-gray-900">{{ fmtRp(perPaxPrice(p)) }}</span>
                                    <span class="block text-[10px] text-muted-foreground">/pax</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-muted-foreground">Harga/pax = (biaya dibagi ÷ pax + biaya per pax) × (1 + markup). Bulat ke atas Rp 1.000.</p>
            </template>

            <!-- ══ Mode: Per Kendaraan ══ -->
            <template v-else>
                <div class="space-y-2">
                    <Label class="text-xs">Opsi Kendaraan (nama · biaya total · muat pax)</Label>
                    <p class="text-[11px] text-muted-foreground -mt-1">Jangan tambahkan mobil sebagai item di atas — masukkan di sini. Item "Dibagi pax" lain (guide/boat) tetap dihitung.</p>
                    <div v-for="(v, i) in vehicles" :key="i" class="grid grid-cols-12 gap-2 items-center">
                        <Input v-model="v.label" placeholder="Nama mobil" class="h-8 text-sm col-span-4" />
                        <Input type="number" v-model.number="v.cost" placeholder="Biaya total" min="0" step="any" class="h-8 text-sm col-span-3" />
                        <Input type="number" v-model.number="v.pax" placeholder="Muat" min="1" class="h-8 text-sm text-center col-span-2" />
                        <select @change="(e) => pickVehicle(i, e)" class="h-8 text-xs border rounded-md col-span-2" title="Ambil dari katalog">
                            <option value="">katalog…</option>
                            <option v-for="p in transportProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <button type="button" class="text-gray-300 hover:text-red-500 col-span-1" @click="removeVehicle(i)">×</button>
                    </div>
                    <Button type="button" size="sm" variant="outline" class="h-8" @click="addVehicle">+ Kendaraan</Button>
                </div>
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr><th v-for="(v, i) in vehicles.filter(x => Number(x.pax) > 0)" :key="i" class="px-3 py-2 text-center font-medium">{{ v.label || 'Mobil' }}<span class="block text-[10px] text-muted-foreground font-normal">{{ v.pax }} pax</span></th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td v-for="(v, i) in vehicles.filter(x => Number(x.pax) > 0)" :key="i" class="px-3 py-3 text-center">
                                    <span class="font-mono font-bold text-base text-gray-900">{{ fmtRp(vehiclePerPax(v)) }}</span>
                                    <span class="block text-[10px] text-muted-foreground">/pax</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-muted-foreground">Harga/pax = ((biaya mobil + biaya dibagi) ÷ muat pax + biaya per pax) × (1 + markup). Bulat ke atas Rp 1.000.</p>
            </template>

            <div class="flex justify-end">
                <Button size="sm" :disabled="applying" @click="applyToQuotation">
                    {{ applying ? 'Menerapkan…' : 'Terapkan ke Quotation →' }}
                </Button>
            </div>
        </div>
    </div>

    <!-- Dialog: Tambah Produk Penawaran -->
    <Dialog :open="qItemDialogOpen" @update:open="v => { if (!v) { qItemDialogOpen = false; qItemForm.reset(); qItemForm.qty = 1; qItemForm.nights = 1; qItemForm.pax_mode = 'per_pax' } }">
        <DialogContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ qItemStep === 'pick' ? 'Pilih Produk' : 'Detail Item Penawaran' }}</DialogTitle>
            </DialogHeader>

            <!-- Step 1: Pick product -->
            <template v-if="qItemStep === 'pick'">
                <Input v-model="qItemSearch" placeholder="Cari produk..." class="mt-1" />
                <div class="max-h-72 overflow-y-auto mt-2 space-y-3 pr-1">
                    <div v-for="(prods, type) in qProductsByType" :key="type">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground px-1 mb-1">
                            {{ TYPE_LABELS[type] ?? type }}
                        </p>
                        <div class="space-y-0.5">
                            <button v-for="p in prods" :key="p.id" type="button"
                                class="w-full text-left flex items-center justify-between px-3 py-2 rounded hover:bg-muted transition-colors"
                                @click="selectQProduct(p)">
                                <span class="text-sm font-medium">{{ p.name }}</span>
                                <span class="text-xs text-muted-foreground font-mono">{{ fmtRp(p.sell) }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="!Object.keys(qProductsByType).length" class="text-center text-sm text-muted-foreground py-6">
                        Tidak ada produk yang cocok.
                    </div>
                </div>
                <div class="pt-2 border-t mt-2">
                    <button type="button"
                        class="w-full text-left px-3 py-2 rounded text-sm text-muted-foreground hover:bg-muted transition-colors"
                        @click="useManualQItem">
                        + Tambah item manual (tanpa produk katalog)
                    </button>
                </div>
            </template>

            <!-- Step 2: Fill details -->
            <template v-else>
                <div v-if="qItemForm.product_id" class="flex items-center justify-between text-sm mb-2">
                    <span class="text-muted-foreground">Produk:
                        <span class="font-medium text-foreground">{{ products.find(p => p.id === qItemForm.product_id)?.name }}</span>
                    </span>
                    <button type="button" class="text-xs underline text-muted-foreground"
                        @click="qItemStep = 'pick'; qItemForm.reset(); qItemForm.qty = 1; qItemForm.nights = 1; qItemForm.pax_mode = 'per_pax'">Ganti</button>
                </div>
                <form @submit.prevent="submitQItem" class="space-y-4 mt-1">
                    <div class="space-y-1.5">
                        <Label>Label / Nama <span class="text-destructive">*</span></Label>
                        <Input v-model="qItemForm.label" placeholder="Nama tampil di penawaran" autofocus />
                        <p v-if="qItemForm.errors.label" class="text-xs text-destructive">{{ qItemForm.errors.label }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label>Qty</Label>
                            <Input type="number" v-model.number="qItemForm.qty" min="1" class="text-right" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Mlm / Unit</Label>
                            <Input type="number" v-model.number="qItemForm.nights" min="1" class="text-right" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Harga Jual / unit <span class="text-destructive">*</span></Label>
                            <Input type="number" v-model.number="qItemForm.unit_sell" min="0" step="1000" class="text-right" />
                            <p v-if="qItemForm.errors.unit_sell" class="text-xs text-destructive">{{ qItemForm.errors.unit_sell }}</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Perhitungan Pax</Label>
                        <div class="flex rounded-md border p-0.5">
                            <button type="button" class="flex-1 py-1.5 rounded text-sm transition-colors" :class="qItemForm.pax_mode === 'per_pax' ? 'bg-teal-600 text-white' : 'text-gray-500'" @click="qItemForm.pax_mode = 'per_pax'">Per Pax (tetap)</button>
                            <button type="button" class="flex-1 py-1.5 rounded text-sm transition-colors" :class="qItemForm.pax_mode === 'shared' ? 'bg-purple-600 text-white' : 'text-gray-500'" @click="qItemForm.pax_mode = 'shared'">Dibagi pax (grup)</button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Transport/guide biasanya "Dibagi pax"; hotel-per-orang/makan/tiket "Per Pax".</p>
                    </div>
                    <div v-if="qItemForm.qty && qItemForm.nights && qItemForm.unit_sell" class="text-sm text-right text-muted-foreground">
                        Subtotal: <span class="font-mono font-semibold text-foreground">{{ fmtRp(qItemForm.qty * qItemForm.nights * qItemForm.unit_sell) }}</span>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan <span class="text-xs text-muted-foreground font-normal">(opsional)</span></Label>
                        <Input v-model="qItemForm.notes" placeholder="Keterangan tambahan..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="qItemDialogOpen = false; qItemForm.reset()">Batal</Button>
                        <Button type="submit" :disabled="qItemForm.processing">Tambahkan</Button>
                    </div>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
