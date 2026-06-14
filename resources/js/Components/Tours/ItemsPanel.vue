<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtNum, fmtRp } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({ tour: Object, products: Array })

const itemForms = reactive({})

watch(
    () => props.tour.items,
    (items) => {
        items.forEach(item => {
            itemForms[item.id] = {
                qty:         item.qty,
                nights:      item.nights,
                day_number:  item.day_number ?? '',
                description: item.description ?? '',
                unit_cost:   item.unit_cost,
                unit_sell:   item.unit_sell,
            }
        })
        Object.keys(itemForms).forEach(id => {
            if (!items.find(i => i.id == id)) delete itemForms[id]
        })
    },
    { immediate: true }
)

function saveItem(itemId) {
    router.patch(route('tour-items.update', itemId), itemForms[itemId], {
        preserveScroll: true, only: ['tour'],
    })
}

async function deleteItem(itemId) {
    if (await confirm({ title: 'Hapus item ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('tour-items.destroy', itemId), {
            preserveScroll: true, only: ['tour'],
        })
    }
}

const productSearch   = ref('')
const addDialogOpen   = ref(false)
const addingProductId = ref(null)

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
    router.post(route('tour-items.store', props.tour.id), { product_id: product.id }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            addingProductId.value = null
            addDialogOpen.value   = false
            productSearch.value   = ''
        },
        onError: () => { addingProductId.value = null },
    })
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 class="font-semibold">Item Produk</h3>
            <Dialog v-model:open="addDialogOpen">
                <DialogTrigger as-child>
                    <Button size="sm">+ Tambah Produk</Button>
                </DialogTrigger>
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

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/30 text-muted-foreground text-xs uppercase">
                        <th class="px-3 py-2 text-left w-10">Hari</th>
                        <th class="px-3 py-2 text-left">Deskripsi</th>
                        <th class="px-3 py-2 text-center w-16">Qty</th>
                        <th class="px-3 py-2 text-center w-16">Mlm</th>
                        <th class="px-3 py-2 text-right w-28">Cost/unit</th>
                        <th class="px-3 py-2 text-right w-28">Sell/unit</th>
                        <th class="px-3 py-2 text-right w-28">Total Jual</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="tour.items.length === 0">
                        <td colspan="8" class="text-center py-10 text-muted-foreground">
                            Belum ada item. Klik "+ Tambah Produk" untuk mulai.
                        </td>
                    </tr>
                    <tr v-for="item in tour.items" :key="item.id" class="border-b last:border-0 hover:bg-muted/20">
                        <td class="px-3 py-1.5">
                            <input type="number" v-model="itemForms[item.id].day_number" @change="saveItem(item.id)"
                                min="1" placeholder="—"
                                class="w-12 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                        </td>
                        <td class="px-3 py-1.5">
                            <div class="flex flex-col gap-0.5">
                                <input type="text" v-model="itemForms[item.id].description" @blur="saveItem(item.id)"
                                    class="border rounded px-2 py-0.5 text-sm w-full focus:outline-none focus:ring-1 focus:ring-primary" />
                                <span class="text-xs text-muted-foreground">{{ TYPE_LABELS[item.product_type] ?? item.product_type }}</span>
                            </div>
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
                                class="text-muted-foreground hover:text-destructive transition-colors"
                                title="Hapus item">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
