<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
    suppliers:    Array,
    pendingCount: Number,
})

const TYPE_LABELS = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Attraction', other: 'Lainnya',
}
const UNIT_LABELS = { per_pax: 'Per Pax', per_unit: 'Per Unit', per_night: 'Per Malam' }

function fmtDate(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtDateTime(d) {
    if (!d) return ''
    return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

// collapse per supplier
const collapsed = ref(new Set())
function toggle(id) {
    collapsed.value.has(id) ? collapsed.value.delete(id) : collapsed.value.add(id)
    collapsed.value = new Set(collapsed.value)
}

// expand period prices per product
const expandedPeriods = ref(new Set())
function togglePeriods(id) {
    expandedPeriods.value.has(id)
        ? expandedPeriods.value.delete(id)
        : expandedPeriods.value.add(id)
    expandedPeriods.value = new Set(expandedPeriods.value)
}

// Product-level approval
function approve(p) {
    router.patch(route('channel-manager.approve', p.id), {}, { preserveScroll: true })
}
async function reject(p) {
    if (await confirm({ title: 'Tolak pengajuan harga?', description: 'Harga lama akan dipertahankan dan pengajuan dibatalkan.', confirmLabel: 'Tolak' })) {
        router.patch(route('channel-manager.reject', p.id), {}, { preserveScroll: true })
    }
}

// edit harga langsung (internal)
const editingId = ref(null)
const priceForm = ref({ cost: 0, sell: 0 })
function startEdit(p) {
    editingId.value = p.id
    priceForm.value = { cost: Number(p.cost), sell: Number(p.sell) }
}
function savePrice(p) {
    router.patch(route('channel-manager.price', p.id), priceForm.value, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

// Period price approval
const approvingPeriod = ref(null)
const periodSellForm = useForm({ sell: '' })

function startApprovePeriod(pr) {
    approvingPeriod.value = pr.id
    periodSellForm.sell = Number(pr.cost)
}
function submitApprovePeriod(prId) {
    periodSellForm.patch(route('channel-manager.price.approve', prId), {
        preserveScroll: true,
        onSuccess: () => { approvingPeriod.value = null; periodSellForm.reset() },
    })
}
async function rejectPeriod(prId) {
    if (await confirm({ title: 'Tolak periode harga?', description: 'Pengajuan periode ini akan dibatalkan.', confirmLabel: 'Tolak' })) {
        router.patch(route('channel-manager.price.reject', prId), {}, { preserveScroll: true })
    }
}

// Period price direct edit (internal)
const editingPeriodId = ref(null)
const periodEditForm = ref({ cost: 0, sell: 0, is_active: true })
function startEditPeriod(pr) {
    editingPeriodId.value = pr.id
    periodEditForm.value = { cost: Number(pr.cost), sell: Number(pr.sell), is_active: pr.is_active }
}
function savePeriodEdit(prId) {
    router.patch(route('channel-manager.period.price', prId), periodEditForm.value, {
        preserveScroll: true,
        onSuccess: () => { editingPeriodId.value = null },
    })
}

const totalPending = computed(() => props.pendingCount ?? 0)
</script>

<template>
    <Head title="Channel Manager" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Channel Manager</h2>
                <span v-if="totalPending" class="text-xs px-2.5 py-1 rounded-full bg-orange-100 text-orange-700 font-medium">
                    {{ totalPending }} pengajuan harga
                </span>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-4">

                <p class="text-sm text-muted-foreground">
                    Harga modal terbaru &amp; jenis produk dari tiap Travel Agent. Setujui pengajuan harga, atau ubah harga langsung.
                </p>

                <div v-if="!suppliers.length" class="rounded-lg border bg-white p-8 text-center text-sm text-muted-foreground">
                    Belum ada Travel Agent. Tambahkan via menu <strong>Suppliers</strong> dengan mengaktifkan opsi "Jadikan Travel Agent".
                </div>

                <!-- Per supplier -->
                <div
                    v-for="s in suppliers"
                    :key="s.id"
                    class="rounded-lg border bg-white shadow-sm overflow-hidden"
                >
                    <button
                        type="button"
                        @click="toggle(s.id)"
                        class="w-full flex items-center justify-between px-5 py-4 hover:bg-muted/30"
                    >
                        <div class="text-left">
                            <div class="font-semibold flex items-center gap-2">
                                {{ s.name }}
                                <span v-if="s.type" class="text-xs px-2 py-0.5 rounded-full bg-muted text-muted-foreground font-medium">
                                    {{ TYPE_LABELS[s.type] ?? s.type }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ s.products.length }} produk
                                <span v-if="s.products.filter(p => p.price_status === 'pending').length" class="text-orange-600 font-medium">
                                    · {{ s.products.filter(p => p.price_status === 'pending').length }} menunggu persetujuan
                                </span>
                            </p>
                        </div>
                        <span class="text-muted-foreground text-lg">{{ collapsed.has(s.id) ? '+' : '−' }}</span>
                    </button>

                    <div v-if="!collapsed.has(s.id)" class="border-t divide-y">
                        <div v-if="!s.products.length" class="px-5 py-6 text-center text-sm text-muted-foreground">
                            Travel agent ini belum menambahkan produk.
                        </div>

                        <div
                            v-for="p in s.products"
                            :key="p.id"
                            class="border-b last:border-b-0"
                            :class="p.price_status === 'pending' ? 'bg-orange-50/30' : ''"
                        >
                            <!-- Baris utama produk -->
                            <div class="px-5 py-3 flex items-center justify-between gap-4 flex-wrap">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-sm flex items-center gap-2 flex-wrap">
                                        {{ p.name }}
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
                                            {{ TYPE_LABELS[p.type] ?? p.type }} · {{ UNIT_LABELS[p.unit] ?? p.unit }}
                                        </span>
                                        <span v-if="!p.is_active" class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">Non-aktif</span>
                                    </div>
                                    <div class="text-xs text-muted-foreground mt-0.5">
                                        Modal: <span class="font-mono">{{ fmtRp(p.cost) }}</span>
                                        · Jual: <span class="font-mono">{{ fmtRp(p.sell) }}</span>
                                    </div>
                                </div>

                                <!-- Pengajuan harga produk pending -->
                                <div v-if="p.price_status === 'pending'" class="flex items-center gap-3 shrink-0">
                                    <div class="text-right">
                                        <div class="text-xs text-muted-foreground">Harga diajukan</div>
                                        <div class="font-mono font-semibold text-orange-700">{{ fmtRp(p.pending_cost) }}</div>
                                        <div class="text-[11px] text-muted-foreground">
                                            {{ p.price_submitted_by }} · {{ fmtDateTime(p.price_updated_at) }}
                                        </div>
                                    </div>
                                    <Button size="sm" @click="approve(p)" class="h-7 text-xs bg-green-600 hover:bg-green-700">Setujui</Button>
                                    <Button size="sm" variant="outline" @click="reject(p)" class="h-7 text-xs">Tolak</Button>
                                </div>

                                <!-- Edit harga produk langsung -->
                                <div v-else-if="editingId === p.id" class="flex items-end gap-2 shrink-0">
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted-foreground block">Modal</label>
                                        <Input type="number" v-model.number="priceForm.cost" class="h-8 w-28 text-sm text-right" min="0" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted-foreground block">Jual</label>
                                        <Input type="number" v-model.number="priceForm.sell" class="h-8 w-28 text-sm text-right" min="0" />
                                    </div>
                                    <Button size="sm" @click="savePrice(p)" class="h-8 text-xs">Simpan</Button>
                                    <Button size="sm" variant="outline" @click="editingId = null" class="h-8 text-xs">Batal</Button>
                                </div>

                                <div v-else class="shrink-0">
                                    <Button size="sm" variant="outline" @click="startEdit(p)" class="h-7 text-xs">Ubah Harga</Button>
                                </div>
                            </div>

                            <!-- Toggle periode harga produk -->
                            <div v-if="p.prices?.length || p.prices?.some(pr => pr.status === 'pending')">
                                <button
                                    type="button"
                                    class="w-full flex items-center justify-between px-5 py-2 text-xs text-muted-foreground bg-muted/20 hover:bg-muted/40 border-t"
                                    @click="togglePeriods(p.id)"
                                >
                                    <span>
                                        Periode Harga
                                        <span v-if="p.prices?.length" class="ml-1 font-medium text-foreground">({{ p.prices.length }})</span>
                                        <span v-if="p.prices?.some(pr => pr.status === 'pending')" class="ml-1 text-orange-600 font-medium">· ada pengajuan</span>
                                    </span>
                                    <span>{{ expandedPeriods.has(p.id) ? '−' : '+' }}</span>
                                </button>

                                <div v-if="expandedPeriods.has(p.id)" class="border-t divide-y bg-white">
                                    <div v-for="pr in p.prices" :key="pr.id"
                                        class="px-5 py-3"
                                        :class="pr.status === 'pending' ? 'bg-orange-50/50' : ''"
                                    >
                                        <!-- Edit mode periode -->
                                        <template v-if="editingPeriodId === pr.id">
                                            <div class="flex items-end gap-2 flex-wrap">
                                                <div class="space-y-1">
                                                    <label class="text-[11px] text-muted-foreground block">Modal</label>
                                                    <Input type="number" v-model.number="periodEditForm.cost" class="h-8 w-28 text-sm text-right" min="0" />
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[11px] text-muted-foreground block">Jual</label>
                                                    <Input type="number" v-model.number="periodEditForm.sell" class="h-8 w-28 text-sm text-right" min="0" />
                                                </div>
                                                <label class="flex items-center gap-1.5 text-xs pb-1">
                                                    <input type="checkbox" v-model="periodEditForm.is_active" class="h-3.5 w-3.5 rounded border" />
                                                    Aktif
                                                </label>
                                                <Button size="sm" @click="savePeriodEdit(pr.id)" class="h-8 text-xs">Simpan</Button>
                                                <Button size="sm" variant="outline" @click="editingPeriodId = null" class="h-8 text-xs">Batal</Button>
                                            </div>
                                        </template>

                                        <!-- Approve mode (set sell price) -->
                                        <template v-else-if="approvingPeriod === pr.id">
                                            <div class="flex items-end gap-2 flex-wrap">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium">{{ pr.label || 'Periode tanpa label' }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ fmtDate(pr.start_date) }} — {{ fmtDate(pr.end_date) }}</div>
                                                    <div class="text-xs text-orange-700 font-mono mt-0.5">Modal diajukan: {{ fmtRp(pr.pending_cost) }}</div>
                                                </div>
                                                <div class="space-y-1 shrink-0">
                                                    <label class="text-[11px] text-muted-foreground block">Harga Jual</label>
                                                    <Input type="number" v-model.number="periodSellForm.sell" class="h-8 w-28 text-sm text-right" min="0" />
                                                    <p v-if="periodSellForm.errors.sell" class="text-xs text-destructive">{{ periodSellForm.errors.sell }}</p>
                                                </div>
                                                <Button size="sm" @click="submitApprovePeriod(pr.id)" :disabled="periodSellForm.processing" :loading="periodSellForm.processing" class="h-8 text-xs bg-green-600 hover:bg-green-700">Setujui</Button>
                                                <Button size="sm" variant="outline" @click="approvingPeriod = null" class="h-8 text-xs">Batal</Button>
                                            </div>
                                        </template>

                                        <!-- View mode -->
                                        <template v-else>
                                            <div class="flex items-center gap-4 flex-wrap">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium">{{ pr.label || 'Periode tanpa label' }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ fmtDate(pr.start_date) }} — {{ fmtDate(pr.end_date) }}</div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <div v-if="pr.status === 'pending'" class="text-xs font-mono text-orange-700 font-semibold">
                                                        Diajukan: {{ fmtRp(pr.pending_cost) }}
                                                    </div>
                                                    <div v-else class="text-sm font-mono">
                                                        {{ fmtRp(pr.cost) }} / {{ fmtRp(pr.sell) }}
                                                    </div>
                                                    <div class="mt-0.5">
                                                        <span v-if="pr.status === 'pending'" class="text-[10px] px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-700 font-semibold">Menunggu</span>
                                                        <span v-else-if="pr.is_active" class="text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold">Aktif</span>
                                                        <span v-else class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 font-semibold">Non-aktif</span>
                                                    </div>
                                                </div>
                                                <!-- Actions untuk pending periode -->
                                                <div v-if="pr.status === 'pending'" class="flex gap-1.5 shrink-0">
                                                    <div class="text-[11px] text-muted-foreground self-center">{{ pr.submitted_by }} · {{ fmtDateTime(pr.submitted_at) }}</div>
                                                    <Button size="sm" @click="startApprovePeriod(pr)" class="h-7 text-xs bg-green-600 hover:bg-green-700">Setujui</Button>
                                                    <Button size="sm" variant="outline" @click="rejectPeriod(pr.id)" class="h-7 text-xs">Tolak</Button>
                                                </div>
                                                <!-- Actions untuk periode aktif -->
                                                <div v-else class="shrink-0">
                                                    <Button size="sm" variant="outline" @click="startEditPeriod(pr)" class="h-7 text-xs">Ubah</Button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
