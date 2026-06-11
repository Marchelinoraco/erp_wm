<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/Components/ui/dialog'

const props = defineProps({
    tours:      Array,
    suppliers:  Array,
    stats:      Object,
    categories: Array,
    statuses:   Object,
})

const CATEGORY_LABEL = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restoran', attraction: 'Atraksi', agent: 'Agent', other: 'Lainnya',
}
const CATEGORY_BADGE = {
    hotel: 'bg-blue-100 text-blue-700', transport: 'bg-amber-100 text-amber-700',
    guide: 'bg-emerald-100 text-emerald-700', restaurant: 'bg-rose-100 text-rose-700',
    attraction: 'bg-violet-100 text-violet-700', agent: 'bg-cyan-100 text-cyan-700', other: 'bg-gray-100 text-gray-700',
}
const STATUS_BADGE = {
    pending: 'bg-orange-100 text-orange-700',
    booked: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
}

const fmt = (v) => new Intl.NumberFormat('id-ID').format(Math.round(v || 0))
function fmtDate(d) {
    return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

// --- Mark as booked / edit booked ---
const bookTarget = ref(null)
const bookForm = useForm({
    status: 'booked',
    actual_cost: 0,
    booking_ref: '',
    notes: '',
})
function openBook(booking) {
    bookTarget.value = booking
    bookForm.status = 'booked'
    bookForm.actual_cost = booking.actual_cost ?? booking.est_cost ?? 0
    bookForm.booking_ref = booking.booking_ref ?? ''
    bookForm.notes = booking.notes ?? ''
    bookForm.clearErrors()
}
function submitBook() {
    bookForm.patch(route('bookings.update', bookTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { bookTarget.value = null },
    })
}

// --- Manual add booking ---
const addTour = ref(null)
const addForm = useForm({
    supplier_id: 'none',
    description: '',
    category: 'other',
    est_cost: 0,
    notes: '',
})
function openAdd(tour) {
    addTour.value = tour
    addForm.reset()
    addForm.supplier_id = 'none'
    addForm.category = 'other'
    addForm.clearErrors()
}
function submitAdd() {
    addForm
        .transform((d) => ({ ...d, supplier_id: d.supplier_id === 'none' ? null : d.supplier_id }))
        .post(route('bookings.store', addTour.value.id), {
            preserveScroll: true,
            onSuccess: () => { addTour.value = null },
        })
}
// auto-isi deskripsi & kategori dari supplier terpilih
function onSupplierPick(val) {
    addForm.supplier_id = val
    if (val !== 'none') {
        const s = props.suppliers.find((x) => String(x.id) === String(val))
        if (s) {
            if (!addForm.description) addForm.description = s.name
            if (s.type && props.categories.includes(s.type)) addForm.category = s.type
        }
    }
}

// --- Quick status actions ---
function revertToPending(booking) {
    if (!confirm('Kembalikan ke "Belum di-booking"? Tagihan supplier yang belum dibayar akan dihapus dari Keuangan.')) return
    router.patch(route('bookings.update', booking.id), { status: 'pending' }, { preserveScroll: true })
}
function cancelBooking(booking) {
    if (!confirm('Tandai booking ini BATAL? Tagihan supplier yang belum dibayar akan dihapus dari Keuangan.')) return
    router.patch(route('bookings.update', booking.id), { status: 'cancelled' }, { preserveScroll: true })
}
function deleteBooking(booking) {
    if (!confirm('Hapus baris booking ini permanen?')) return
    router.delete(route('bookings.destroy', booking.id), { preserveScroll: true })
}

const hasAnyBooking = computed(() => props.tours.some((t) => t.bookings.length > 0))
</script>

<template>
    <Head title="Booking Operasional" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">Booking Operasional</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-orange-500">{{ stats.pending }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Belum di-booking</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-green-600">{{ stats.booked }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Sudah di-booking</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-base font-bold text-gray-700">IDR {{ fmt(stats.est_total) }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Estimasi biaya</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-base font-bold text-blue-700">IDR {{ fmt(stats.actual_total) }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Sudah di-deal</div>
                    </div>
                </div>

                <!-- Empty -->
                <div v-if="tours.length === 0" class="rounded-lg border bg-white p-10 text-center text-muted-foreground shadow-sm">
                    Belum ada tour <strong>confirmed</strong>. Booking otomatis muncul saat inquiry dikonfirmasi.
                </div>

                <div v-else-if="!hasAnyBooking" class="rounded-lg border border-dashed bg-amber-50/50 p-5 text-sm text-amber-800">
                    Ada tour confirmed tapi belum ada item/supplier untuk di-booking. Tambahkan booking manual di kartu tour di bawah.
                </div>

                <!-- Tour cards -->
                <div
                    v-for="t in tours"
                    :key="t.id"
                    class="rounded-lg border bg-white shadow-sm overflow-hidden"
                >
                    <!-- Tour header -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-gray-50/70 px-4 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <Link :href="route('tours.edit', t.id)" class="font-semibold text-gray-900 hover:underline">
                                    {{ t.code }}
                                </Link>
                                <span class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">{{ t.type_label }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground mt-0.5 truncate">
                                {{ t.title || '(tanpa judul)' }}
                                <span v-if="t.customer"> · {{ t.customer }}</span>
                                · {{ t.pax }} pax
                                <span v-if="t.start_date"> · {{ fmtDate(t.start_date) }}</span>
                            </div>
                        </div>
                        <Button size="sm" variant="outline" @click="openAdd(t)">+ Booking manual</Button>
                    </div>

                    <!-- Booking rows -->
                    <div v-if="t.bookings.length === 0" class="px-4 py-5 text-center text-sm text-muted-foreground">
                        Belum ada booking untuk tour ini.
                    </div>

                    <div v-else class="divide-y">
                        <div v-for="b in t.bookings" :key="b.id" class="px-4 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <!-- Left: info -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ b.description }}</span>
                                        <span :class="['rounded-full px-2 py-0.5 text-[10px] font-medium', CATEGORY_BADGE[b.category]]">
                                            {{ CATEGORY_LABEL[b.category] }}
                                        </span>
                                        <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold', STATUS_BADGE[b.status]]">
                                            {{ statuses[b.status] }}
                                        </span>
                                    </div>

                                    <p v-if="b.notes" class="mt-1 text-xs text-muted-foreground whitespace-pre-line">{{ b.notes }}</p>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
                                        <span>Estimasi: <b class="text-gray-700">IDR {{ fmt(b.est_cost) }}</b></span>
                                        <span v-if="b.actual_cost !== null">Deal: <b class="text-blue-700">IDR {{ fmt(b.actual_cost) }}</b></span>
                                        <span v-if="b.booking_ref">Ref: <b class="text-gray-700">{{ b.booking_ref }}</b></span>
                                        <span v-if="b.booked_by">oleh {{ b.booked_by }}</span>
                                        <span v-if="b.bill_id" class="inline-flex items-center gap-1 text-green-700">
                                            ✓ Tagihan dibuat<template v-if="b.bill_status"> ({{ b.bill_status }})</template>
                                        </span>
                                    </div>
                                </div>

                                <!-- Right: actions -->
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <template v-if="b.status === 'pending'">
                                        <Button size="sm" @click="openBook(b)">Booking</Button>
                                        <button type="button" class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" title="Batal" @click="cancelBooking(b)">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </template>

                                    <template v-else-if="b.status === 'booked'">
                                        <Button size="sm" variant="outline" @click="openBook(b)">Edit</Button>
                                        <button type="button" class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" title="Kembalikan ke belum di-booking" @click="revertToPending(b)">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                        </button>
                                    </template>

                                    <template v-else>
                                        <Button size="sm" variant="outline" @click="router.patch(route('bookings.update', b.id), { status: 'pending' }, { preserveScroll: true })">Aktifkan</Button>
                                    </template>

                                    <button type="button" class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Hapus" @click="deleteBooking(b)">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Booking dialog (mark booked / edit) -->
        <Dialog :open="!!bookTarget" @update:open="v => { if (!v) bookTarget = null }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Booking Supplier</DialogTitle>
                    <DialogDescription v-if="bookTarget">
                        {{ bookTarget.description }} — saat disimpan, otomatis dibuat tagihan (AP) di menu Keuangan.
                    </DialogDescription>
                </DialogHeader>
                <form v-if="bookTarget" @submit.prevent="submitBook" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Harga Deal (IDR) <span class="text-destructive">*</span></Label>
                        <Input type="number" min="0" step="1000" v-model="bookForm.actual_cost" />
                        <p class="text-xs text-muted-foreground">Estimasi dari item: IDR {{ fmt(bookTarget.est_cost) }}</p>
                        <p v-if="bookForm.errors.actual_cost" class="text-xs text-destructive">{{ bookForm.errors.actual_cost }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>No. Konfirmasi / Ref Supplier</Label>
                        <Input v-model="bookForm.booking_ref" placeholder="Mis. BK-1029 / voucher hotel" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Catatan</Label>
                        <Textarea v-model="bookForm.notes" rows="2" placeholder="Detail kamar, jam jemput, dll" />
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="bookTarget = null">Batal</Button>
                        <Button type="submit" :disabled="bookForm.processing">Simpan & buat tagihan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Manual add dialog -->
        <Dialog :open="!!addTour" @update:open="v => { if (!v) addTour = null }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Tambah Booking Manual</DialogTitle>
                    <DialogDescription v-if="addTour">Untuk tour {{ addTour.code }}</DialogDescription>
                </DialogHeader>
                <form v-if="addTour" @submit.prevent="submitAdd" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Supplier (opsional)</Label>
                        <Select :model-value="addForm.supplier_id" @update:model-value="onSupplierPick">
                            <SelectTrigger><SelectValue placeholder="Pilih supplier..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">— Tanpa supplier —</SelectItem>
                                <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Deskripsi <span class="text-destructive">*</span></Label>
                        <Input v-model="addForm.description" placeholder="Mis. Hotel Sutan Raja 3 malam" />
                        <p v-if="addForm.errors.description" class="text-xs text-destructive">{{ addForm.errors.description }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Kategori</Label>
                            <Select v-model="addForm.category">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in categories" :key="c" :value="c">{{ CATEGORY_LABEL[c] }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Estimasi (IDR)</Label>
                            <Input type="number" min="0" step="1000" v-model="addForm.est_cost" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="addTour = null">Batal</Button>
                        <Button type="submit" :disabled="addForm.processing">Tambah</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
