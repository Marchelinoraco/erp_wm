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
    product_id: null, label: '', qty: 1, nights: 1, unit_sell: '', notes: '',
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
}

function selectQProduct(p) {
    qItemForm.product_id = p.id
    qItemForm.label      = p.name
    qItemForm.unit_sell  = Number(p.sell)
    qItemStep.value      = 'form'
}

function useManualQItem() {
    qItemForm.product_id = null
    qItemForm.label      = ''
    qItemForm.unit_sell  = ''
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
        },
    })
}

function startEditQItem(qi) {
    editingQItemId.value = qi.id
    qItemForm.product_id = qi.product_id
    qItemForm.label      = qi.label
    qItemForm.qty        = qi.qty
    qItemForm.nights     = qi.nights
    qItemForm.unit_sell  = Number(qi.unit_sell)
    qItemForm.notes      = qi.notes ?? ''
}

function cancelEditQItem() {
    editingQItemId.value = null
    qItemForm.reset()
    qItemForm.qty    = 1
    qItemForm.nights = 1
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
                                <p v-if="qItemForm.errors.label" class="text-xs text-destructive">{{ qItemForm.errors.label }}</p>
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
                                <p v-if="qItemForm.errors.unit_sell" class="text-xs text-destructive">{{ qItemForm.errors.unit_sell }}</p>
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
                            <div class="text-sm font-medium leading-tight">{{ qi.label }}</div>
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

    <!-- Dialog: Tambah Produk Penawaran -->
    <Dialog :open="qItemDialogOpen" @update:open="v => { if (!v) { qItemDialogOpen = false; qItemForm.reset(); qItemForm.qty = 1; qItemForm.nights = 1 } }">
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
                        @click="qItemStep = 'pick'; qItemForm.reset(); qItemForm.qty = 1; qItemForm.nights = 1">Ganti</button>
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
