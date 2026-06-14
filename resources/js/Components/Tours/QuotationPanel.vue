<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'

const props = defineProps({ tour: Object, quotationDefaults: Object, isTour: Boolean })

let idSeq = 0
function newId(prefix) {
    return `${prefix}-${Date.now().toString(36)}-${idSeq++}`
}

function normalizePricing(p) {
    p = p || {}
    return {
        tiers:  Array.isArray(p.tiers)     ? p.tiers     : [],
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
    included:       props.tour.included      ?? props.quotationDefaults?.included     ?? '',
    excluded:       props.tour.excluded      ?? props.quotationDefaults?.excluded     ?? '',
    child_policy:   props.tour.child_policy  ?? props.quotationDefaults?.child_policy ?? '',
    terms:          props.tour.terms         ?? props.quotationDefaults?.terms        ?? '',
    price_validity: (props.tour.price_validity ?? '').slice(0, 10),
})

function addTier()  { quotation.pricing.tiers.push({ id: newId('t'), label: '', note: '' }) }
function removeTier(idx) {
    const [removed] = quotation.pricing.tiers.splice(idx, 1)
    if (removed) {
        delete quotation.pricing.base.prices[removed.id]
        quotation.pricing.hotels.forEach(h => { delete h.prices[removed.id] })
    }
}
function addHotel()           { quotation.pricing.hotels.push({ id: newId('h'), name: '', room: '', prices: {}, single_sup: null }) }
function removeHotel(idx)     { quotation.pricing.hotels.splice(idx, 1) }
function addOptional()        { quotation.pricing.optionals.push({ label: '', price: null, note: '' }) }
function removeOptional(idx)  { quotation.pricing.optionals.splice(idx, 1) }

const saving = ref(false)
function saveQuotation() {
    saving.value = true
    router.patch(route('tours.update', props.tour.id), {
        pricing:        quotation.pricing,
        included:       quotation.included,
        excluded:       quotation.excluded,
        child_policy:   quotation.child_policy,
        terms:          quotation.terms,
        price_validity: quotation.price_validity || null,
    }, {
        preserveScroll: true,
        only: ['tour'],
        onFinish: () => { saving.value = false },
    })
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <div>
                <h3 class="font-semibold">Quotation / Penawaran</h3>
                <p class="text-xs text-muted-foreground">Harga, syarat & teks yang tampil di PDF untuk customer</p>
            </div>
            <Button size="sm" :disabled="saving" @click="saveQuotation">
                {{ saving ? 'Menyimpan…' : 'Simpan Quotation' }}
            </Button>
        </div>
        <div class="p-5 space-y-6">

            <template v-if="isTour">
                <!-- Tier pax -->
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
            </template>

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
            <div v-if="isTour" class="space-y-1.5">
                <Label>Kebijakan Anak <span class="text-xs text-muted-foreground font-normal">(1 item per baris)</span></Label>
                <Textarea v-model="quotation.child_policy" rows="4" />
            </div>
            <div class="space-y-1.5">
                <Label>Syarat &amp; Ketentuan</Label>
                <Textarea v-model="quotation.terms" rows="6" />
            </div>
        </div>
    </div>
</template>
