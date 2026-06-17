<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ tour: Object, quotationDefaults: Object, isTour: Boolean })

let idSeq = 0
function newId(prefix) {
    return `${prefix}-${Date.now().toString(36)}-${idSeq++}`
}

// Malam default dari rentang tanggal tour
const defaultNights = (() => {
    const s = props.tour.start_date, e = props.tour.end_date
    if (s && e) {
        const d = Math.round((new Date(e) - new Date(s)) / 86400000)
        return d > 0 ? d : 1
    }
    return 1
})()

function normalizePricing(p) {
    p = p || {}
    const hasCalc      = p.mode === 'auto'   || (p.tiers || []).some(t => t.pax != null || t.group_cost != null)
    const isManualData = p.mode === 'manual' || (!p.mode && (p.tiers || []).some(t => t.label && t.pax == null))
    const mode = p.mode || (hasCalc ? 'auto' : (isManualData ? 'manual' : 'auto'))

    return {
        mode,
        nights:       p.nights ?? defaultNights,
        markup_type:  p.markup_type === 'amount' ? 'amount' : 'percent',
        markup_value: p.markup_value ?? (Number(props.tour.default_markup) || 20),
        per_pax_cost: p.per_pax_cost ?? null,
        tiers: Array.isArray(p.tiers) ? p.tiers.map(t => ({
            id:         t.id || newId('t'),
            pax:        t.pax ?? null,
            vehicle:    t.vehicle ?? t.note ?? '',
            group_cost: t.group_cost ?? null,
            label:      t.label ?? '',
            note:       t.note ?? '',
        })) : [],
        base: {
            label:   p.base?.label   ?? 'Tanpa Hotel & Sarapan Pagi',
            enabled: p.base?.enabled ?? false,
            prices:  p.base?.prices  ?? {},
        },
        hotels: Array.isArray(p.hotels) ? p.hotels.map(h => ({
            id:         h.id || newId('h'),
            name:       h.name ?? '',
            room:       h.room ?? '',
            rate:       h.rate ?? null,
            occupancy:  h.occupancy ?? 2,
            prices:     h.prices ?? {},
            single_sup: h.single_sup ?? null,
        })) : [],
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

// ── Tier / Hotel / Optional management ──────────────────────────────────────
function addTier()  { quotation.pricing.tiers.push({ id: newId('t'), pax: null, vehicle: '', group_cost: null, label: '', note: '' }) }
function removeTier(idx) {
    const [removed] = quotation.pricing.tiers.splice(idx, 1)
    if (removed) {
        delete quotation.pricing.base.prices[removed.id]
        quotation.pricing.hotels.forEach(h => { delete h.prices[removed.id] })
    }
}
function addHotel()          { quotation.pricing.hotels.push({ id: newId('h'), name: '', room: '', rate: null, occupancy: 2, prices: {}, single_sup: null }) }
function removeHotel(idx)    { quotation.pricing.hotels.splice(idx, 1) }
function addOptional()       { quotation.pricing.optionals.push({ label: '', price: null, note: '' }) }
function removeOptional(idx) { quotation.pricing.optionals.splice(idx, 1) }

// ── Kalkulator: hitung harga otomatis ───────────────────────────────────────
function roundUp(x)       { return Math.ceil((Number(x) || 0) / 1000) * 1000 }
function markupFactor()   { return quotation.pricing.markup_type === 'percent' ? 1 + (Number(quotation.pricing.markup_value) || 0) / 100 : 1 }
function markupAdd()      { return quotation.pricing.markup_type === 'amount' ? (Number(quotation.pricing.markup_value) || 0) : 0 }
function withMarkup(cost) { return roundUp(cost * markupFactor() + markupAdd()) }
function extraMarkup(raw) { return roundUp(raw * markupFactor()) } // single sup hanya kena markup %

function tierBaseCost(tier) {
    const pax    = Number(tier.pax) || 1
    const group  = Number(tier.group_cost) || 0
    const perPax = Number(quotation.pricing.per_pax_cost) || 0
    return group / pax + perPax
}
function hotelPerPax(hotel) {
    const nights = Number(quotation.pricing.nights) || 0
    const rate   = Number(hotel.rate) || 0
    const occ    = Number(hotel.occupancy) || 1
    return rate * nights / occ
}
function autoBasePrice(tier)        { return withMarkup(tierBaseCost(tier)) }
function autoHotelPrice(tier, hotel){ return withMarkup(tierBaseCost(tier) + hotelPerPax(hotel)) }
function autoSingleSup(hotel) {
    const nights = Number(quotation.pricing.nights) || 0
    const rate   = Number(hotel.rate) || 0
    const occ    = Number(hotel.occupancy) || 1
    return occ > 1 ? extraMarkup(rate * nights * (1 - 1 / occ)) : 0
}

const hasSingleSup = computed(() => quotation.pricing.hotels.some(h => autoSingleSup(h) > 0))

/** Salin hasil hitung ke struktur prices/label agar PDF (tanpa perubahan) bisa menampilkannya. */
function bakeAuto() {
    const p = quotation.pricing
    p.tiers.forEach(t => {
        t.label = `Min ${Number(t.pax) || 1} pax`
        t.note  = t.vehicle || ''
    })
    p.base.prices = {}
    p.tiers.forEach(t => { p.base.prices[t.id] = autoBasePrice(t) })
    p.hotels.forEach(h => {
        h.prices = {}
        p.tiers.forEach(t => { h.prices[t.id] = autoHotelPrice(t, h) })
        h.single_sup = autoSingleSup(h)
    })
}

const saving = ref(false)
function saveQuotation() {
    if (props.isTour && quotation.pricing.mode === 'auto') bakeAuto()
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
                <!-- Mode toggle -->
                <div class="inline-flex rounded-md border p-0.5 text-sm">
                    <button type="button" @click="quotation.pricing.mode = 'auto'"
                        :class="quotation.pricing.mode === 'auto' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                        class="px-3 py-1 rounded transition-colors">⚡ Kalkulator</button>
                    <button type="button" @click="quotation.pricing.mode = 'manual'"
                        :class="quotation.pricing.mode === 'manual' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                        class="px-3 py-1 rounded transition-colors">✎ Manual</button>
                </div>

                <!-- ══════════ MODE KALKULATOR ══════════ -->
                <template v-if="quotation.pricing.mode === 'auto'">
                    <p class="text-xs text-muted-foreground -mt-3">
                        Isi biaya → harga per pax dihitung otomatis:
                        <b>(biaya kendaraan ÷ pax + biaya per pax + kamar × malam ÷ isi) × (1 + markup)</b>
                    </p>

                    <!-- Setelan umum -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="space-y-1">
                            <Label class="text-xs">Jumlah Malam</Label>
                            <Input type="number" step="1" min="0" v-model.number="quotation.pricing.nights" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Markup</Label>
                            <div class="flex">
                                <button type="button" @click="quotation.pricing.markup_type = 'percent'"
                                    :class="quotation.pricing.markup_type === 'percent' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                    class="px-2 rounded-l border text-sm">%</button>
                                <button type="button" @click="quotation.pricing.markup_type = 'amount'"
                                    :class="quotation.pricing.markup_type === 'amount' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                    class="px-2 border-y text-sm">Rp</button>
                                <Input type="number" step="any" v-model.number="quotation.pricing.markup_value" class="rounded-l-none" />
                            </div>
                        </div>
                        <div class="space-y-1 col-span-2">
                            <Label class="text-xs">Biaya per Pax <span class="text-muted-foreground font-normal">(makan + tiket + air + handling)</span></Label>
                            <Input type="number" step="any" v-model.number="quotation.pricing.per_pax_cost" placeholder="800000" />
                        </div>
                    </div>

                    <!-- Tier pax (kendaraan) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Tier (per kendaraan / jumlah pax)</Label>
                            <Button type="button" variant="outline" size="sm" @click="addTier">+ Tier</Button>
                        </div>
                        <p v-if="!quotation.pricing.tiers.length" class="text-xs text-muted-foreground">
                            Contoh: pax 2 (Avanza), pax 4 (Innova), pax 8 (Hiace) — atau bebas: 1, 5, 10, 20.
                        </p>
                        <div v-for="(tier, ti) in quotation.pricing.tiers" :key="tier.id" class="grid grid-cols-12 gap-2 items-center">
                            <Input type="number" step="1" min="1" v-model.number="tier.pax" placeholder="Pax" class="col-span-2" />
                            <Input v-model="tier.vehicle" placeholder="Nama kendaraan (Innova)" class="col-span-4" />
                            <Input type="number" step="any" v-model.number="tier.group_cost" placeholder="Biaya kendaraan+operasional grup" class="col-span-5" />
                            <Button type="button" variant="ghost" size="sm" class="col-span-1" @click="removeTier(ti)">✕</Button>
                        </div>
                    </div>

                    <!-- Baris tanpa hotel -->
                    <div class="rounded-md border p-3 space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" v-model="quotation.pricing.base.enabled" class="rounded border-gray-300" />
                            Tampilkan baris "Tanpa Hotel"
                        </label>
                        <Input v-if="quotation.pricing.base.enabled" v-model="quotation.pricing.base.label" placeholder="Tanpa Hotel & Sarapan Pagi" />
                    </div>

                    <!-- Hotels -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Hotel (harga kamar/malam)</Label>
                            <Button type="button" variant="outline" size="sm" @click="addHotel">+ Hotel</Button>
                        </div>
                        <div v-for="(hotel, hi) in quotation.pricing.hotels" :key="hotel.id" class="grid grid-cols-12 gap-2 items-center">
                            <Input v-model="hotel.name" placeholder="Aston Hotel 4*" class="col-span-4" />
                            <Input v-model="hotel.room" placeholder="Superior" class="col-span-3" />
                            <Input type="number" step="any" v-model.number="hotel.rate" placeholder="Kamar/malam" class="col-span-2" />
                            <Input type="number" step="1" min="1" v-model.number="hotel.occupancy" placeholder="Isi" class="col-span-2" />
                            <Button type="button" variant="ghost" size="sm" class="col-span-1" @click="removeHotel(hi)">✕</Button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Isi = jumlah orang per kamar (mis. 2 = twin). Single supplement dihitung otomatis.</p>
                    </div>

                    <!-- Preview matriks (otomatis) -->
                    <div v-if="quotation.pricing.tiers.length" class="space-y-1">
                        <Label class="text-xs">Pratinjau Harga per Pax (otomatis)</Label>
                        <div class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="text-left font-medium px-3 py-2">Hotel / Paket</th>
                                        <th v-for="tier in quotation.pricing.tiers" :key="tier.id" class="text-right font-medium px-3 py-2 whitespace-nowrap">
                                            {{ tier.pax || '?' }} pax<span v-if="tier.vehicle" class="block text-[10px] text-muted-foreground font-normal">{{ tier.vehicle }}</span>
                                        </th>
                                        <th v-if="hasSingleSup" class="text-right font-medium px-3 py-2">Sgl. Sup</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-if="quotation.pricing.base.enabled">
                                        <td class="px-3 py-2 font-medium">{{ quotation.pricing.base.label || 'Tanpa Hotel' }}</td>
                                        <td v-for="tier in quotation.pricing.tiers" :key="tier.id" class="text-right px-3 py-2">{{ fmtRp(autoBasePrice(tier)) }}</td>
                                        <td v-if="hasSingleSup" class="text-right px-3 py-2 text-muted-foreground">—</td>
                                    </tr>
                                    <tr v-for="hotel in quotation.pricing.hotels" :key="hotel.id">
                                        <td class="px-3 py-2">
                                            <span class="font-medium">{{ hotel.name || 'Hotel' }}</span>
                                            <span v-if="hotel.room" class="text-xs text-muted-foreground"> · {{ hotel.room }}</span>
                                        </td>
                                        <td v-for="tier in quotation.pricing.tiers" :key="tier.id" class="text-right px-3 py-2">{{ fmtRp(autoHotelPrice(tier, hotel)) }}</td>
                                        <td v-if="hasSingleSup" class="text-right px-3 py-2 text-muted-foreground">{{ autoSingleSup(hotel) ? fmtRp(autoSingleSup(hotel)) : '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[11px] text-muted-foreground">Harga ini yang tampil di PDF. Dibulatkan ke atas per Rp 1.000.</p>
                    </div>
                </template>

                <!-- ══════════ MODE MANUAL ══════════ -->
                <template v-else>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Kolom Tier Pax</Label>
                            <Button type="button" variant="outline" size="sm" @click="addTier">+ Tier</Button>
                        </div>
                        <div v-for="(tier, ti) in quotation.pricing.tiers" :key="tier.id" class="flex items-center gap-2">
                            <Input v-model="tier.label" placeholder="Min 4 pax" class="w-40" />
                            <Input v-model="tier.note" placeholder="Innova Reborn" class="flex-1" />
                            <Button type="button" variant="ghost" size="sm" @click="removeTier(ti)">✕</Button>
                        </div>
                    </div>

                    <template v-if="quotation.pricing.tiers.length">
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
                </template>

                <!-- Optional tour (sama untuk kedua mode) -->
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
