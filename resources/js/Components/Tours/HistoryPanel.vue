<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { confirm } from '@/lib/confirm'
import { STATUS_CONFIG } from '@/lib/tourConstants'

const props = defineProps({ tour: Object })

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
    revision: '↺', note: '📝', call: '📞', meeting: '👥', email: '✉', confirmed: '✓', cancelled: '✕',
}

const showHistoryForm = ref(false)
const historyForm     = useForm({ type: 'revision', description: '' })

function submitHistory() {
    historyForm.post(route('tours.histories.store', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => { historyForm.reset('description'); showHistoryForm.value = false },
    })
}

async function deleteHistory(historyId) {
    if (await confirm({ title: 'Hapus catatan ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('tours.histories.destroy', [props.tour.id, historyId]), {
            preserveScroll: true, only: ['tour'],
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
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 class="font-semibold">Riwayat Revisi</h3>
            <button type="button" @click="showHistoryForm = !showHistoryForm"
                class="text-xs text-primary font-medium hover:underline">
                {{ showHistoryForm ? 'Tutup' : '+ Tambah Catatan' }}
            </button>
        </div>

        <div v-if="showHistoryForm" class="px-5 py-4 border-b bg-muted/20 space-y-3">
            <div class="space-y-1.5">
                <label class="text-xs font-medium">Tipe Catatan</label>
                <div class="flex flex-wrap gap-2">
                    <button v-for="(label, key) in HISTORY_TYPES" :key="key" type="button"
                        @click="historyForm.type = key"
                        :class="[
                            'px-3 py-1 rounded-full text-xs border transition-colors',
                            historyForm.type === key
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-white text-muted-foreground hover:border-muted-foreground/60'
                        ]">
                        {{ label }}
                    </button>
                </div>
            </div>
            <div class="space-y-1.5">
                <label class="text-xs font-medium">Keterangan</label>
                <Textarea v-model="historyForm.description" rows="3"
                    placeholder="Mis. Customer minta perubahan hotel dari Aston ke Novotel, dan tambah 1 hari di Tomohon..." />
                <p v-if="historyForm.errors.description" class="text-xs text-destructive">{{ historyForm.errors.description }}</p>
            </div>
            <Button type="button" size="sm" @click="submitHistory" :disabled="historyForm.processing" class="w-full">
                Simpan Catatan
            </Button>
        </div>

        <div v-if="!tour.histories?.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
            Belum ada riwayat. Tambahkan catatan pertama.
        </div>
        <div v-else class="divide-y">
            <div v-for="h in tour.histories" :key="h.id" class="px-5 py-4 flex gap-3">
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
                            <button type="button" @click="deleteHistory(h.id)"
                                class="text-muted-foreground hover:text-destructive text-xs">✕</button>
                        </div>
                    </div>
                    <p class="text-sm text-foreground leading-relaxed">{{ h.description }}</p>
                    <p v-if="h.created_by" class="text-xs text-muted-foreground">— {{ h.created_by }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
