<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ tour: Object, suppliers: { type: Array, default: () => [] } })

const CATEGORY_LABEL = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Wisata', other: 'Lainnya',
}
const STATUS_BADGE = {
    pending:  { label: '🟡 Menunggu',  cls: 'bg-amber-100 text-amber-700' },
    approved: { label: '🟢 Disetujui', cls: 'bg-green-100 text-green-700' },
    rejected: { label: '🔴 Ditolak',   cls: 'bg-red-100 text-red-600' },
}

function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// ── Ajukan biaya tambahan ────────────────────────────────────────────────────
const dialogOpen = ref(false)
const form = useForm({
    supplier_id: 'none',
    category:    'other',
    description: '',
    amount:      '',
    notes:       '',
})

function openDialog() {
    form.reset()
    form.supplier_id = 'none'
    form.category     = 'other'
    dialogOpen.value = true
}

function submit() {
    if (form.supplier_id === 'none') form.supplier_id = ''
    form.post(route('cost-requests.store', props.tour.id), {
        preserveScroll: true, only: ['tour'],
        onSuccess: () => { dialogOpen.value = false; form.reset() },
    })
}

async function cancelRequest(id) {
    if (await confirm({ title: 'Batalkan permintaan ini?', confirmLabel: 'Batalkan' })) {
        router.delete(route('cost-requests.destroy', id), { preserveScroll: true, only: ['tour'] })
    }
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <h3 class="font-semibold">Biaya Tambahan</h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Ada biaya tak terduga saat tour berjalan? Ajukan di sini — akuntan akan verifikasi.
                </p>
            </div>
            <Button size="sm" @click="openDialog">+ Ajukan Biaya Tambahan</Button>
        </div>

        <div v-if="!tour.cost_requests?.length" class="px-5 py-6 text-sm text-muted-foreground text-center">
            Belum ada pengajuan.
        </div>

        <div v-else class="divide-y">
            <div v-for="cr in tour.cost_requests" :key="cr.id" class="px-5 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-gray-800">{{ cr.description }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                {{ CATEGORY_LABEL[cr.category] ?? cr.category }}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="STATUS_BADGE[cr.status]?.cls">
                                {{ STATUS_BADGE[cr.status]?.label }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Perkiraan {{ fmtRp(cr.amount) }}
                            <template v-if="cr.supplier"> · {{ cr.supplier.name }}</template>
                            · {{ fmtDate(cr.created_at) }}
                        </p>
                        <p v-if="cr.status === 'rejected' && cr.review_notes" class="text-xs text-red-600 mt-1">
                            Alasan ditolak: {{ cr.review_notes }}
                        </p>
                        <p v-if="cr.invoice" class="text-xs text-blue-700 mt-1">
                            📄 Ditambahkan ke invoice {{ cr.invoice.number }} sebagai biaya tambahan
                        </p>
                    </div>
                    <Button v-if="cr.status === 'pending'" size="sm" variant="outline" @click="cancelRequest(cr.id)">
                        Batalkan
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Dialog: Ajukan Biaya Tambahan ── -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Ajukan Biaya Tambahan</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-3 mt-2">
                <div class="space-y-1.5">
                    <Label>Supplier <span class="text-xs text-muted-foreground font-normal">(opsional)</span></Label>
                    <Select v-model="form.supplier_id">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">— Tanpa Supplier —</SelectItem>
                            <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label>Kategori</Label>
                    <Select v-model="form.category">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, key) in CATEGORY_LABEL" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label>Deskripsi <span class="text-destructive">*</span></Label>
                    <Input v-model="form.description" placeholder="Mis. Sewa penginapan tambahan 1 malam" />
                    <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Nominal Perkiraan (IDR) <span class="text-destructive">*</span></Label>
                    <Input type="number" v-model="form.amount" min="0" step="any" placeholder="0" />
                    <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Catatan <span class="text-xs text-muted-foreground font-normal">(opsional)</span></Label>
                    <Textarea v-model="form.notes" rows="2" placeholder="Konteks/alasan biaya ini muncul..." />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing">Ajukan</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
