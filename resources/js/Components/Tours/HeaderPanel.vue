<script setup>
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

const headerForm = useForm({
    type:           tourType,
    customer_id:    props.tour.customer_id ? String(props.tour.customer_id) : 'none',
    title:          props.tour.title          ?? '',
    pax:            props.tour.pax            ?? 1,
    start_date:     props.tour.start_date     ?? '',
    end_date:       props.tour.end_date       ?? '',
    status:         props.tour.status         ?? 'inquiry',
    sales_person:   props.tour.sales_person   ?? '',
    default_markup: props.tour.default_markup ?? 0,
    notes:          props.tour.notes          ?? '',
    details:        { ...emptyDetails(tourType), ...(props.tour.details ?? {}) },
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
