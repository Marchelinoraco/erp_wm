<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import { TYPE_FIELDS, TYPE_BADGE, typeLabel, emptyDetails } from '@/lib/inquiryTypes'
import { STATUS_CONFIG } from '@/lib/tourConstants'

const props = defineProps({ tour: Object, customers: Array })

const tourType     = props.tour.type ?? 'tour'
const detailFields = TYPE_FIELDS[tourType] ?? []
const isTour       = tourType === 'tour'

const isMice = tourType === 'mice'

const headerForm = useForm({
    type:           tourType,
    tour_direction: props.tour.tour_direction ?? 'inbound',
    customer_id:    props.tour.customer_id ? String(props.tour.customer_id) : 'none',
    guest_name:     props.tour.guest_name     ?? '',
    title:          props.tour.title          ?? '',
    pax:            props.tour.pax            ?? 1,
    budget:         props.tour.budget         ?? '',
    start_date:     props.tour.start_date     ?? '',
    end_date:       props.tour.end_date       ?? '',
    status:         props.tour.status         ?? 'inquiry',
    sales_person:   props.tour.sales_person   ?? '',
    default_markup: props.tour.default_markup ?? 0,
    notes:          props.tour.notes          ?? '',
    details:        { ...emptyDetails(tourType), ...(props.tour.details ?? {}) },
})

const selectedCustomer = computed(() =>
    (props.customers ?? []).find(c => String(c.id) === headerForm.customer_id) ?? null
)
const isBuyer = computed(() => selectedCustomer.value?.type === 'buyer')

watch(isBuyer, (val) => {
    if (!val) headerForm.guest_name = ''
})

function saveHeader() {
    if (headerForm.customer_id === 'none') headerForm.customer_id = ''
    headerForm.patch(route('tours.update', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
    })
}
</script>

<template>
    <!-- Header Card -->
    <div class="rounded-lg border bg-white p-5 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <h3 class="font-semibold">Informasi {{ typeLabel(tourType) }}</h3>
            <span :class="[TYPE_BADGE[tourType] ?? 'bg-gray-100 text-gray-600', 'inline-flex px-2 py-0.5 rounded text-[11px] font-semibold']">
                {{ typeLabel(tourType) }}
            </span>
        </div>
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

            <!-- Nama tamu — khusus customer tipe buyer, dilihat tim lapangan -->
            <div v-if="isBuyer" class="space-y-1.5">
                <Label for="guest_name">Nama Tamu <span class="text-destructive">*</span></Label>
                <Input id="guest_name" v-model="headerForm.guest_name" placeholder="Mis. Mr. Tanaka & Party" />
                <p class="text-xs text-muted-foreground">
                    Nama ini yang dilihat guide/sopir di lapangan, bukan nama travel agent.
                </p>
                <p v-if="headerForm.errors.guest_name" class="text-xs text-destructive">{{ headerForm.errors.guest_name }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="title">Judul Tour</Label>
                <Input id="title" v-model="headerForm.title" placeholder="Mis. 4D3N Manado Heritage" />
            </div>

            <!-- Arah Tour — hanya untuk tipe Tour, menentukan klasifikasi Laba Rugi -->
            <div v-if="isTour" class="space-y-1.5">
                <Label>Arah Tour
                    <span class="text-xs text-muted-foreground font-normal ml-1">— untuk klasifikasi Laba Rugi</span>
                </Label>
                <Select v-model="headerForm.tour_direction">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="inbound">Inbound — Tamu datang ke Manado</SelectItem>
                        <SelectItem value="outbound">Outbound — Tamu ke luar Manado</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <Label for="pax">{{ isMice ? 'Jumlah Peserta (Pax)' : 'Pax' }}</Label>
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

            <!-- Budget klien — hanya untuk MICE -->
            <div v-if="isMice" class="space-y-1.5">
                <Label for="budget">
                    Budget Klien (IDR)
                    <span class="text-xs text-muted-foreground font-normal ml-1">— batas anggaran event dari klien</span>
                </Label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-mono">Rp</span>
                    <Input
                        id="budget"
                        type="number"
                        v-model.number="headerForm.budget"
                        min="0"
                        step="1000"
                        placeholder="0"
                        class="pl-9 text-right font-mono"
                    />
                </div>
                <p v-if="headerForm.errors.budget" class="text-xs text-destructive">{{ headerForm.errors.budget }}</p>
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
                <Button type="submit" :disabled="headerForm.processing" size="sm">Simpan Header</Button>
            </div>
        </form>
    </div>

    <!-- Detail khusus per tipe (non-tour) -->
    <div v-if="!isTour && detailFields.length" class="rounded-lg border bg-white p-5 shadow-sm">
        <h3 class="font-semibold mb-4">Detail {{ typeLabel(tourType) }}</h3>
        <form @submit.prevent="saveHeader" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div
                    v-for="f in detailFields"
                    :key="f.key"
                    class="space-y-1.5"
                    :class="(f.type === 'textarea' || f.type === 'checkbox') ? 'col-span-2' : ''"
                >
                    <label v-if="f.type === 'checkbox'" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="headerForm.details[f.key]" class="h-4 w-4 rounded border-input" />
                        {{ f.label }}
                    </label>
                    <template v-else>
                        <Label>{{ f.label }}</Label>
                        <Textarea v-if="f.type === 'textarea'" v-model="headerForm.details[f.key]" rows="2" :placeholder="f.placeholder" />
                        <Input v-else :type="f.type" v-model="headerForm.details[f.key]" :placeholder="f.placeholder" />
                    </template>
                </div>
            </div>
            <div class="flex justify-end">
                <Button type="submit" :disabled="headerForm.processing" size="sm">Simpan Detail</Button>
            </div>
        </form>
    </div>
</template>
