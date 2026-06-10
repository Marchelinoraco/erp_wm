<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, reactive, watch, computed, nextTick, onMounted } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/Components/ui/dialog'

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
    tour:              Object,
    customers:         Array,
    products:          Array,
    manifestUrl:       String,
    fieldUsers:        Array,
    emailTemplates:    Object,
    quotationDefaults: Object,
})

// ── Header form ───────────────────────────────────────────────────────────────
const headerForm = useForm({
    customer_id:    props.tour.customer_id ? String(props.tour.customer_id) : 'none',
    title:          props.tour.title          ?? '',
    pax:            props.tour.pax            ?? 1,
    start_date:     props.tour.start_date     ?? '',
    end_date:       props.tour.end_date       ?? '',
    status:         props.tour.status         ?? 'inquiry',
    sales_person:   props.tour.sales_person   ?? '',
    default_markup: props.tour.default_markup ?? 0,
    notes:          props.tour.notes          ?? '',
})

function saveHeader() {
    if (headerForm.customer_id === 'none') headerForm.customer_id = ''
    headerForm.patch(route('tours.update', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
    })
}

// ── Item inline editing ───────────────────────────────────────────────────────
const itemForms = reactive({})

watch(
    () => props.tour.items,
    (items) => {
        // Sync server state back into local forms
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
        // Clean up removed items
        Object.keys(itemForms).forEach(id => {
            if (!items.find(i => i.id == id)) delete itemForms[id]
        })
    },
    { immediate: true }
)

function saveItem(itemId) {
    router.patch(route('tour-items.update', itemId), itemForms[itemId], {
        preserveScroll: true,
        only: ['tour'],
    })
}

function deleteItem(itemId) {
    if (confirm('Hapus item ini?')) {
        router.delete(route('tour-items.destroy', itemId), {
            preserveScroll: true,
            only: ['tour'],
        })
    }
}

// ── Add Product dialog ─────────────────────────────────────────────────────────
const productSearch  = ref('')
const addDialogOpen  = ref(false)
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
    router.post(route('tour-items.store', props.tour.id), {
        product_id: product.id,
    }, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            addingProductId.value = null
            addDialogOpen.value   = false
            productSearch.value   = ''
        },
        onError: () => {
            addingProductId.value = null
        },
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_CONFIG = {
    inquiry:         { label: 'Inquiry',        class: 'bg-gray-100 text-gray-700' },
    quotation_draft: { label: 'Draft Quotation', class: 'bg-blue-100 text-blue-700' },
    quotation_sent:  { label: 'Sent',            class: 'bg-purple-100 text-purple-700' },
    follow_up:       { label: 'Follow Up',       class: 'bg-yellow-100 text-yellow-700' },
    negotiation:     { label: 'Negosiasi',       class: 'bg-orange-100 text-orange-700' },
    confirmed:       { label: 'Confirmed',       class: 'bg-green-100 text-green-700' },
    cancelled:       { label: 'Cancelled',       class: 'bg-red-100 text-red-700' },
}

const TYPE_LABELS = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Attraction', other: 'Lainnya',
}

function fmt(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

// ── Assignments ───────────────────────────────────────────────────────────────
const assignDialogOpen  = ref(false)
const editingAssignment = ref(null)

const assignForm = useForm({
    role:        'guide',
    user_id:     null,
    person_name: '',
    phone:       '',
    vehicle:     '',
    pickup_time: '',
    notes:       '',
})

function openAddAssignment() {
    editingAssignment.value = null
    assignForm.reset()
    assignForm.role = 'guide'
    assignDialogOpen.value = true
}

function openEditAssignment(a) {
    editingAssignment.value = a
    assignForm.role        = a.role
    assignForm.user_id     = a.user_id     ?? null
    assignForm.person_name = a.person_name ?? ''
    assignForm.phone       = a.phone       ?? ''
    assignForm.vehicle     = a.vehicle     ?? ''
    assignForm.pickup_time = a.pickup_time ?? ''
    assignForm.notes       = a.notes       ?? ''
    assignDialogOpen.value = true
}

function onFieldUserSelect(userId) {
    const u = props.fieldUsers?.find(f => f.id === Number(userId))
    if (u) {
        assignForm.user_id     = u.id
        assignForm.person_name = u.name
        assignForm.role        = u.role
    }
}

function submitAssignment() {
    if (editingAssignment.value) {
        assignForm.patch(route('assignments.update', editingAssignment.value.id), {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => { assignDialogOpen.value = false },
        })
    } else {
        assignForm.post(route('assignments.store', props.tour.id), {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => { assignDialogOpen.value = false; assignForm.reset() },
        })
    }
}

function deleteAssignment(id) {
    if (confirm('Hapus assignment ini?')) {
        router.delete(route('assignments.destroy', id), {
            preserveScroll: true,
            only: ['tour'],
        })
    }
}

// ── Manifest link copy ────────────────────────────────────────────────────────
const linkCopied = ref(false)

function copyManifestLink() {
    navigator.clipboard.writeText(props.manifestUrl).then(() => {
        linkCopied.value = true
        setTimeout(() => { linkCopied.value = false }, 2000)
    })
}

const ROLE_LABELS = { guide: 'Guide', driver: 'Driver', tour_leader: 'Tour Leader' }
const ROLE_COLORS = {
    guide:       'bg-blue-100 text-blue-700',
    driver:      'bg-green-100 text-green-700',
    tour_leader: 'bg-yellow-100 text-yellow-700',
}

// ── Itinerary Days ────────────────────────────────────────────────────────────
const itineraryDays = ref(
    props.tour.itinerary_days?.map(d => ({ ...d })) ?? []
)

function addDay() {
    const next = (itineraryDays.value.at(-1)?.day_number ?? 0) + 1
    itineraryDays.value.push({ day_number: next, title: '', description: '' })
}

function removeDay(i) {
    itineraryDays.value.splice(i, 1)
    itineraryDays.value.forEach((d, idx) => { d.day_number = idx + 1 })
}

function saveItinerary() {
    router.post(route('tours.itinerary.days', props.tour.id), { days: itineraryDays.value }, {
        preserveScroll: true,
        only: ['tour'],
    })
}

// ── Riwayat / Activity Log ────────────────────────────────────────────────────
const HISTORY_TYPES = {
    revision:  'Revisi Customer',
    note:      'Catatan Internal',
    call:      'Telepon',
    meeting:   'Meeting',
    email:     'Email',
    confirmed: 'Confirmed',
    cancelled: 'Dibatalkan',
}
const HISTORY_COLORS = {
    revision:  'bg-orange-100 text-orange-700',
    note:      'bg-gray-100 text-gray-600',
    call:      'bg-blue-100 text-blue-700',
    meeting:   'bg-purple-100 text-purple-700',
    email:     'bg-sky-100 text-sky-700',
    confirmed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
}
const HISTORY_ICONS = {
    revision:  '↺',
    note:      '📝',
    call:      '📞',
    meeting:   '👥',
    email:     '✉',
    confirmed: '✓',
    cancelled: '✕',
}

const showHistoryForm = ref(false)
const historyForm = useForm({ type: 'revision', description: '' })

function submitHistory() {
    historyForm.post(route('tours.histories.store', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            historyForm.reset('description')
            showHistoryForm.value = false
        },
    })
}

function deleteHistory(historyId) {
    if (confirm('Hapus catatan ini?')) {
        router.delete(route('tours.histories.destroy', [props.tour.id, historyId]), {
            preserveScroll: true,
            only: ['tour'],
        })
    }
}

function fmtDateTime(d) {
    if (!d) return ''
    return new Date(d).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

// ── Hourly Itinerary ─────────────────────────────────────────────────────────
const itineraryHours = ref(props.tour.itinerary_hours?.map(h => ({ ...h })) ?? [])
const expandedHourDays = reactive(new Set())
const hourForm = useForm({
    day_number: 1,
    start_time: '',
    end_time: '',
    activity: '',
    notes: ''
})
const editingHourId = ref(null)
const expandedHourDaysTick = ref(0)

watch(
    () => props.tour.itinerary_hours,
    (hours) => { itineraryHours.value = hours?.map(h => ({ ...h })) ?? [] },
)

const groupedHours = computed(() => {
    const grouped = {}
    itineraryHours.value.forEach(h => {
        if (!grouped[h.day_number]) grouped[h.day_number] = []
        grouped[h.day_number].push(h)
    })
    return grouped
})

function toggleHourDay(day) {
    if (expandedHourDays.has(day)) {
        expandedHourDays.delete(day)
    } else {
        expandedHourDays.add(day)
    }
    expandedHourDaysTick.value++
}

function isHourDayExpanded(day) {
    void expandedHourDaysTick.value
    return expandedHourDays.has(day)
}

function startAddHour(day) {
    editingHourId.value = null
    hourForm.day_number = day
    hourForm.start_time = ''
    hourForm.end_time = ''
    hourForm.activity = ''
    hourForm.notes = ''
}

function startEditHour(hour) {
    editingHourId.value = hour.id
    hourForm.day_number = hour.day_number
    hourForm.start_time = hour.start_time
    hourForm.end_time = hour.end_time
    hourForm.activity = hour.activity
    hourForm.notes = hour.notes
}

function saveHour() {
    if (!hourForm.start_time || !hourForm.activity) return

    if (editingHourId.value) {
        router.patch(route('tours.itinerary.hours.update', [props.tour.id, editingHourId.value]), {
            day_number: hourForm.day_number,
            start_time: hourForm.start_time,
            end_time: hourForm.end_time,
            activity: hourForm.activity,
            notes: hourForm.notes,
        }, {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => {
                editingHourId.value = null
                hourForm.reset()
            }
        })
    } else {
        router.post(route('tours.itinerary.hours.store', props.tour.id), {
            day_number: hourForm.day_number,
            start_time: hourForm.start_time,
            end_time: hourForm.end_time,
            activity: hourForm.activity,
            notes: hourForm.notes,
        }, {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => {
                editingHourId.value = null
                hourForm.reset()
            }
        })
    }
}

function deleteHour(hourId) {
    if (confirm('Hapus aktivitas ini?')) {
        router.delete(route('tours.itinerary.hours.delete', [props.tour.id, hourId]), {
            preserveScroll: true,
            only: ['tour'],
        })
    }
}

function cancelHourForm() {
    editingHourId.value = null
    hourForm.reset()
}

// ── Itinerary PDF ─────────────────────────────────────────────────────────────
const pdfForm     = useForm({ pdf: null })
const pdfFileRef  = ref(null)

function onPdfSelect(e) {
    pdfForm.pdf = e.target.files[0] ?? null
}

function uploadPdf() {
    pdfForm.post(route('tours.itinerary.pdf.upload', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            pdfForm.reset()
            if (pdfFileRef.value) pdfFileRef.value.value = ''
        },
    })
}

function deletePdf() {
    if (!confirm('Hapus PDF itinerary ini?')) return
    router.delete(route('tours.itinerary.pdf.delete', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
    })
}

// ── Email Customer ────────────────────────────────────────────────────────────
const emailDialogOpen = ref(false)
const emailForm = useForm({ to: '', subject: '', body: '' })
const customerEmail = computed(() => props.tour.customer?.email ?? '')

function openEmailDialog() {
    const tpl = props.emailTemplates?.[props.tour.status] ?? {}
    emailForm.to      = customerEmail.value
    emailForm.subject = tpl.subject ?? ''
    emailForm.body    = tpl.body    ?? ''
    emailDialogOpen.value = true
}

function sendEmail() {
    emailForm.post(route('tours.email.send', props.tour.id), {
        preserveScroll: true,
        onSuccess: () => { emailDialogOpen.value = false },
    })
}

// ── Quotation (matriks harga + teks customer-facing) ───────────────────────────
let idSeq = 0
function newId(prefix) {
    return `${prefix}-${Date.now().toString(36)}-${idSeq++}`
}

function normalizePricing(p) {
    p = p || {}
    return {
        tiers:  Array.isArray(p.tiers) ? p.tiers : [],
        base:   {
            label:   p.base?.label   ?? 'Tanpa Hotel & Sarapan Pagi',
            enabled: p.base?.enabled ?? false,
            prices:  p.base?.prices  ?? {},
        },
        hotels:    Array.isArray(p.hotels)    ? p.hotels    : [],
        optionals: Array.isArray(p.optionals) ? p.optionals : [],
    }
}

const quotation = reactive({
    pricing:        normalizePricing(props.tour.pricing ? JSON.parse(JSON.stringify(props.tour.pricing)) : null),
    included:       props.tour.included     ?? props.quotationDefaults?.included     ?? '',
    excluded:       props.tour.excluded     ?? props.quotationDefaults?.excluded     ?? '',
    child_policy:   props.tour.child_policy  ?? props.quotationDefaults?.child_policy ?? '',
    terms:          props.tour.terms         ?? props.quotationDefaults?.terms        ?? '',
    price_validity: (props.tour.price_validity ?? '').slice(0, 10),
})

function addTier() {
    quotation.pricing.tiers.push({ id: newId('t'), label: '', note: '' })
}
function removeTier(idx) {
    const [removed] = quotation.pricing.tiers.splice(idx, 1)
    if (removed) {
        delete quotation.pricing.base.prices[removed.id]
        quotation.pricing.hotels.forEach(h => { delete h.prices[removed.id] })
    }
}
function addHotel() {
    quotation.pricing.hotels.push({ id: newId('h'), name: '', room: '', prices: {}, single_sup: null })
}
function removeHotel(idx) {
    quotation.pricing.hotels.splice(idx, 1)
}
function addOptional() {
    quotation.pricing.optionals.push({ label: '', price: null, note: '' })
}
function removeOptional(idx) {
    quotation.pricing.optionals.splice(idx, 1)
}

const quotationSaving = ref(false)
function saveQuotation() {
    quotationSaving.value = true
    router.patch(route('tours.update', props.tour.id), {
        customer_id:    headerForm.customer_id === 'none' ? '' : headerForm.customer_id,
        title:          headerForm.title,
        pax:            headerForm.pax,
        start_date:     headerForm.start_date,
        end_date:       headerForm.end_date,
        status:         headerForm.status,
        sales_person:   headerForm.sales_person,
        default_markup: headerForm.default_markup,
        notes:          headerForm.notes,
        pricing:        quotation.pricing,
        included:       quotation.included,
        excluded:       quotation.excluded,
        child_policy:   quotation.child_policy,
        terms:          quotation.terms,
        price_validity: quotation.price_validity || null,
    }, {
        preserveScroll: true,
        only: ['tour'],
        onFinish: () => { quotationSaving.value = false },
    })
}

// ── Auto-resize textarea directive ───────────────────────────────────────────
const vAutoResize = {
    mounted(el) {
        const resize = () => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px' }
        el.style.overflow = 'hidden'
        resize()
        el.addEventListener('input', resize)
    },
}

onMounted(() => nextTick(() => {
    document.querySelectorAll('.itinerary-desc').forEach(el => {
        el.style.overflow = 'hidden'
        el.style.height = 'auto'
        el.style.height = el.scrollHeight + 'px'
    })
}))
</script>

<template>
    <Head :title="`${tour.code} — Tour Builder`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <Link :href="route('tours.index')" class="text-muted-foreground hover:text-foreground">
                            ← Tours
                        </Link>
                        <span class="text-muted-foreground">/</span>
                        <span class="font-mono font-semibold">{{ tour.code }}</span>
                        <span
                            :class="[STATUS_CONFIG[tour.status]?.class, 'px-2 py-0.5 rounded-full text-xs font-medium']"
                        >
                            {{ STATUS_CONFIG[tour.status]?.label ?? tour.status }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Email Customer — hanya muncul kalau customer punya email -->
                        <Button
                            v-if="customerEmail"
                            variant="outline"
                            size="sm"
                            class="gap-1.5"
                            type="button"
                            @click="openEmailDialog"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            Email Customer
                        </Button>
                        <a :href="route('quotation.preview', tour.id)" target="_blank">
                            <Button variant="outline" size="sm">Preview PDF</Button>
                        </a>
                        <a :href="route('quotation.download', tour.id)">
                            <Button size="sm">⬇ Download Quotation</Button>
                        </a>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                    <!-- ── Left: Header + Items ──────────────────────────────── -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Header Card -->
                        <div class="rounded-lg border bg-white p-5 shadow-sm">
                            <h3 class="font-semibold mb-4">Informasi Tour</h3>
                            <form @submit.prevent="saveHeader" class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label>Customer</Label>
                                        <Select v-model="headerForm.customer_id">
                                            <SelectTrigger><SelectValue placeholder="Pilih customer..." /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">— Tanpa Customer —</SelectItem>
                                                <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                                    {{ c.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label>Status</Label>
                                        <Select v-model="headerForm.status">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="(cfg, key) in STATUS_CONFIG" :key="key" :value="key">
                                                    {{ cfg.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="title">Judul Tour</Label>
                                    <Input id="title" v-model="headerForm.title" placeholder="Mis. 4D3N Manado Heritage" />
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="pax">Pax</Label>
                                        <Input id="pax" type="number" v-model="headerForm.pax" min="1" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="start_date">Tanggal Mulai</Label>
                                        <Input id="start_date" type="date" v-model="headerForm.start_date" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="end_date">Tanggal Selesai</Label>
                                        <Input id="end_date" type="date" v-model="headerForm.end_date" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="sales_person">Sales Person</Label>
                                        <Input id="sales_person" v-model="headerForm.sales_person" placeholder="Nama..." />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="notes">Catatan</Label>
                                        <Input id="notes" v-model="headerForm.notes" placeholder="Catatan internal..." />
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Button type="submit" :disabled="headerForm.processing" size="sm">
                                        Simpan Header
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <!-- Items Card -->
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
                                        <Input
                                            v-model="productSearch"
                                            placeholder="Cari produk..."
                                            class="mt-1"
                                            autofocus
                                        />
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
                                                            Sell: {{ fmt(p.sell) }} {{ p.currency }} / {{ p.unit }}
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

                            <!-- Items table -->
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
                                        <tr
                                            v-for="item in tour.items"
                                            :key="item.id"
                                            class="border-b last:border-0 hover:bg-muted/20"
                                        >
                                            <!-- Hari -->
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    v-model="itemForms[item.id].day_number"
                                                    @change="saveItem(item.id)"
                                                    min="1"
                                                    placeholder="—"
                                                    class="w-12 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                />
                                            </td>
                                            <!-- Deskripsi -->
                                            <td class="px-3 py-1.5">
                                                <div class="flex flex-col gap-0.5">
                                                    <input
                                                        type="text"
                                                        v-model="itemForms[item.id].description"
                                                        @blur="saveItem(item.id)"
                                                        class="border rounded px-2 py-0.5 text-sm w-full focus:outline-none focus:ring-1 focus:ring-primary"
                                                    />
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ TYPE_LABELS[item.product_type] ?? item.product_type }}
                                                    </span>
                                                </div>
                                            </td>
                                            <!-- Qty -->
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    v-model="itemForms[item.id].qty"
                                                    @change="saveItem(item.id)"
                                                    min="1"
                                                    class="w-14 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                />
                                            </td>
                                            <!-- Nights -->
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    v-model="itemForms[item.id].nights"
                                                    @change="saveItem(item.id)"
                                                    min="1"
                                                    class="w-14 border rounded px-1 py-0.5 text-center text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                />
                                            </td>
                                            <!-- Unit Cost -->
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    v-model="itemForms[item.id].unit_cost"
                                                    @change="saveItem(item.id)"
                                                    min="0"
                                                    class="w-28 border rounded px-2 py-0.5 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"
                                                />
                                            </td>
                                            <!-- Unit Sell -->
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    v-model="itemForms[item.id].unit_sell"
                                                    @change="saveItem(item.id)"
                                                    min="0"
                                                    class="w-28 border rounded px-2 py-0.5 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"
                                                />
                                            </td>
                                            <!-- Line Sell (read-only, generated by DB) -->
                                            <td class="px-3 py-1.5 text-right font-mono text-sm font-medium">
                                                {{ fmt(item.line_sell) }}
                                            </td>
                                            <!-- Delete -->
                                            <td class="px-3 py-1.5 text-center">
                                                <button
                                                    type="button"
                                                    @click="deleteItem(item.id)"
                                                    class="text-muted-foreground hover:text-destructive transition-colors"
                                                    title="Hapus item"
                                                >
                                                    ✕
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── Operasional Card ──────────────────────────────── -->
                        <div class="rounded-lg border bg-white shadow-sm">
                            <div class="flex items-center justify-between px-5 py-4 border-b">
                                <h3 class="font-semibold">Operasional</h3>
                                <Button size="sm" variant="outline" @click="openAddAssignment">
                                    + Tambah Guide / Driver
                                </Button>
                            </div>

                            <!-- Assignment list -->
                            <div v-if="!tour.assignments?.length"
                                class="px-5 py-8 text-center text-sm text-muted-foreground">
                                Belum ada guide / driver ditugaskan.
                            </div>
                            <div v-else class="divide-y">
                                <div v-for="a in tour.assignments" :key="a.id"
                                    class="flex items-center gap-4 px-5 py-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-medium text-sm">{{ a.person_name ?? '—' }}</span>
                                            <span :class="[ROLE_COLORS[a.role], 'text-xs px-2 py-0.5 rounded-full font-medium']">
                                                {{ ROLE_LABELS[a.role] ?? a.role }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-0.5 flex flex-wrap gap-3">
                                            <span v-if="a.phone">📱 {{ a.phone }}</span>
                                            <span v-if="a.vehicle">🚐 {{ a.vehicle }}</span>
                                            <span v-if="a.pickup_time">🕐 {{ a.pickup_time }}</span>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <Button size="sm" variant="outline" @click="openEditAssignment(a)">
                                            Edit
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="deleteAssignment(a.id)">
                                            Hapus
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Manifest share link -->
                            <div class="px-5 py-4 border-t bg-muted/20">
                                <p class="text-xs font-semibold text-muted-foreground mb-2">SHARE MANIFEST KE GUIDE / DRIVER</p>
                                <div class="flex gap-2">
                                    <input
                                        :value="manifestUrl"
                                        readonly
                                        class="flex-1 border rounded px-3 py-1.5 text-xs font-mono bg-white truncate focus:outline-none"
                                    />
                                    <Button size="sm" variant="outline" @click="copyManifestLink">
                                        {{ linkCopied ? '✓ Disalin!' : 'Salin Link' }}
                                    </Button>
                                    <a :href="manifestUrl" target="_blank">
                                        <Button size="sm" variant="outline">Buka</Button>
                                    </a>
                                </div>
                                <p class="text-xs text-muted-foreground mt-1.5">
                                    Link ini aman dibagikan via WhatsApp — tidak perlu login.
                                </p>
                            </div>
                        </div>

                        <!-- ── Itinerary Card ─────────────────────────────────── -->
                        <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-4 border-b">
                                <h3 class="font-semibold">Itinerary</h3>
                                <Button type="button" size="sm" variant="outline" @click="addDay">
                                    + Tambah Hari
                                </Button>
                            </div>

                            <!-- Day list -->
                            <div v-if="!itineraryDays.length"
                                class="px-5 py-8 text-center text-sm text-muted-foreground">
                                Belum ada itinerary. Klik "Tambah Hari" untuk memulai.
                            </div>

                            <div v-else class="divide-y">
                                <div
                                    v-for="(day, i) in itineraryDays"
                                    :key="i"
                                    class="px-5 py-4 space-y-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-xs font-bold">
                                            {{ day.day_number }}
                                        </span>
                                        <Input
                                            v-model="day.title"
                                            placeholder="Judul hari ini (mis. Arrival & City Tour)"
                                            class="flex-1"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="text-destructive hover:text-destructive shrink-0"
                                            @click="removeDay(i)"
                                        >
                                            ✕
                                        </Button>
                                    </div>
                                    <textarea
                                        v-model="day.description"
                                        v-auto-resize
                                        placeholder="Aktivitas, jadwal, tempat yang dikunjungi..."
                                        class="itinerary-desc ml-11 w-[calc(100%-2.75rem)] min-h-[80px] rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    />
                                </div>
                            </div>

                            <div v-if="itineraryDays.length" class="px-5 py-3 bg-muted/20 border-t flex justify-end">
                                <Button type="button" size="sm" @click="saveItinerary">
                                    Simpan Itinerary
                                </Button>
                            </div>

                            <!-- ── Hourly Itinerary ────────────────────────────────── -->
                            <div v-if="itineraryDays.length" class="px-5 py-4 border-t space-y-3">
                                <p class="text-xs font-semibold text-muted-foreground">ITINERARY JAM-KE-JAM (Opsional)</p>

                                <!-- Hourly activities per day -->
                                <div class="space-y-2">
                                    <div v-for="day in itineraryDays" :key="day.day_number" class="border rounded-md overflow-hidden">
                                        <button
                                            type="button"
                                            @click="toggleHourDay(day.day_number)"
                                            class="w-full flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-muted/50 bg-muted/20"
                                        >
                                            <span class="flex items-center gap-2 flex-1 text-left">
                                                <span class="text-xs font-semibold">Hari {{ day.day_number }}:</span>
                                                <span class="text-sm">{{ day.title || '(Belum ada judul)' }}</span>
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ (groupedHours[day.day_number]?.length || 0) }} aktivitas
                                            </span>
                                            <span class="text-muted-foreground text-lg shrink-0">
                                                {{ isHourDayExpanded(day.day_number) ? '−' : '+' }}
                                            </span>
                                        </button>

                                        <div v-if="isHourDayExpanded(day.day_number)" class="bg-white space-y-2 p-3 border-t">
                                            <!-- Form untuk add/edit hour -->
                                            <div v-if="!editingHourId || hourForm.day_number === day.day_number" class="space-y-2 p-3 rounded-md border bg-muted/10">
                                                <div class="grid grid-cols-3 gap-2">
                                                    <div class="space-y-1">
                                                        <Label class="text-xs">Mulai</Label>
                                                        <Input type="time" v-model="hourForm.start_time" class="h-8 text-sm" />
                                                    </div>
                                                    <div class="space-y-1">
                                                        <Label class="text-xs">Selesai</Label>
                                                        <Input type="time" v-model="hourForm.end_time" class="h-8 text-sm" />
                                                    </div>
                                                    <div class="space-y-1">
                                                        <Label class="text-xs">Aktivitas</Label>
                                                        <Input v-model="hourForm.activity" placeholder="Mis. Breakfast" class="h-8 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs">Catatan</Label>
                                                    <Input v-model="hourForm.notes" placeholder="Lokasi, detail, dll" class="h-8 text-sm" />
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button type="button" size="sm" @click="saveHour" class="h-7 text-xs flex-1">
                                                        {{ editingHourId ? '✓ Update' : '+ Tambah' }}
                                                    </Button>
                                                    <Button v-if="editingHourId" type="button" size="sm" variant="outline" @click="cancelHourForm" class="h-7 text-xs">
                                                        Batal
                                                    </Button>
                                                </div>
                                            </div>

                                            <!-- List activities untuk hari ini -->
                                            <div v-if="groupedHours[day.day_number]?.length" class="space-y-1.5">
                                                <div v-for="hour in groupedHours[day.day_number]" :key="hour.id" class="flex items-start justify-between gap-2 p-2 rounded text-xs bg-white border">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium">
                                                            {{ hour.start_time }}<span v-if="hour.end_time">–{{ hour.end_time }}</span>
                                                        </div>
                                                        <div class="text-muted-foreground">{{ hour.activity }}</div>
                                                        <div v-if="hour.notes" class="text-muted-foreground text-xs">{{ hour.notes }}</div>
                                                    </div>
                                                    <div class="flex gap-1 shrink-0">
                                                        <button
                                                            type="button"
                                                            @click="startEditHour(hour)"
                                                            class="text-blue-600 hover:text-blue-700"
                                                        >
                                                            ✎
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="deleteHour(hour.id)"
                                                            class="text-destructive hover:text-destructive/80"
                                                        >
                                                            ✕
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Add button jika belum ada form -->
                                            <button
                                                v-if="!editingHourId || hourForm.day_number !== day.day_number"
                                                type="button"
                                                @click="startAddHour(day.day_number)"
                                                class="text-xs text-primary hover:text-primary/80 font-medium pt-1"
                                            >
                                                + Tambah Aktivitas
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PDF Upload -->
                            <div class="px-5 py-4 border-t space-y-3">
                                <p class="text-xs font-semibold text-muted-foreground">PDF ITINERARY LENGKAP</p>

                                <!-- PDF sudah ada -->
                                <div v-if="tour.itinerary_pdf_url" class="flex items-center gap-3 p-3 rounded-md bg-muted/30 border">
                                    <svg class="h-8 w-8 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 17.5h7v-1h-7v1zm0-2.5h7v-1h-7v1zm0-2.5h4v-1h-4v1z"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate">{{ tour.code }}-itinerary.pdf</p>
                                        <p class="text-xs text-muted-foreground">PDF tersedia</p>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <a :href="route('tours.itinerary.pdf.download', tour.id)" target="_blank">
                                            <Button type="button" size="sm" variant="outline">⬇ Unduh</Button>
                                        </a>
                                        <Button type="button" size="sm" variant="destructive" @click="deletePdf">
                                            Hapus
                                        </Button>
                                    </div>
                                </div>

                                <!-- Upload baru -->
                                <div class="flex items-center gap-3">
                                    <input
                                        ref="pdfFileRef"
                                        type="file"
                                        accept=".pdf"
                                        class="flex-1 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:cursor-pointer cursor-pointer"
                                        @change="onPdfSelect"
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        :disabled="!pdfForm.pdf || pdfForm.processing"
                                        @click="uploadPdf"
                                    >
                                        Upload PDF
                                    </Button>
                                </div>
                                <p v-if="pdfForm.errors.pdf" class="text-xs text-destructive">{{ pdfForm.errors.pdf }}</p>
                                <p class="text-xs text-muted-foreground">Maks. 20 MB. PDF ini dapat diunduh oleh admin & sales.</p>
                            </div>
                        </div>

                        <!-- ── Quotation / Penawaran ─────────────────────────── -->
                        <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-4 border-b">
                                <div>
                                    <h3 class="font-semibold">Quotation / Penawaran</h3>
                                    <p class="text-xs text-muted-foreground">Matriks harga & syarat yang tampil di PDF untuk customer</p>
                                </div>
                                <Button size="sm" :disabled="quotationSaving" @click="saveQuotation">
                                    {{ quotationSaving ? 'Menyimpan…' : 'Simpan Quotation' }}
                                </Button>
                            </div>
                            <div class="p-5 space-y-6">

                                <!-- Tier pax (kolom matriks) -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label>Kolom Tier Pax</Label>
                                        <Button type="button" variant="outline" size="sm" @click="addTier">+ Tier</Button>
                                    </div>
                                    <p v-if="!quotation.pricing.tiers.length" class="text-xs text-muted-foreground">
                                        Belum ada tier. Contoh: "Min 2 pax" (New Avanza), "Min 4 pax" (Innova), "Min 8 pax" (Hiace).
                                    </p>
                                    <div v-for="(tier, ti) in quotation.pricing.tiers" :key="tier.id" class="flex items-center gap-2">
                                        <Input v-model="tier.label" placeholder="Min 4 pax" class="w-40" />
                                        <Input v-model="tier.note" placeholder="Innova Reborn" class="flex-1" />
                                        <Button type="button" variant="ghost" size="sm" @click="removeTier(ti)">✕</Button>
                                    </div>
                                </div>

                                <!-- Matriks harga -->
                                <template v-if="quotation.pricing.tiers.length">
                                    <!-- Baris tanpa hotel -->
                                    <div class="rounded-md border p-3 space-y-2">
                                        <label class="flex items-center gap-2 text-sm font-medium">
                                            <input type="checkbox" v-model="quotation.pricing.base.enabled" class="rounded border-gray-300" />
                                            Tampilkan baris "Tanpa Hotel"
                                        </label>
                                        <div v-if="quotation.pricing.base.enabled" class="space-y-2">
                                            <Input v-model="quotation.pricing.base.label" placeholder="Tanpa Hotel & Sarapan Pagi" />
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <div v-for="tier in quotation.pricing.tiers" :key="tier.id" class="space-y-1">
                                                    <span class="text-xs text-muted-foreground">{{ tier.label || 'Tier' }}</span>
                                                    <Input type="number" step="any" v-model.number="quotation.pricing.base.prices[tier.id]" placeholder="0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hotel rows -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <Label>Harga per Hotel (per pax)</Label>
                                            <Button type="button" variant="outline" size="sm" @click="addHotel">+ Hotel</Button>
                                        </div>
                                        <div v-for="(hotel, hi) in quotation.pricing.hotels" :key="hotel.id" class="rounded-md border p-3 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <Input v-model="hotel.name" placeholder="Aston Hotel 4*" class="flex-1" />
                                                <Input v-model="hotel.room" placeholder="Superior Room" class="w-40" />
                                                <Button type="button" variant="ghost" size="sm" @click="removeHotel(hi)">✕</Button>
                                            </div>
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                <div v-for="tier in quotation.pricing.tiers" :key="tier.id" class="space-y-1">
                                                    <span class="text-xs text-muted-foreground">{{ tier.label || 'Tier' }}</span>
                                                    <Input type="number" step="any" v-model.number="hotel.prices[tier.id]" placeholder="0" />
                                                </div>
                                                <div class="space-y-1">
                                                    <span class="text-xs text-muted-foreground">Single Sup.</span>
                                                    <Input type="number" step="any" v-model.number="hotel.single_sup" placeholder="0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <p v-else class="text-xs text-muted-foreground">
                                    Tambahkan minimal 1 tier pax untuk mengisi matriks harga. Jika dikosongkan, PDF memakai harga tunggal dari total item tour.
                                </p>

                                <!-- Optional tour -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label>Optional Tour</Label>
                                        <Button type="button" variant="outline" size="sm" @click="addOptional">+ Optional</Button>
                                    </div>
                                    <div v-for="(opt, oi) in quotation.pricing.optionals" :key="oi" class="flex items-center gap-2">
                                        <Input v-model="opt.label" placeholder="Snorkeling" class="w-40" />
                                        <Input type="number" step="any" v-model.number="opt.price" placeholder="150000" class="w-32" />
                                        <Input v-model="opt.note" placeholder="termasuk mask, snorkel & fins" class="flex-1" />
                                        <Button type="button" variant="ghost" size="sm" @click="removeOptional(oi)">✕</Button>
                                    </div>
                                </div>

                                <!-- Validity -->
                                <div class="space-y-1.5 max-w-xs">
                                    <Label>Harga berlaku s/d</Label>
                                    <Input type="date" v-model="quotation.price_validity" />
                                </div>

                                <!-- Teks customer-facing -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label>Harga Sudah Termasuk <span class="text-xs text-muted-foreground font-normal">(1 item per baris)</span></Label>
                                        <Textarea v-model="quotation.included" rows="7" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label>Harga Belum Termasuk <span class="text-xs text-muted-foreground font-normal">(1 item per baris)</span></Label>
                                        <Textarea v-model="quotation.excluded" rows="7" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <Label>Kebijakan Anak <span class="text-xs text-muted-foreground font-normal">(1 item per baris)</span></Label>
                                    <Textarea v-model="quotation.child_policy" rows="4" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label>Syarat &amp; Ketentuan</Label>
                                    <Textarea v-model="quotation.terms" rows="6" />
                                </div>
                            </div>
                        </div>

                        <!-- ── Riwayat / Activity Log ────────────────────────── -->
                        <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-4 border-b">
                                <h3 class="font-semibold">Riwayat Revisi</h3>
                                <button
                                    type="button"
                                    @click="showHistoryForm = !showHistoryForm"
                                    class="text-xs text-primary font-medium hover:underline"
                                >
                                    {{ showHistoryForm ? 'Tutup' : '+ Tambah Catatan' }}
                                </button>
                            </div>

                            <!-- Form tambah riwayat -->
                            <div v-if="showHistoryForm" class="px-5 py-4 border-b bg-muted/20 space-y-3">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium">Tipe Catatan</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="(label, key) in HISTORY_TYPES"
                                            :key="key"
                                            type="button"
                                            @click="historyForm.type = key"
                                            :class="[
                                                'px-3 py-1 rounded-full text-xs border transition-colors',
                                                historyForm.type === key
                                                    ? 'bg-primary text-primary-foreground border-primary'
                                                    : 'bg-white text-muted-foreground hover:border-muted-foreground/60'
                                            ]"
                                        >
                                            {{ label }}
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-medium">Keterangan</label>
                                    <Textarea
                                        v-model="historyForm.description"
                                        rows="3"
                                        placeholder="Mis. Customer minta perubahan hotel dari Aston ke Novotel, dan tambah 1 hari di Tomohon..."
                                    />
                                    <p v-if="historyForm.errors.description" class="text-xs text-destructive">{{ historyForm.errors.description }}</p>
                                </div>
                                <Button type="button" size="sm" @click="submitHistory" :disabled="historyForm.processing" class="w-full">
                                    Simpan Catatan
                                </Button>
                            </div>

                            <!-- Timeline -->
                            <div v-if="!tour.histories?.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                                Belum ada riwayat. Tambahkan catatan pertama.
                            </div>
                            <div v-else class="divide-y">
                                <div
                                    v-for="h in tour.histories"
                                    :key="h.id"
                                    class="px-5 py-4 flex gap-3"
                                >
                                    <!-- Icon dot -->
                                    <div class="mt-0.5 shrink-0">
                                        <span :class="['inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold', HISTORY_COLORS[h.type] ?? 'bg-gray-100 text-gray-600']">
                                            {{ HISTORY_ICONS[h.type] ?? '•' }}
                                        </span>
                                    </div>

                                    <div class="flex-1 min-w-0 space-y-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-semibold">{{ HISTORY_TYPES[h.type] ?? h.type }}</span>
                                                <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', STATUS_CONFIG[h.status_snapshot]?.class ?? 'bg-gray-100 text-gray-700']">
                                                    {{ STATUS_CONFIG[h.status_snapshot]?.label ?? h.status_snapshot }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-xs text-muted-foreground">{{ fmtDateTime(h.created_at) }}</span>
                                                <button
                                                    type="button"
                                                    @click="deleteHistory(h.id)"
                                                    class="text-muted-foreground hover:text-destructive text-xs"
                                                >✕</button>
                                            </div>
                                        </div>
                                        <p class="text-sm text-foreground leading-relaxed">{{ h.description }}</p>
                                        <p v-if="h.created_by" class="text-xs text-muted-foreground">— {{ h.created_by }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ── Right: Costing Panel ──────────────────────────────── -->
                    <div class="lg:sticky lg:top-6 space-y-4">
                        <!-- Costing Summary -->
                        <div class="rounded-lg border bg-white p-5 shadow-sm">
                            <h3 class="font-semibold mb-4">Ringkasan Biaya</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Total Cost (Modal)</span>
                                    <span class="font-mono">{{ fmt(tour.total_cost) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Total Sell (Jual)</span>
                                    <span class="font-mono font-medium">{{ fmt(tour.total_sell) }}</span>
                                </div>
                                <div class="border-t pt-3">
                                    <div class="flex justify-between">
                                        <span class="font-semibold">Profit</span>
                                        <span
                                            class="font-mono font-bold text-lg"
                                            :class="tour.profit >= 0 ? 'text-green-700' : 'text-red-600'"
                                        >
                                            {{ fmt(tour.profit) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between mt-1">
                                        <span class="text-sm text-muted-foreground">Margin</span>
                                        <span
                                            class="text-sm font-semibold"
                                            :class="tour.margin >= 0 ? 'text-green-700' : 'text-red-600'"
                                        >
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
                                <button
                                    v-for="(cfg, key) in STATUS_CONFIG"
                                    :key="key"
                                    type="button"
                                    @click="headerForm.status = key; saveHeader()"
                                    :class="[
                                        'w-full text-left px-3 py-1.5 rounded-md text-xs transition-colors',
                                        tour.status === key
                                            ? cfg.class + ' font-semibold'
                                            : 'hover:bg-muted text-muted-foreground',
                                    ]"
                                >
                                    {{ cfg.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Assignment Dialog ── -->
        <Dialog v-model:open="assignDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingAssignment ? 'Edit Assignment' : 'Tambah Guide / Driver' }}
                    </DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitAssignment" class="space-y-4 mt-2">
                    <!-- Link ke akun field staff (opsional) -->
                    <div v-if="fieldUsers?.length" class="space-y-1.5">
                        <Label>Link ke Akun (Opsional)</Label>
                        <Select :model-value="assignForm.user_id?.toString() ?? ''" @update:model-value="onFieldUserSelect">
                            <SelectTrigger><SelectValue placeholder="— Pilih atau isi manual —" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="fu in fieldUsers"
                                    :key="fu.id"
                                    :value="fu.id.toString()"
                                >
                                    {{ fu.name }}
                                    <span class="ml-1 text-xs text-gray-400 capitalize">· {{ fu.role.replace('_', ' ') }}</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Role</Label>
                        <Select v-model="assignForm.role">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="guide">Tour Guide</SelectItem>
                                <SelectItem value="driver">Driver</SelectItem>
                                <SelectItem value="tour_leader">Tour Leader</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="a_name">Nama</Label>
                            <Input id="a_name" v-model="assignForm.person_name" placeholder="Nama lengkap" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="a_phone">Telepon / WA</Label>
                            <Input id="a_phone" v-model="assignForm.phone" placeholder="08xx..." />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="a_vehicle">Kendaraan</Label>
                            <Input id="a_vehicle" v-model="assignForm.vehicle" placeholder="Mis. Innova B 1234 XX" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="a_pickup">Pickup Time</Label>
                            <Input id="a_pickup" type="time" v-model="assignForm.pickup_time" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="a_notes">Catatan</Label>
                        <Input id="a_notes" v-model="assignForm.notes" placeholder="Info tambahan..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="assignDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="assignForm.processing">
                            {{ editingAssignment ? 'Simpan' : 'Tambahkan' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Email Customer Dialog ── -->
        <Dialog v-model:open="emailDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        Email ke Customer
                    </DialogTitle>
                </DialogHeader>

                <form @submit.prevent="sendEmail" class="space-y-4 mt-2">
                    <!-- Penerima -->
                    <div class="space-y-1.5">
                        <Label>Kepada (To) <span class="text-destructive">*</span></Label>
                        <Input v-model="emailForm.to" type="email" placeholder="email@customer.com" />
                        <p v-if="emailForm.errors.to" class="text-xs text-destructive">{{ emailForm.errors.to }}</p>
                    </div>

                    <!-- Subject -->
                    <div class="space-y-1.5">
                        <Label>Subject <span class="text-destructive">*</span></Label>
                        <Input v-model="emailForm.subject" placeholder="Subjek email..." />
                        <p v-if="emailForm.errors.subject" class="text-xs text-destructive">{{ emailForm.errors.subject }}</p>
                    </div>

                    <!-- Body -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label>Isi Email <span class="text-destructive">*</span></Label>
                            <span class="text-xs text-muted-foreground">Template otomatis dari status: <strong>{{ STATUS_CONFIG[tour.status]?.label }}</strong></span>
                        </div>
                        <Textarea v-model="emailForm.body" rows="12" class="font-mono text-sm" />
                        <p v-if="emailForm.errors.body" class="text-xs text-destructive">{{ emailForm.errors.body }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="emailDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="emailForm.processing" class="gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                            </svg>
                            Kirim Email
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

    </AuthenticatedLayout>
</template>
