<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    tour:          Object,
    miceTemplates: Array,
})

// ── Terapkan Template ─────────────────────────────────────────────────────────
const selectedTemplateId = ref(null)

const selectedTemplate = computed(() =>
    props.miceTemplates.find(t => t.id === selectedTemplateId.value) ?? null
)

const applying = ref(false)
function applyTemplate() {
    if (!selectedTemplate.value) return
    applying.value = true
    router.post(
        route('mice-templates.apply', { miceTemplate: selectedTemplate.value.id, tour: props.tour.id }),
        {},
        {
            preserveScroll: true,
            only: ['tour'],
            onFinish: () => {
                applying.value = false
                selectedTemplateId.value = null
            },
        }
    )
}

// ── Simpan dari Tour ──────────────────────────────────────────────────────────
const showSaveForm = ref(false)
const saveForm = useForm({ name: '', description: '' })

function submitSave() {
    saveForm.post(route('mice-templates.save-from-tour', props.tour.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSaveForm.value = false
            saveForm.reset()
        },
    })
}

// ── Kelola Template ───────────────────────────────────────────────────────────
const showManage    = ref(false)
const editingId     = ref(null)
const editForm      = useForm({ name: '', description: '' })

function startEdit(t) {
    editingId.value      = t.id
    editForm.name        = t.name
    editForm.description = t.description ?? ''
}

function saveEdit(id) {
    editForm.patch(route('mice-templates.update', id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

async function deleteTemplate(t) {
    if (await confirm({ title: `Hapus template "${t.name}"?`, confirmLabel: 'Hapus' })) {
        router.delete(route('mice-templates.destroy', t.id), { preserveScroll: true })
    }
}

// ── Buat Template Manual ──────────────────────────────────────────────────────
const showCreateForm = ref(false)
const createForm = useForm({
    name:        '',
    description: '',
    items: [
        { label: '', pax_mode: 'shared', unit_sell: '', qty: 1, nights: 1, notes: '' },
    ],
})

function addCreateItem() {
    createForm.items.push({ label: '', pax_mode: 'per_pax', unit_sell: '', qty: 1, nights: 1, notes: '' })
}
function removeCreateItem(i) {
    createForm.items.splice(i, 1)
}
function submitCreate() {
    createForm.post(route('mice-templates.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateForm.value = false
            createForm.reset()
            createForm.items = [{ label: '', pax_mode: 'shared', unit_sell: '', qty: 1, nights: 1, notes: '' }]
        },
    })
}

const hasQItems = computed(() =>
    (props.tour.quotation_items ?? []).filter(qi => qi.status !== 'rejected').length > 0
)
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b bg-pink-50/40">
            <div>
                <h3 class="font-semibold text-pink-900">Template Paket MICE</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Terapkan paket item sekaligus, atau simpan event ini sebagai template baru.</p>
            </div>
            <div class="flex gap-2">
                <Button size="sm" variant="outline" class="text-xs h-7" @click="showManage = !showManage">
                    {{ showManage ? 'Tutup' : '⚙ Kelola' }}
                </Button>
                <Button size="sm" variant="outline" class="text-xs h-7" @click="showCreateForm = !showCreateForm">
                    {{ showCreateForm ? 'Tutup' : '+ Buat Template' }}
                </Button>
            </div>
        </div>

        <!-- Terapkan Template -->
        <div class="px-5 py-4 space-y-3">
            <div v-if="!miceTemplates.length" class="text-sm text-muted-foreground py-2 text-center">
                Belum ada template tersimpan.
                <button type="button" class="underline text-pink-700 ml-1" @click="showCreateForm = true">Buat template pertama →</button>
            </div>

            <template v-else>
                <div class="flex gap-2 items-end flex-wrap">
                    <div class="flex-1 min-w-48 space-y-1.5">
                        <Label class="text-xs">Pilih Template</Label>
                        <select
                            v-model="selectedTemplateId"
                            class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option :value="null">— Pilih template… —</option>
                            <option v-for="t in miceTemplates" :key="t.id" :value="t.id">
                                {{ t.name }} ({{ t.items?.length ?? 0 }} item)
                            </option>
                        </select>
                    </div>
                    <Button
                        size="sm"
                        :disabled="!selectedTemplateId || applying"
                        @click="applyTemplate"
                        class="h-9 bg-pink-700 hover:bg-pink-800 text-white"
                    >
                        {{ applying ? 'Menerapkan…' : '▶ Terapkan' }}
                    </Button>
                </div>

                <!-- Preview item template yang dipilih -->
                <div v-if="selectedTemplate" class="rounded-md border border-pink-100 bg-pink-50/50 divide-y divide-pink-100">
                    <div class="px-3 py-2">
                        <p class="text-xs font-semibold text-pink-800">Preview: {{ selectedTemplate.name }}</p>
                        <p v-if="selectedTemplate.description" class="text-[11px] text-muted-foreground">{{ selectedTemplate.description }}</p>
                    </div>
                    <div v-for="(item, i) in selectedTemplate.items" :key="i"
                        class="px-3 py-2 flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <span class="text-sm">{{ item.label }}</span>
                            <span class="ml-1.5 text-[10px] px-1.5 py-0.5 rounded border"
                                :class="item.pax_mode === 'shared'
                                    ? 'bg-purple-50 text-purple-700 border-purple-200'
                                    : 'bg-teal-50 text-teal-700 border-teal-200'">
                                {{ item.pax_mode === 'shared' ? '÷ Dibagi pax' : '∕ Per pax' }}
                            </span>
                        </div>
                        <span class="text-xs font-mono text-muted-foreground shrink-0">
                            {{ item.qty > 1 || item.nights > 1 ? `${item.qty}×${item.nights}×` : '' }}{{ fmtRp(item.unit_sell) }}
                        </span>
                    </div>
                </div>
            </template>

            <!-- Simpan event ini sebagai template -->
            <div class="border-t pt-3 mt-1">
                <div v-if="!showSaveForm">
                    <button
                        type="button"
                        class="text-xs text-pink-700 hover:underline disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="!hasQItems"
                        :title="!hasQItems ? 'Tambahkan item di Produk Penawaran terlebih dahulu' : ''"
                        @click="showSaveForm = true"
                    >
                        ↓ Simpan item event ini sebagai template baru
                    </button>
                    <span v-if="!hasQItems" class="text-[11px] text-muted-foreground ml-2">(belum ada item)</span>
                </div>
                <form v-else @submit.prevent="submitSave" class="space-y-2">
                    <p class="text-xs font-medium text-pink-800">Simpan {{ (tour.quotation_items ?? []).filter(qi => qi.status !== 'rejected').length }} item sebagai template:</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-xs">Nama Template <span class="text-destructive">*</span></Label>
                            <Input v-model="saveForm.name" placeholder="Mis. Paket Meeting 50 Pax" class="h-8 text-sm" />
                            <p v-if="saveForm.errors.name" class="text-xs text-destructive">{{ saveForm.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Deskripsi <span class="text-muted-foreground font-normal">(opsional)</span></Label>
                            <Input v-model="saveForm.description" placeholder="Catatan singkat…" class="h-8 text-sm" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="showSaveForm = false; saveForm.reset()">Batal</Button>
                        <Button type="submit" size="sm" class="h-7 text-xs bg-pink-700 hover:bg-pink-800 text-white" :disabled="saveForm.processing">Simpan Template</Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Buat Template Manual -->
        <div v-if="showCreateForm" class="border-t px-5 py-4 bg-muted/20 space-y-3">
            <p class="text-sm font-semibold">Buat Template Manual</p>
            <form @submit.prevent="submitCreate" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs">Nama Template <span class="text-destructive">*</span></Label>
                        <Input v-model="createForm.name" placeholder="Mis. Paket Gathering Standar" class="h-8 text-sm" />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">Deskripsi</Label>
                        <Input v-model="createForm.description" placeholder="Opsional…" class="h-8 text-sm" />
                    </div>
                </div>

                <!-- Daftar item template -->
                <div class="space-y-2">
                    <Label class="text-xs">Item Paket</Label>
                    <div v-for="(item, i) in createForm.items" :key="i"
                        class="grid grid-cols-12 gap-2 items-center rounded border p-2 bg-white">
                        <Input v-model="item.label" placeholder="Nama item…" class="h-8 text-sm col-span-4" />
                        <div class="col-span-2 flex rounded border p-0.5 h-8">
                            <button type="button"
                                class="flex-1 rounded text-[10px] transition-colors"
                                :class="item.pax_mode === 'per_pax' ? 'bg-teal-600 text-white' : 'text-gray-500'"
                                @click="item.pax_mode = 'per_pax'">Per Pax</button>
                            <button type="button"
                                class="flex-1 rounded text-[10px] transition-colors"
                                :class="item.pax_mode === 'shared' ? 'bg-purple-600 text-white' : 'text-gray-500'"
                                @click="item.pax_mode = 'shared'">Dibagi</button>
                        </div>
                        <Input type="number" v-model.number="item.unit_sell" placeholder="Harga" min="0" step="1000" class="h-8 text-sm text-right col-span-2" />
                        <Input type="number" v-model.number="item.qty" min="1" title="Qty" class="h-8 text-sm text-center col-span-1" />
                        <Input type="number" v-model.number="item.nights" min="1" title="Malam/Unit" class="h-8 text-sm text-center col-span-1" />
                        <Input v-model="item.notes" placeholder="Catatan…" class="h-8 text-sm col-span-1" />
                        <button type="button" class="col-span-1 text-gray-300 hover:text-red-500 text-center" @click="removeCreateItem(i)">×</button>
                    </div>
                    <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="addCreateItem">
                        + Tambah item
                    </button>
                    <p v-if="createForm.errors['items']" class="text-xs text-destructive">{{ createForm.errors['items'] }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" size="sm" variant="outline" @click="showCreateForm = false; createForm.reset()">Batal</Button>
                    <Button type="submit" size="sm" class="bg-pink-700 hover:bg-pink-800 text-white" :disabled="createForm.processing">Simpan Template</Button>
                </div>
            </form>
        </div>

        <!-- Kelola (Edit / Hapus) Template -->
        <div v-if="showManage && miceTemplates.length" class="border-t divide-y">
            <div v-for="t in miceTemplates" :key="t.id" class="px-5 py-3">
                <template v-if="editingId === t.id">
                    <form @submit.prevent="saveEdit(t.id)" class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-xs">Nama</Label>
                            <Input v-model="editForm.name" class="h-8 text-sm" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Deskripsi</Label>
                            <Input v-model="editForm.description" class="h-8 text-sm" />
                        </div>
                        <div class="col-span-2 flex justify-end gap-2 mt-1">
                            <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="editingId = null">Batal</Button>
                            <Button type="submit" size="sm" class="h-7 text-xs" :disabled="editForm.processing">Simpan</Button>
                        </div>
                    </form>
                </template>
                <template v-else>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ t.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ t.items?.length ?? 0 }} item
                                <span v-if="t.description"> · {{ t.description }}</span>
                            </p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="startEdit(t)">Edit</Button>
                            <Button size="sm" variant="destructive" class="h-7 text-xs" @click="deleteTemplate(t)">Hapus</Button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
