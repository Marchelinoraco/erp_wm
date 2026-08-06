<script setup>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({
    invoice: { type: Object, required: true },
})

// ── State per-item, di-scope ke SATU invoice ini saja ───────────────────────
const itemForms = reactive({})
const dirtyIds  = ref(new Set())
const saveState = ref('idle')
const errorMsg  = ref('')

watch(
    () => props.invoice.items,
    (items) => {
        const ids = []
        ;(items ?? []).forEach(item => {
            ids.push(item.id)
            if (dirtyIds.value.has(item.id)) return
            itemForms[item.id] = {
                qty: item.qty, nights: item.nights,
                description: item.description ?? '',
                unit_cost: item.unit_cost, unit_sell: item.unit_sell,
                start_date: item.start_date ? String(item.start_date).slice(0, 10) : '',
                end_date:   item.end_date   ? String(item.end_date).slice(0, 10)   : '',
            }
        })
        Object.keys(itemForms).forEach(id => { if (!ids.includes(Number(id))) delete itemForms[id] })
    },
    { immediate: true, deep: true }
)

let saveTimer = null
let pendingAction = null

function markDirty(itemId) {
    dirtyIds.value.add(itemId)
    saveState.value = 'pending'
    clearTimeout(saveTimer)
    saveTimer = setTimeout(flushSaves, 1500)
}

function flushSaves() {
    clearTimeout(saveTimer)
    if (saveState.value === 'saving') return
    if (!dirtyIds.value.size) {
        const act = pendingAction
        pendingAction = null
        act?.()
        return
    }

    const rows = [...dirtyIds.value].filter(id => itemForms[id]).map(id => ({ id, ...itemForms[id] }))
    dirtyIds.value = new Set()
    saveState.value = 'saving'
    errorMsg.value = ''

    router.patch(route('invoice-items.bulk-update', props.invoice.id), { items: rows }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => { saveState.value = dirtyIds.value.size ? 'pending' : 'saved' },
        onError: (errors) => {
            errorMsg.value = Object.values(errors ?? {})[0] ?? 'Terjadi kesalahan.'
            rows.forEach(r => dirtyIds.value.add(r.id))
            saveState.value = 'pending'
        },
        onFinish: () => {
            if (saveState.value === 'saving') saveState.value = 'idle'
            if (dirtyIds.value.size) {
                saveTimer = setTimeout(flushSaves, 300)
            } else {
                const act = pendingAction
                pendingAction = null
                act?.()
            }
        },
    })
}

function afterFlush(action) {
    if (!dirtyIds.value.size && saveState.value !== 'saving') return action()
    pendingAction = action
    flushSaves()
}

async function deleteItem(itemId) {
    if (!(await confirm({ title: 'Hapus item ini?', confirmLabel: 'Hapus' }))) return

    errorMsg.value = ''
    dirtyIds.value.delete(itemId)
    afterFlush(() => {
        router.delete(route('invoice-items.destroy', itemId), {
            preserveScroll: true,
            only: ['tour'],
            onError: (errors) => {
                // Guard "item sudah punya Bill" (lihat InvoiceItemController::destroy) muncul di sini.
                errorMsg.value = Object.values(errors ?? {})[0] ?? 'Item tidak bisa dihapus.'
            },
        })
    })
}

function lineSellLocal(itemId) {
    const f = itemForms[itemId]
    if (!f) return 0
    return (Number(f.qty) || 0) * (Number(f.nights) || 0) * (Number(f.unit_sell) || 0)
}

function handleBeforeUnload(e) {
    if (dirtyIds.value.size || saveState.value === 'saving') {
        e.preventDefault()
        e.returnValue = ''
    }
}

let stopRouterGuard = null
onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
    stopRouterGuard = router.on('before', (event) => {
        if (event.detail.visit.method !== 'get') return
        if (dirtyIds.value.size || saveState.value === 'saving') {
            return window.confirm('Ada perubahan Rincian Profit yang belum tersimpan. Tetap pindah halaman?')
        }
    })
})
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    stopRouterGuard?.()
    clearTimeout(saveTimer)
})

// Textarea deskripsi menyesuaikan tinggi dengan isinya — teks panjang tidak terpotong
const vAutogrow = {
    mounted: (el) => autoGrow(el),
    updated: (el) => autoGrow(el),
}
function autoGrow(el) {
    el.style.height = 'auto'
    el.style.height = el.scrollHeight + 'px'
}

// Enter = pindah ke baris berikutnya, kolom sama (input angka/deskripsi cepat dari atas ke bawah)
function focusNextRow(event) {
    const { col, row } = event.target.dataset
    const next = event.target.closest('table')
        ?.querySelector(`[data-col="${col}"][data-row="${Number(row) + 1}"]`)
    if (next) { next.focus(); next.select?.() }
}

// ── Tambah item manual (deskripsi bebas, tanpa katalog produk) ─────────────
// Katalog produk & tempel-dari-clipboard tetap jadi fitur khusus sales di
// InvoicesPanel.vue (lihat plan Task 4) — di sini hanya jalur tambah paling
// sederhana yang dipakai bersama sales & akuntan.
const addOpen = ref(false)
const addForm = reactive({ description: '', qty: 1, nights: 1, unit_cost: 0, unit_sell: 0 })

function submitAdd() {
    router.post(route('invoice-items.bulk', props.invoice.id), {
        items: [{ ...addForm }],
    }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            addOpen.value = false
            Object.assign(addForm, { description: '', qty: 1, nights: 1, unit_cost: 0, unit_sell: 0 })
        },
        onError: (errors) => { errorMsg.value = Object.values(errors ?? {})[0] ?? 'Gagal menambah item.' },
    })
}
</script>

<template>
    <div class="rounded-md border">
        <div class="px-3 py-2 flex items-center justify-between border-b">
            <span class="text-xs font-semibold uppercase text-muted-foreground">Rincian Profit (internal · IDR)</span>
            <span v-if="invoice.approved_at" class="text-[11px] text-amber-600">
                Perubahan di sini tercatat di riwayat tour.
            </span>
        </div>

        <p v-if="errorMsg" class="px-3 py-1.5 text-xs text-red-600 bg-red-50 border-b">{{ errorMsg }}</p>

        <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="border-b bg-muted text-muted-foreground text-xs uppercase">
                        <th class="px-3 py-2 text-left">Deskripsi</th>
                        <th class="px-2 py-2 text-center w-24">Tanggal</th>
                        <th class="px-2 py-2 text-center w-14">Qty</th>
                        <th class="px-2 py-2 text-center w-14">Mlm</th>
                        <th class="px-2 py-2 text-right w-28">Cost/unit</th>
                        <th class="px-2 py-2 text-right w-28">Sell/unit</th>
                        <th class="px-2 py-2 text-right w-28">Total Jual</th>
                        <th class="px-2 py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!(invoice.items ?? []).length">
                        <td colspan="8" class="text-center py-6 text-muted-foreground">Belum ada item.</td>
                    </tr>
                    <tr v-for="(item, idx) in invoice.items" :key="item.id" class="border-b last:border-0">
                        <td class="px-2 py-1">
                            <span class="block text-xs text-muted-foreground mb-0.5">
                                {{ TYPE_LABELS[item.product_type] ?? item.product_type ?? '—' }}
                            </span>
                            <textarea v-model="itemForms[item.id].description" @input="markDirty(item.id)" rows="1"
                                v-autogrow data-col="description" :data-row="idx" @keydown.enter.prevent="focusNextRow($event)"
                                class="border rounded px-2 py-1 text-sm w-full min-w-[10rem] resize-none overflow-hidden leading-snug block focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
                        </td>
                        <td class="px-2 py-1">
                            <input type="date" v-model="itemForms[item.id].start_date" @change="markDirty(item.id)"
                                class="border rounded px-1 py-0.5 text-xs w-full mb-1" />
                            <input type="date" v-model="itemForms[item.id].end_date" @change="markDirty(item.id)"
                                :min="itemForms[item.id].start_date" class="border rounded px-1 py-0.5 text-xs w-full" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].qty" @input="markDirty(item.id)" min="1"
                                data-col="qty" :data-row="idx" @keydown.enter.prevent="focusNextRow($event)"
                                class="w-14 border rounded px-1 py-1 text-center text-sm" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].nights" @input="markDirty(item.id)" min="1"
                                data-col="nights" :data-row="idx" @keydown.enter.prevent="focusNextRow($event)"
                                class="w-14 border rounded px-1 py-1 text-center text-sm" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].unit_cost" @input="markDirty(item.id)" min="0"
                                data-col="unit_cost" :data-row="idx" @keydown.enter.prevent="focusNextRow($event)"
                                class="w-28 border rounded px-2 py-1 text-right text-sm font-mono" />
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" v-model="itemForms[item.id].unit_sell" @input="markDirty(item.id)" min="0"
                                data-col="unit_sell" :data-row="idx" @keydown.enter.prevent="focusNextRow($event)"
                                class="w-28 border rounded px-2 py-1 text-right text-sm font-mono" />
                        </td>
                        <td class="px-2 py-1 text-right font-mono text-sm font-medium">{{ fmtRp(lineSellLocal(item.id)) }}</td>
                        <td class="px-2 py-1 text-center">
                            <button type="button" @click="deleteItem(item.id)"
                                class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus item">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2 border-t flex items-center justify-between">
            <span v-if="saveState !== 'idle'" class="text-[11px]"
                :class="saveState === 'saved' ? 'text-green-600' : 'text-amber-600'">
                {{ saveState === 'pending' ? '● Ada perubahan…' : saveState === 'saving' ? '⏳ Menyimpan…' : '✓ Tersimpan' }}
            </span>
            <span v-else></span>
            <Button size="sm" variant="outline" @click="addOpen = true">+ Tambah Item</Button>
        </div>
    </div>

    <Dialog v-model:open="addOpen">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Tambah Item Rincian Profit</DialogTitle></DialogHeader>
            <form @submit.prevent="submitAdd" class="space-y-3 mt-2">
                <Input v-model="addForm.description" placeholder="Deskripsi" required />
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="addForm.qty" type="number" min="1" placeholder="Qty" />
                    <Input v-model="addForm.nights" type="number" min="1" placeholder="Malam" />
                    <Input v-model="addForm.unit_cost" type="number" min="0" placeholder="Cost/unit" />
                    <Input v-model="addForm.unit_sell" type="number" min="0" placeholder="Sell/unit" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" @click="addOpen = false">Batal</Button>
                    <Button type="submit">Tambah</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
