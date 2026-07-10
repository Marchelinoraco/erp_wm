<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtNum, fmtRp, fmtCur } from '@/lib/fmt'
import { TYPE_LABELS } from '@/lib/tourConstants'

const props = defineProps({ tour: Object, products: Array })

const CURRENCIES = ['IDR', 'USD', 'EUR', 'SGD', 'AUD', 'MYR']

const invoices = computed(() => props.tour.invoices ?? [])
const tourPax  = computed(() => Number(props.tour.pax) || 0)

// Header proforma (read-only, dari Tour)
const guestName = computed(() => {
    const name = props.tour.customer?.name || 'Valued Guest'
    return tourPax.value > 1 ? `${name} & Party` : name
})
const reservationLabel = computed(() => props.tour.title || props.tour.code || '-')
const dateLabel = computed(() => {
    const s = props.tour.start_date ? String(props.tour.start_date).slice(0, 10) : ''
    const e = props.tour.end_date ? String(props.tour.end_date).slice(0, 10) : ''
    if (!s) return '—'
    return e ? `${s} – ${e}` : s
})

// ── Form state (keyed by invoice id) ────────────────────────────────────────────
const proformaForms = reactive({})   // { currency, unit_price, description_lines[], notes }
const exchangeForms = reactive({})   // kurs input (non-IDR)
const dueForms      = reactive({})   // jatuh tempo
const itemForms     = reactive({})   // rincian profit (keyed by item id)
const profitOpen    = reactive({})   // collapsible panel profit

watch(
    () => props.tour.invoices,
    (list) => {
        const itemIds = []
        const invIds  = []
        ;(list ?? []).forEach(inv => {
            invIds.push(inv.id)
            dueForms[inv.id]      = inv.due_date ? String(inv.due_date).slice(0, 10) : ''
            exchangeForms[inv.id] = inv.exchange_rate && Number(inv.exchange_rate) !== 1 ? Number(inv.exchange_rate) : ''
            proformaForms[inv.id] = {
                currency:          inv.currency || 'IDR',
                unit_price:        Number(inv.unit_price) || 0,
                guest_name:        inv.guest_name || '',
                description_lines: Array.isArray(inv.description_lines)
                    ? inv.description_lines.map(l => ({ label: l.label ?? '', date: l.date ?? '', detail: l.detail ?? '' }))
                    : [],
                notes: inv.notes || '',
            }
            if (!(inv.id in profitOpen)) profitOpen[inv.id] = false
            ;(inv.items ?? []).forEach(item => {
                itemIds.push(item.id)
                itemForms[item.id] = {
                    qty:         item.qty,
                    nights:      item.nights,
                    description: item.description ?? '',
                    unit_cost:   item.unit_cost,
                    unit_sell:   item.unit_sell,
                    start_date:  item.start_date ? String(item.start_date).slice(0, 10) : '',
                    end_date:    item.end_date ? String(item.end_date).slice(0, 10) : '',
                }
            })
        })
        Object.keys(itemForms).forEach(id => { if (!itemIds.includes(Number(id))) delete itemForms[id] })
        Object.keys(dueForms).forEach(id => { if (!invIds.includes(Number(id))) delete dueForms[id] })
    },
    { immediate: true, deep: true }
)

const errorMsg = ref('')
function onError(errors) {
    errorMsg.value = Object.values(errors ?? {})[0] ?? 'Terjadi kesalahan.'
}
const reload = { preserveScroll: true, only: ['tour'], onError }

function todayStr() {
    return new Date().toISOString().slice(0, 10)
}

// ── Helpers status ──────────────────────────────────────────────────────────────
function isApproved(inv) { return !!inv.approved_at }
function stage(inv) {
    if (inv.approved_at) return 'approved'
    return Number(inv.baseline_total) > 0 ? 'detail' : 'baseline'
}
const STAGE_BADGE = {
    baseline: { label: 'Tahap 1 · Proforma', cls: 'bg-blue-100 text-blue-700' },
    detail:   { label: 'Patokan Terkunci',   cls: 'bg-amber-100 text-amber-700' },
    approved: { label: 'Sudah di Keuangan',  cls: 'bg-green-100 text-green-700' },
}

// Total proforma (mata uang invoice) = harga/pax × pax
function proformaTotal(invId) {
    const f = proformaForms[invId]
    if (!f) return 0
    return (Number(f.unit_price) || 0) * Math.max(tourPax.value, 1)
}
// Tipe "tour" (inbound/outbound): profit = tagihan customer (harga/pax × pax,
// IDR) − total cost item. Tipe lain: profit per item (sell − cost).
const isTourType = computed(() => props.tour.type === 'tour')

// Nilai tagihan dalam IDR; null bila kurs non-IDR belum diketahui.
function invRevenueIdr(inv) {
    if (isApproved(inv)) return Number(inv.total_idr) || 0
    const cur   = proformaForms[inv.id]?.currency || inv.currency || 'IDR'
    const total = proformaTotal(inv.id)
    if (cur === 'IDR') return total
    const rate = Number(exchangeForms[inv.id]) || Number(inv.exchange_rate) || 0
    return rate > 1 ? total * rate : null
}

function invProfit(inv) {
    const totalCost = (inv.items ?? []).reduce((s, i) => s + Number(i.line_cost), 0)
    if (isTourType.value) {
        const rev = invRevenueIdr(inv)
        return rev === null ? null : rev - totalCost
    }
    return (inv.items ?? []).reduce((s, i) => s + (Number(i.line_sell) - Number(i.line_cost)), 0)
}

// ── Salin rincian profit ke clipboard (tab-separated → rapi di Excel/Sheets) ──
const copiedProfit = ref(null)
async function copyProfitTable(inv) {
    const rows = [['Deskripsi', 'Tipe', 'Qty', 'Mlm', 'Cost/unit', 'Sell/unit', 'Total Cost', 'Total Jual']]
    ;(inv.items ?? []).forEach(item => {
        const f = itemForms[item.id] ?? item
        const qty    = Number(f.qty) || 0
        const nights = Number(f.nights) || 0
        const cost   = Number(f.unit_cost) || 0
        const sell   = Number(f.unit_sell) || 0
        rows.push([
            f.description || item.product?.name || '',
            TYPE_LABELS[item.product_type] ?? item.product_type ?? '',
            qty, nights, cost, sell,
            qty * nights * cost,
            qty * nights * sell,
        ])
    })
    const totalCost = (inv.items ?? []).reduce((s, i) => s + Number(i.line_cost), 0)
    const totalSell = (inv.items ?? []).reduce((s, i) => s + Number(i.line_sell), 0)
    rows.push([])
    rows.push(['Total', '', '', '', '', '', totalCost, totalSell])
    if (isTourType.value) {
        rows.push(['Total Tagihan (IDR)', '', '', '', '', '', '', invRevenueIdr(inv) ?? 'kurs belum diisi'])
    }
    rows.push(['Profit', '', '', '', '', '', '', invProfit(inv) ?? 'kurs belum diisi'])
    rows.push(['Margin', '', '', '', '', '', '', `${invMargin(inv)}%`])

    const text = rows.map(r => r.join('\t')).join('\n')
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        // Fallback untuk browser tanpa izin clipboard API
        const ta = document.createElement('textarea')
        ta.value = text
        document.body.appendChild(ta)
        ta.select()
        document.execCommand('copy')
        document.body.removeChild(ta)
    }
    copiedProfit.value = inv.id
    setTimeout(() => { if (copiedProfit.value === inv.id) copiedProfit.value = null }, 2000)
}

// ── Tempel rincian profit dari clipboard (kebalikan tombol Salin) ───────────────
const pasteDialogOpen = ref(false)
const pasteTarget     = ref(null)   // invoice id tujuan
const pasteText       = ref('')

const LABEL_TO_TYPE = Object.fromEntries(
    Object.entries(TYPE_LABELS).map(([k, v]) => [v.toLowerCase(), k])
)

function openPasteDialog(inv) {
    pasteTarget.value = inv.id
    pasteText.value = ''
    pasteDialogOpen.value = true
}

// "25000,00" / "25.000" / "Rp 25.000,50" / "25,000.00" → angka
function parseNum(s) {
    s = String(s ?? '').replace(/[^\d.,-]/g, '')
    if (!s) return 0
    const lastDot = s.lastIndexOf('.'), lastComma = s.lastIndexOf(',')
    if (lastDot !== -1 && lastComma !== -1) {
        // Pemisah desimal = yang paling belakang; sisanya pemisah ribuan
        s = lastComma > lastDot
            ? s.replace(/\./g, '').replace(',', '.')
            : s.replace(/,/g, '')
    } else if (lastComma !== -1) {
        const dec = s.length - lastComma - 1
        s = dec <= 2 ? s.replace(',', '.') : s.replace(/,/g, '')
    } else if (lastDot !== -1) {
        const dec = s.length - lastDot - 1
        if (dec === 3) s = s.replace(/\./g, '')   // 25.000 → ribuan gaya Indonesia
    }
    return Number(s) || 0
}

const parsedPasteRows = computed(() => {
    const rows = []
    for (const line of pasteText.value.split(/\r?\n/)) {
        const cells = line.split('\t').map(c => c.trim())
        if (cells.every(c => c === '')) continue
        const first = (cells[0] ?? '').toLowerCase()
        // Lewati header & baris ringkasan hasil tombol Salin
        if (['deskripsi', 'description', 'total', 'profit', 'margin'].includes(first)) continue
        if (cells.length < 2) continue

        // Kolom "Tipe" opsional: ada bila sel ke-2 bukan angka
        const hasType = cells.length >= 6 && cells[1] !== '' && !/^[\d.,-]+$/.test(cells[1])
        const o = hasType ? 1 : 0
        rows.push({
            description:  cells[0],
            product_type: hasType ? (LABEL_TO_TYPE[cells[1].toLowerCase()] ?? null) : null,
            qty:          Math.max(Math.round(parseNum(cells[1 + o])) || 1, 1),
            nights:       Math.max(Math.round(parseNum(cells[2 + o])) || 1, 1),
            unit_cost:    parseNum(cells[3 + o]),
            unit_sell:    parseNum(cells[4 + o]),
        })
    }
    return rows
})

function submitPaste() {
    if (!parsedPasteRows.value.length) return
    errorMsg.value = ''
    router.post(route('invoice-items.bulk', pasteTarget.value), { items: parsedPasteRows.value }, {
        ...reload,
        onSuccess: () => { pasteDialogOpen.value = false },
    })
}
function invMargin(inv) {
    const profit = invProfit(inv)
    if (profit === null) return 0
    const base = isTourType.value
        ? (invRevenueIdr(inv) ?? 0)
        : (inv.items ?? []).reduce((s, i) => s + Number(i.line_sell), 0)
    return base > 0 ? Math.round((profit / base) * 1000) / 10 : 0
}
function baselineMatched(inv) {
    return Math.abs(proformaTotal(inv.id) - Number(inv.baseline_total)) < 0.01
}

// ── Aksi invoice ──────────────────────────────────────────────────────────────
function createInvoice() {
    errorMsg.value = ''
    router.post(route('invoices.store', props.tour.id), {}, reload)
}
function saveProforma(invId) {
    errorMsg.value = ''
    router.patch(route('invoices.proforma', invId), proformaForms[invId], reload)
}
function addLine(invId) {
    proformaForms[invId].description_lines.push({ label: '', date: '', detail: '' })
}
function removeLine(invId, idx) {
    proformaForms[invId].description_lines.splice(idx, 1)
    saveProforma(invId)
}
function lockBaseline(inv) {
    errorMsg.value = ''
    router.patch(route('invoices.baseline', inv.id), {}, reload)
}
function saveDueDate(invId) {
    errorMsg.value = ''
    router.patch(route('invoices.due-date', invId), { due_date: dueForms[invId] || null }, reload)
}
async function deleteInvoice(inv) {
    if (await confirm({ title: `Hapus invoice ${inv.number}?`, confirmLabel: 'Hapus' })) {
        errorMsg.value = ''
        router.delete(route('invoices.destroy', inv.id), reload)
    }
}

// ── Persetujuan (dengan kurs untuk non-IDR) ─────────────────────────────────────
const approveDialogOpen = ref(false)
const approveTarget     = ref(null)
const approveRate       = ref('')

async function approve(inv) {
    errorMsg.value = ''
    if ((proformaForms[inv.id]?.currency || 'IDR') !== 'IDR') {
        approveTarget.value = inv
        approveRate.value   = exchangeForms[inv.id] || ''
        approveDialogOpen.value = true
        return
    }
    if (await confirm({
        title: 'Setujui invoice ini?',
        description: 'Setelah disetujui, invoice masuk ke Keuangan dan tidak bisa diubah lagi.',
        confirmLabel: 'Setujui',
    })) {
        router.post(route('invoices.approve', inv.id), {}, reload)
    }
}
function submitApprove() {
    const inv = approveTarget.value
    if (!inv) return
    errorMsg.value = ''
    router.post(route('invoices.approve', inv.id), { exchange_rate: approveRate.value }, {
        ...reload,
        onSuccess: () => { approveDialogOpen.value = false },
    })
}
const approveIdrPreview = computed(() => {
    const inv = approveTarget.value
    if (!inv) return 0
    return proformaTotal(inv.id) * (Number(approveRate.value) || 0)
})

// ── Aksi baris item (rincian profit internal) ───────────────────────────────────
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

// ── Pembayaran / DP (sales dapat input langsung) ────────────────────────────────
const payForms = reactive({})  // { amount, date, method, notes } per invoice id

watch(
    () => props.tour.invoices,
    (list) => {
        ;(list ?? []).forEach(inv => {
            if (!(inv.id in payForms)) {
                payForms[inv.id] = { amount: '', date: todayStr(), method: 'transfer', notes: '' }
            }
        })
    },
    { immediate: true }
)

function invPaid(inv) {
    return (inv.payments ?? []).reduce((s, p) => s + Number(p.amount), 0)
}
function invOutstanding(inv) {
    return Math.max(Number(inv.total) - invPaid(inv), 0)
}
function savePayment(invId) {
    errorMsg.value = ''
    router.post(route('invoice-deposits.store', invId), payForms[invId], {
        ...reload,
        onSuccess: () => {
            payForms[invId] = { amount: '', date: todayStr(), method: 'transfer', notes: '' }
        },
    })
}
async function deletePayment(payId) {
    if (await confirm({ title: 'Delete this payment?', confirmLabel: 'Delete' })) {
        errorMsg.value = ''
        router.delete(route('invoice-deposits.destroy', payId), reload)
    }
}

// ── Dialog pilih produk ────────────────────────────────────────────────────────
const addDialogOpen    = ref(false)
const addTargetInvoice = ref(null)
const productSearch    = ref('')
const addingProductId  = ref(null)

// Semua produk bisa diberi tanggal mulai & selesai. Hotel/transport/guide
// WAJIB bertanggal; tipe lain opsional. Item bertanggal tampil ke tim
// lapangan (guide/supir/tour leader) di MyJobs & manifest.
const DATED_TYPES = ['hotel', 'transport', 'guide']
const isDated = (t) => DATED_TYPES.includes(t)
const pendingProduct = ref(null)   // produk menunggu input tanggal
const addDates = reactive({ start: '', end: '' })

function fmtDateID(d) {
    if (!d) return ''
    return new Date(String(d).slice(0, 10)).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
    })
}

function openAddDialog(inv) {
    addTargetInvoice.value = inv
    productSearch.value    = ''
    pendingProduct.value   = null
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
function pickProduct(product) {
    pendingProduct.value = product
    // Tipe wajib-tanggal diprefill dari tanggal mulai tour; tipe opsional
    // dibiarkan kosong supaya tidak terisi tanggal tanpa sengaja.
    addDates.start = isDated(product.type) && props.tour.start_date
        ? String(props.tour.start_date).slice(0, 10) : ''
    addDates.end = ''
}

watch(() => addDates.start, (s) => {
    if (s && (!addDates.end || addDates.end < s)) addDates.end = s
})

function confirmAddDated() {
    if (isDated(pendingProduct.value.type) && (!addDates.start || !addDates.end)) return
    const extra = {}
    if (addDates.start) {
        extra.start_date = addDates.start
        extra.end_date   = addDates.end || addDates.start
        // Hotel: jumlah malam otomatis dari rentang check-in → check-out
        if (pendingProduct.value.type === 'hotel') {
            const nights = Math.round((new Date(extra.end_date) - new Date(addDates.start)) / 86400000)
            extra.nights = Math.max(nights, 1)
        }
    }
    addProduct(pendingProduct.value, extra)
}

function addProduct(product, extra = {}) {
    addingProductId.value = product.id
    router.post(route('invoice-items.store', addTargetInvoice.value.id), { product_id: product.id, ...extra }, {
        preserveScroll: true,
        only: ['tour'],
        onError,
        onSuccess: () => {
            addingProductId.value = null
            addDialogOpen.value   = false
            productSearch.value   = ''
            pendingProduct.value  = null
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
                    Isi proforma (mata uang, deskripsi, harga) → kunci patokan → setujui untuk kirim ke Keuangan.
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
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-700">
                            {{ proformaForms[inv.id]?.currency || inv.currency || 'IDR' }}
                        </span>
                        <span class="text-xs text-muted-foreground flex items-center gap-1.5">
                            <span>Jatuh tempo:</span>
                            <input v-if="!isApproved(inv)" type="date" v-model="dueForms[inv.id]" @change="saveDueDate(inv.id)"
                                class="border rounded px-2 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary" />
                            <span v-else class="font-medium text-foreground">{{ dueForms[inv.id] || '—' }}</span>
                        </span>
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

                <!-- Header proforma (read-only dari Tour) -->
                <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1 rounded-md bg-muted/20 px-4 py-3 text-sm">
                    <div><span class="text-muted-foreground">Guest Name:</span> <span class="font-medium">{{ inv.guest_name || guestName }}</span></div>
                    <div><span class="text-muted-foreground">Reservation:</span> <span class="font-medium">{{ reservationLabel }}</span></div>
                    <div><span class="text-muted-foreground">Date:</span> <span class="font-medium">{{ dateLabel }}</span></div>
                    <div><span class="text-muted-foreground">Total Pax:</span> <span class="font-medium">{{ tourPax || '—' }} pax</span></div>
                </div>

                <!-- ── EDITOR PROFORMA (belum disetujui) ── -->
                <template v-if="!isApproved(inv) && proformaForms[inv.id]">
                    <!-- Guest Name (override tampilan PDF, tidak mengubah data Customer) -->
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Guest Name (tampil di PDF)</label>
                        <input type="text" v-model="proformaForms[inv.id].guest_name" @blur="saveProforma(inv.id)"
                            :placeholder="guestName"
                            class="block w-full max-w-sm border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>

                    <!-- Mata uang + kurs -->
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Mata Uang</label>
                            <select v-model="proformaForms[inv.id].currency" @change="saveProforma(inv.id)"
                                class="block border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                <option v-for="c in CURRENCIES" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div v-if="proformaForms[inv.id].currency !== 'IDR'" class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Kurs ke IDR (1 {{ proformaForms[inv.id].currency }} = Rp)</label>
                            <input type="number" v-model="exchangeForms[inv.id]" min="0" step="0.01" placeholder="mis. 17000"
                                class="block w-40 border rounded px-2 py-1 text-sm text-right font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                            <p class="text-[11px] text-muted-foreground">Diisi/dipakai saat menyetujui.</p>
                        </div>
                    </div>

                    <!-- Baris deskripsi terstruktur -->
                    <div class="rounded-md border">
                        <div class="flex items-center justify-between px-3 py-2 border-b bg-muted/30">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Baris Deskripsi (Hotel, Transport, dll)</span>
                            <Button size="sm" variant="outline" @click="addLine(inv.id)">+ Baris</Button>
                        </div>
                        <div v-if="proformaForms[inv.id].description_lines.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">
                            Belum ada baris. Klik "+ Baris" untuk menambah (mis. Label "Hotel", tanggal, deskripsi kamar).
                        </div>
                        <div v-else class="divide-y">
                            <div v-for="(ln, idx) in proformaForms[inv.id].description_lines" :key="idx"
                                class="flex flex-wrap items-start gap-2 px-3 py-2">
                                <input type="text" v-model="ln.label" @blur="saveProforma(inv.id)" placeholder="Label (mis. Hotel)"
                                    class="w-32 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="text" v-model="ln.date" @blur="saveProforma(inv.id)" placeholder="Tanggal (mis. 13-15 Aug 2026)"
                                    class="w-44 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <textarea v-model="ln.detail" @blur="saveProforma(inv.id)" placeholder="Deskripsi (Enter untuk baris baru, mis. nama hotel lalu tipe kamar)"
                                    rows="2"
                                    class="flex-1 min-w-[12rem] border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
                                <button type="button" @click="removeLine(inv.id, idx)"
                                    class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus baris">✕</button>
                            </div>
                        </div>
                    </div>

                    <!-- Harga per pax -->
                    <div class="flex flex-wrap items-end gap-3 rounded-md bg-muted/20 px-4 py-3">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Harga / pax ({{ proformaForms[inv.id].currency }})</label>
                            <input type="number" v-model="proformaForms[inv.id].unit_price" @change="saveProforma(inv.id)" min="0"
                                class="block w-40 border rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                        </div>
                        <div class="text-sm pb-1">
                            × <span class="font-medium">{{ tourPax || 1 }} pax</span>
                            =
                            <span class="font-mono font-semibold">{{ fmtCur(proformaTotal(inv.id), proformaForms[inv.id].currency) }}</span>
                        </div>
                    </div>
                </template>

                <!-- ── Ringkasan proforma (sudah disetujui) ── -->
                <template v-else-if="isApproved(inv)">
                    <div v-if="(inv.description_lines ?? []).length" class="rounded-md border divide-y text-sm">
                        <div v-for="(ln, idx) in inv.description_lines" :key="idx" class="flex gap-2 px-3 py-1.5">
                            <span class="w-28 font-medium">{{ idx === 0 || ln.label !== inv.description_lines[idx - 1].label ? ln.label : '' }}</span>
                            <span class="w-40 text-muted-foreground">{{ ln.date }}</span>
                            <span class="flex-1 whitespace-pre-line">{{ ln.detail }}</span>
                        </div>
                    </div>
                    <div class="text-sm">
                        Price:
                        <span class="font-mono">{{ fmtCur(inv.unit_price, inv.currency) }}</span>
                        × {{ tourPax || 1 }} pax =
                        <span class="font-mono font-semibold">{{ fmtCur(inv.total, inv.currency) }}</span>
                        <span v-if="(inv.currency || 'IDR') !== 'IDR'" class="text-xs text-muted-foreground">
                            (≈ {{ fmtRp(inv.total_idr) }}, rate {{ fmtNum(inv.exchange_rate) }})
                        </span>
                    </div>
                </template>

                <!-- ── Rincian profit internal (opsional, collapsible) ── -->
                <div class="rounded-md border">
                    <button type="button" @click="profitOpen[inv.id] = !profitOpen[inv.id]"
                        class="w-full flex items-center justify-between px-3 py-2 text-left text-xs font-semibold uppercase text-muted-foreground hover:bg-muted/30">
                        <span>Rincian Profit (internal · IDR) — opsional</span>
                        <span class="flex items-center gap-2">
                            <span class="font-mono normal-case text-[11px]" :class="(invProfit(inv) ?? 0) >= 0 ? 'text-green-700' : 'text-red-600'">
                                {{ invProfit(inv) === null ? 'isi kurs dulu' : `${fmtRp(invProfit(inv))} (${invMargin(inv)}%)` }}
                            </span>
                            <span>{{ profitOpen[inv.id] ? '▾' : '▸' }}</span>
                        </span>
                    </button>

                    <div v-if="profitOpen[inv.id]" class="border-t p-3 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] text-muted-foreground">
                                Tidak muncul di PDF customer. Hanya untuk memantau modal vs jual (IDR). Tidak wajib untuk menyetujui.
                                <span v-if="isTourType" class="block">Profit tour = Total tagihan (harga/pax × pax) − total cost item.</span>
                            </p>
                            <div class="flex items-center gap-2 shrink-0">
                                <Button v-if="!isApproved(inv)" size="sm" variant="outline" @click="openPasteDialog(inv)">📥 Tempel</Button>
                                <Button v-if="(inv.items ?? []).length" size="sm" variant="outline" @click="copyProfitTable(inv)">
                                    {{ copiedProfit === inv.id ? '✓ Tersalin' : '📋 Salin' }}
                                </Button>
                            </div>
                        </div>
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
                                    <template v-if="isApproved(inv)">
                                        <tr v-for="item in inv.items" :key="item.id" class="border-b last:border-0">
                                            <td class="px-3 py-1.5">
                                                {{ item.description || item.product?.name }}
                                                <span class="block text-xs text-muted-foreground">
                                                    {{ TYPE_LABELS[item.product_type] ?? item.product_type }}
                                                    <template v-if="item.start_date">
                                                        · 📅 {{ fmtDateID(item.start_date) }}<template v-if="item.end_date && item.end_date !== item.start_date"> – {{ fmtDateID(item.end_date) }}</template>
                                                    </template>
                                                </span>
                                            </td>
                                            <td class="px-3 py-1.5 text-center">{{ item.qty }}</td>
                                            <td class="px-3 py-1.5 text-center">{{ item.nights }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_cost) }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono">{{ fmtNum(item.unit_sell) }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono font-medium">{{ fmtRp(item.line_sell) }}</td>
                                        </tr>
                                    </template>
                                    <template v-else>
                                        <tr v-for="item in inv.items" :key="item.id" class="border-b last:border-0 hover:bg-muted/20">
                                            <td class="px-3 py-1.5">
                                                <input type="text" v-model="itemForms[item.id].description" @blur="saveItem(item.id)"
                                                    class="border rounded px-2 py-0.5 text-sm w-full focus:outline-none focus:ring-1 focus:ring-primary" />
                                                <span class="text-xs text-muted-foreground">{{ TYPE_LABELS[item.product_type] ?? item.product_type }}</span>
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="text-xs">📅</span>
                                                    <input type="date" v-model="itemForms[item.id].start_date" @change="saveItem(item.id)"
                                                        class="border rounded px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary" />
                                                    <span class="text-xs text-muted-foreground">–</span>
                                                    <input type="date" v-model="itemForms[item.id].end_date" @change="saveItem(item.id)"
                                                        :min="itemForms[item.id].start_date"
                                                        class="border rounded px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary" />
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
                    </div>
                </div>

                <!-- Ringkasan patokan / total / aksi -->
                <div class="flex flex-wrap items-end justify-between gap-3 rounded-md bg-muted/30 px-4 py-3">
                    <div class="text-sm space-y-1">
                        <div v-if="Number(inv.baseline_total) > 0" class="flex items-center gap-2">
                            <span class="text-muted-foreground">Patokan:</span>
                            <span class="font-mono font-medium">{{ fmtCur(inv.baseline_total, proformaForms[inv.id]?.currency || inv.currency) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Total Tagihan:</span>
                            <span class="font-mono font-medium">
                                {{ isApproved(inv)
                                    ? fmtCur(inv.total, inv.currency)
                                    : fmtCur(proformaTotal(inv.id), proformaForms[inv.id]?.currency) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!isApproved(inv)" class="flex items-center gap-2">
                        <Button v-if="stage(inv) === 'baseline'" size="sm"
                            :disabled="proformaTotal(inv.id) <= 0" @click="lockBaseline(inv)">
                            Kunci Patokan
                        </Button>
                        <template v-else>
                            <Button size="sm" variant="outline" @click="lockBaseline(inv)" title="Set patokan = total proforma sekarang">
                                Samakan Patokan
                            </Button>
                            <Button size="sm" :disabled="!baselineMatched(inv) || proformaTotal(inv.id) <= 0" @click="approve(inv)">
                                Setujui
                            </Button>
                        </template>
                    </div>
                    <div v-else class="text-xs text-green-700 font-medium">
                        ✓ Disetujui — dikelola di Keuangan
                    </div>
                </div>

                <!-- ── Payments / Deposit ── -->
                <div v-if="isApproved(inv)" class="rounded-md border overflow-hidden">
                    <div class="flex items-center justify-between px-3 py-2 bg-muted/30 border-b">
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Payments</span>
                        <span class="text-xs space-x-3">
                            <span>Paid: <span class="font-mono font-medium text-green-700">{{ fmtCur(invPaid(inv), inv.currency) }}</span></span>
                            <span>Outstanding: <span class="font-mono font-medium" :class="invOutstanding(inv) > 0 ? 'text-orange-600' : 'text-green-700'">{{ fmtCur(invOutstanding(inv), inv.currency) }}</span></span>
                        </span>
                    </div>

                    <!-- Daftar pembayaran -->
                    <div v-if="(inv.payments ?? []).length" class="divide-y">
                        <div v-for="p in inv.payments" :key="p.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2 text-sm">
                            <span class="font-mono font-medium text-green-700">+{{ fmtCur(p.amount, inv.currency) }}</span>
                            <span v-if="inv.currency && inv.currency !== 'IDR'" class="text-xs text-muted-foreground">≈ {{ fmtRp(p.amount_idr) }}</span>
                            <span class="text-xs text-muted-foreground">{{ p.date?.slice(0, 10) }} · {{ p.method }}</span>
                            <span v-if="p.notes" class="text-xs text-muted-foreground truncate">· {{ p.notes }}</span>
                            <button type="button" @click="deletePayment(p.id)"
                                class="ml-auto text-muted-foreground hover:text-destructive transition-colors text-base leading-none">×</button>
                        </div>
                    </div>
                    <div v-else class="px-3 py-3 text-xs text-center text-muted-foreground">No payments recorded yet.</div>

                    <!-- Form tambah pembayaran -->
                    <div class="border-t px-3 py-3 bg-muted/10">
                        <p class="text-xs font-medium text-muted-foreground mb-2">Record Payment / Deposit</p>
                        <div class="flex flex-wrap gap-2 items-end">
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground">Amount ({{ inv.currency || 'IDR' }})</label>
                                <input type="number" v-model="payForms[inv.id].amount" min="0.01" step="0.01"
                                    :placeholder="`${inv.currency || 'IDR'} amount`"
                                    class="w-36 border rounded px-2 py-1 text-sm text-right font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground">Date</label>
                                <input type="date" v-model="payForms[inv.id].date"
                                    class="border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground">Method</label>
                                <select v-model="payForms[inv.id].method"
                                    class="border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="space-y-1 flex-1 min-w-[8rem]">
                                <label class="text-xs text-muted-foreground">Notes (optional)</label>
                                <input type="text" v-model="payForms[inv.id].notes" placeholder="e.g. DP 50%"
                                    class="w-full border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                            </div>
                            <Button size="sm" :disabled="!(Number(payForms[inv.id]?.amount) > 0)" @click="savePayment(inv.id)">
                                + Save
                            </Button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Dialog kurs saat menyetujui (non-IDR) -->
        <Dialog v-model:open="approveDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Setujui Invoice — Kurs ke IDR</DialogTitle>
                </DialogHeader>
                <div v-if="approveTarget" class="space-y-3 text-sm">
                    <p>
                        Tagihan customer:
                        <span class="font-mono font-semibold">{{ fmtCur(proformaTotal(approveTarget.id), proformaForms[approveTarget.id]?.currency) }}</span>
                    </p>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">
                            Kurs: 1 {{ proformaForms[approveTarget.id]?.currency }} = Rp
                        </label>
                        <Input type="number" v-model="approveRate" min="0" step="0.01" placeholder="mis. 17000" autofocus />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Nilai ke Keuangan (IDR): <span class="font-mono font-medium text-foreground">{{ fmtRp(approveIdrPreview) }}</span>
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="approveDialogOpen = false">Batal</Button>
                    <Button :disabled="!(Number(approveRate) > 0)" @click="submitApprove">Setujui & Kirim ke Keuangan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Dialog pilih produk -->
        <Dialog v-model:open="addDialogOpen">
            <DialogContent class="max-w-2xl max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Pilih Produk</DialogTitle>
                </DialogHeader>
                <!-- Langkah 2: isi tanggal (wajib utk hotel/transport/guide, opsional lainnya) -->
                <div v-if="pendingProduct" class="space-y-3">
                    <div class="rounded-md border bg-muted/20 px-3 py-2">
                        <p class="font-medium text-sm">{{ pendingProduct.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ TYPE_LABELS[pendingProduct.type] ?? pendingProduct.type }}</p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="isDated(pendingProduct.type)">
                            Produk tipe <b>{{ TYPE_LABELS[pendingProduct.type] ?? pendingProduct.type }}</b> perlu tanggal mulai & selesai —
                            jadwal ini tampil ke tim lapangan di MyJobs.
                        </template>
                        <template v-else>
                            Tanggal <b>opsional</b> untuk tipe {{ TYPE_LABELS[pendingProduct.type] ?? pendingProduct.type }} —
                            kosongkan bila tidak perlu. Item bertanggal ikut tampil di jadwal tim lapangan (MyJobs & manifest).
                        </template>
                        <span v-if="pendingProduct.type === 'hotel'" class="block">Jumlah malam dihitung otomatis dari check-in → check-out.</span>
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">
                                {{ pendingProduct.type === 'hotel' ? 'Check-in' : 'Tanggal Mulai' }}
                            </label>
                            <Input type="date" v-model="addDates.start" autofocus />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">
                                {{ pendingProduct.type === 'hotel' ? 'Check-out' : 'Tanggal Selesai' }}
                            </label>
                            <Input type="date" v-model="addDates.end" :min="addDates.start" />
                        </div>
                    </div>
                    <div class="flex justify-between pt-1">
                        <Button variant="outline" size="sm" @click="pendingProduct = null">← Kembali</Button>
                        <Button size="sm"
                            :disabled="(isDated(pendingProduct.type) && (!addDates.start || !addDates.end)) || addingProductId === pendingProduct.id"
                            @click="confirmAddDated">
                            + Tambah{{ !isDated(pendingProduct.type) && !addDates.start ? ' tanpa tanggal' : '' }}
                        </Button>
                    </div>
                </div>

                <!-- Langkah 1: cari & pilih produk -->
                <template v-else>
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
                                @click="pickProduct(p)"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-muted text-left text-sm transition-colors disabled:opacity-50"
                            >
                                <span class="font-medium">
                                    {{ p.name }}
                                    <span v-if="isDated(p.type)" class="ml-1 text-xs text-muted-foreground font-normal">📅</span>
                                </span>
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
                </template>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog tempel rincian profit ── -->
        <Dialog v-model:open="pasteDialogOpen">
            <DialogContent class="max-w-3xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Tempel Rincian Profit</DialogTitle>
                </DialogHeader>
                <p class="text-xs text-muted-foreground">
                    Tempel (Ctrl+V / Cmd+V) data dari Excel, Google Sheets, atau hasil tombol "Salin".
                    Urutan kolom: <b>Deskripsi · [Tipe] · Qty · Mlm · Cost/unit · Sell/unit</b> — kolom Tipe dan kolom setelah Sell/unit boleh ada/tidak.
                </p>
                <textarea v-model="pasteText" rows="8" autofocus
                    placeholder="Tempel data di sini..."
                    class="w-full border rounded px-2 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"></textarea>

                <div v-if="parsedPasteRows.length" class="overflow-auto max-h-60 rounded-md border">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b bg-muted/30 text-muted-foreground uppercase">
                                <th class="px-2 py-1.5 text-left">Deskripsi</th>
                                <th class="px-2 py-1.5 text-left">Tipe</th>
                                <th class="px-2 py-1.5 text-center">Qty</th>
                                <th class="px-2 py-1.5 text-center">Mlm</th>
                                <th class="px-2 py-1.5 text-right">Cost/unit</th>
                                <th class="px-2 py-1.5 text-right">Sell/unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in parsedPasteRows" :key="i" class="border-b last:border-0">
                                <td class="px-2 py-1">{{ r.description }}</td>
                                <td class="px-2 py-1">{{ r.product_type ? (TYPE_LABELS[r.product_type] ?? r.product_type) : '—' }}</td>
                                <td class="px-2 py-1 text-center">{{ r.qty }}</td>
                                <td class="px-2 py-1 text-center">{{ r.nights }}</td>
                                <td class="px-2 py-1 text-right font-mono">{{ fmtNum(r.unit_cost) }}</td>
                                <td class="px-2 py-1 text-right font-mono">{{ fmtNum(r.unit_sell) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="pasteText.trim()" class="text-xs text-amber-600">
                    Tidak ada baris yang bisa dibaca — pastikan data dipisah tab (hasil copy dari Excel/Sheets).
                </p>

                <DialogFooter>
                    <Button variant="outline" @click="pasteDialogOpen = false">Batal</Button>
                    <Button :disabled="!parsedPasteRows.length" @click="submitPaste">
                        Tambahkan {{ parsedPasteRows.length }} item
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
