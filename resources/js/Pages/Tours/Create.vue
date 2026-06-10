<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import { INQUIRY_TYPES, TYPE_FIELDS, typeLabel, emptyDetails } from '@/lib/inquiryTypes'

const props = defineProps({
    customers: Array,
    packages:  Array,
    type:      { type: String, default: 'tour' },
    types:     { type: Object, default: () => ({}) },
})

const isTour   = computed(() => props.type === 'tour')
const fields    = computed(() => TYPE_FIELDS[props.type] ?? [])
const heading   = computed(() => INQUIRY_TYPES[props.type]?.build ?? 'Buat Tour Baru')

const form = useForm({
    type:           props.type,
    inquiry_source: props.type === 'tour' ? 'website' : 'external',
    package_id:     null,
    customer_id:    'none',
    title:          '',
    pax:            1,
    start_date:     '',
    end_date:       '',
    status:         'inquiry',
    sales_person:   '',
    notes:          '',
    details:        emptyDetails(props.type),
})

// --- package combobox ---
const packageSearch   = ref('')
const packageOpen     = ref(false)
const packageComboRef = ref(null)
const selectedPackage = ref(null)

const filteredPackages = computed(() => {
    const q    = packageSearch.value.toLowerCase().trim()
    const list = q
        ? props.packages.filter(p =>
            p.title.toLowerCase().includes(q) ||
            (p.location || '').toLowerCase().includes(q)
          )
        : props.packages
    return {
        manado:        list.filter(p => p.type === 'manado'),
        national:      list.filter(p => p.type === 'national'),
        international: list.filter(p => p.type === 'international'),
    }
})

function durationLabel(pkg) {
    return pkg.duration_nights > 0
        ? `${pkg.duration_days}D${pkg.duration_nights}N`
        : `${pkg.duration_days}D`
}

function selectPackage(pkg) {
    selectedPackage.value = pkg
    form.package_id       = pkg.id
    form.title            = pkg.title
    packageSearch.value   = pkg.title
    packageOpen.value     = false
}

function clearPackage() {
    selectedPackage.value = null
    form.package_id       = null
    form.title            = ''
    packageSearch.value   = ''
}

function setSource(src) {
    form.inquiry_source = src
    if (src === 'external') clearPackage()
}

function onMouseDown(e) {
    if (packageComboRef.value && !packageComboRef.value.contains(e.target)) {
        packageOpen.value = false
    }
}
onMounted(() => document.addEventListener('mousedown', onMouseDown))
onBeforeUnmount(() => document.removeEventListener('mousedown', onMouseDown))

function submit() {
    if (form.customer_id === 'none') form.customer_id = ''
    form.post(route('tours.store'))
}
</script>

<template>
    <Head :title="heading" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('tours.index', { type })" class="text-muted-foreground hover:text-foreground">
                    ← {{ typeLabel(type) }}
                </Link>
                <span class="text-muted-foreground">/</span>
                <h2 class="text-xl font-semibold">{{ heading }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <p class="text-sm text-muted-foreground mb-5">
                        Isi informasi dasar dulu. Setelah tour dibuat, kamu akan diarahkan ke halaman
                        <strong>Tour Builder</strong> untuk menambahkan item produk.
                    </p>

                    <form @submit.prevent="submit" class="space-y-5">

                        <!-- SOURCE PICKER (tour saja — katalog website khusus tour) -->
                        <div v-if="isTour" class="space-y-1.5">
                            <Label>Sumber Inquiry</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-3 text-sm transition-colors',
                                        form.inquiry_source === 'website'
                                            ? 'border-primary bg-primary/5 text-primary font-medium'
                                            : 'border-border text-muted-foreground hover:border-muted-foreground/60',
                                    ]"
                                    @click="setSource('website')"
                                >
                                    <!-- globe icon -->
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                    </svg>
                                    <span>Dari Website</span>
                                    <span class="text-xs opacity-70">welcomemanado.com</span>
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'flex flex-col items-center gap-1 rounded-lg border-2 px-3 py-3 text-sm transition-colors',
                                        form.inquiry_source === 'external'
                                            ? 'border-primary bg-primary/5 text-primary font-medium'
                                            : 'border-border text-muted-foreground hover:border-muted-foreground/60',
                                    ]"
                                    @click="setSource('external')"
                                >
                                    <!-- phone icon -->
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.62 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                    <span>External</span>
                                    <span class="text-xs opacity-70">WA / Telepon / Walk-in</span>
                                </button>
                            </div>
                        </div>

                        <!-- PACKAGE COMBOBOX (website only) -->
                        <div v-if="isTour && form.inquiry_source === 'website'" class="space-y-1.5">
                            <Label>Paket Tour</Label>
                            <div class="relative" ref="packageComboRef">
                                <Input
                                    v-model="packageSearch"
                                    placeholder="Ketik nama paket untuk mencari..."
                                    autocomplete="off"
                                    @focus="packageOpen = true"
                                    @input="packageOpen = true"
                                />
                                <div
                                    v-if="packageOpen"
                                    class="absolute z-50 mt-1 w-full max-h-72 overflow-y-auto rounded-md border bg-white shadow-lg"
                                >
                                    <template v-if="filteredPackages.manado.length || filteredPackages.national.length || filteredPackages.international.length">
                                        <!-- Manado group -->
                                        <template v-if="filteredPackages.manado.length">
                                            <div class="bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground">
                                                Lokal Manado ({{ filteredPackages.manado.length }})
                                            </div>
                                            <button
                                                v-for="pkg in filteredPackages.manado"
                                                :key="pkg.id"
                                                type="button"
                                                class="w-full text-left flex items-center justify-between px-3 py-2 text-sm hover:bg-accent"
                                                @click="selectPackage(pkg)"
                                            >
                                                <span>{{ pkg.title }}</span>
                                                <span class="ml-3 shrink-0 text-xs text-muted-foreground">{{ durationLabel(pkg) }}</span>
                                            </button>
                                        </template>

                                        <!-- Nasional group -->
                                        <template v-if="filteredPackages.national.length">
                                            <div class="bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground">
                                                Nasional ({{ filteredPackages.national.length }})
                                            </div>
                                            <button
                                                v-for="pkg in filteredPackages.national"
                                                :key="pkg.id"
                                                type="button"
                                                class="w-full text-left flex items-center justify-between px-3 py-2 text-sm hover:bg-accent"
                                                @click="selectPackage(pkg)"
                                            >
                                                <span>{{ pkg.title }}</span>
                                                <span class="ml-3 shrink-0 text-xs text-muted-foreground">
                                                    {{ pkg.location ? pkg.location + ' · ' : '' }}{{ durationLabel(pkg) }}
                                                </span>
                                            </button>
                                        </template>

                                        <!-- Internasional group -->
                                        <template v-if="filteredPackages.international.length">
                                            <div class="bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground">
                                                Internasional ({{ filteredPackages.international.length }})
                                            </div>
                                            <button
                                                v-for="pkg in filteredPackages.international"
                                                :key="pkg.id"
                                                type="button"
                                                class="w-full text-left flex items-center justify-between px-3 py-2 text-sm hover:bg-accent"
                                                @click="selectPackage(pkg)"
                                            >
                                                <span>{{ pkg.title }}</span>
                                                <span class="ml-3 shrink-0 text-xs text-muted-foreground">{{ durationLabel(pkg) }}</span>
                                            </button>
                                        </template>
                                    </template>
                                    <div v-else class="px-3 py-4 text-center text-sm text-muted-foreground">
                                        Paket tidak ditemukan
                                    </div>
                                </div>
                            </div>

                            <!-- selected package info -->
                            <p v-if="selectedPackage" class="text-xs text-muted-foreground">
                                ✓
                                <span class="font-medium">{{ { manado: 'Lokal Manado', national: 'Nasional', international: 'Internasional' }[selectedPackage.type] }}</span>
                                · {{ durationLabel(selectedPackage) }}
                                ·
                                <button type="button" class="text-destructive underline underline-offset-2" @click="clearPackage">
                                    Ganti paket
                                </button>
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Pilih paket dari katalog website, atau biarkan kosong untuk mengisi manual.
                            </p>
                        </div>

                        <!-- TITLE -->
                        <div class="space-y-1.5">
                            <Label for="title">
                                Judul {{ typeLabel(type) }}
                                <span
                                    v-if="isTour && form.inquiry_source === 'website' && selectedPackage"
                                    class="text-xs font-normal text-muted-foreground ml-1"
                                >(dari paket, bisa diedit)</span>
                            </Label>
                            <Input id="title" v-model="form.title" placeholder="Mis. 4D3N Manado Heritage" />
                        </div>

                        <!-- CUSTOMER -->
                        <div class="space-y-1.5">
                            <Label>Customer</Label>
                            <Select v-model="form.customer_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih customer..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">— Tanpa Customer —</SelectItem>
                                    <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                        {{ c.name }}<span v-if="c.country" class="text-muted-foreground"> ({{ c.country }})</span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="pax">Jumlah Pax <span class="text-destructive">*</span></Label>
                                <Input id="pax" type="number" v-model="form.pax" min="1" />
                                <p v-if="form.errors.pax" class="text-sm text-destructive">{{ form.errors.pax }}</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label>Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="inquiry">Inquiry</SelectItem>
                                        <SelectItem value="quotation_draft">Draft Quotation</SelectItem>
                                        <SelectItem value="quotation_sent">Sent</SelectItem>
                                        <SelectItem value="follow_up">Follow Up</SelectItem>
                                        <SelectItem value="negotiation">Negosiasi</SelectItem>
                                        <SelectItem value="confirmed">Confirmed</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="start_date">Tanggal Mulai</Label>
                                <Input id="start_date" type="date" v-model="form.start_date" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="end_date">Tanggal Selesai</Label>
                                <Input id="end_date" type="date" v-model="form.end_date" />
                            </div>
                        </div>

                        <!-- DETAIL KHUSUS PER TIPE -->
                        <div v-if="fields.length" class="rounded-lg border bg-muted/20 p-4 space-y-4">
                            <p class="text-sm font-semibold">Detail {{ typeLabel(type) }}</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    v-for="f in fields"
                                    :key="f.key"
                                    class="space-y-1.5"
                                    :class="(f.type === 'textarea' || f.type === 'checkbox') ? 'col-span-2' : ''"
                                >
                                    <label v-if="f.type === 'checkbox'" class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" v-model="form.details[f.key]" class="h-4 w-4 rounded border-input" />
                                        {{ f.label }}
                                    </label>
                                    <template v-else>
                                        <Label>{{ f.label }}</Label>
                                        <Textarea v-if="f.type === 'textarea'" v-model="form.details[f.key]" rows="2" :placeholder="f.placeholder" />
                                        <Input v-else :type="f.type" v-model="form.details[f.key]" :placeholder="f.placeholder" />
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="sales_person">Sales Person</Label>
                            <Input id="sales_person" v-model="form.sales_person" placeholder="Nama sales..." />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="notes">Catatan</Label>
                            <Textarea id="notes" v-model="form.notes" rows="3" />
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('tours.index')">
                                <Button type="button" variant="outline">Batal</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Buat {{ typeLabel(type) }} & Buka Builder →
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
