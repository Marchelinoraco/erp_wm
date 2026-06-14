<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirm } from '@/lib/confirm'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

const props = defineProps({
    reminders: Array,
    tours:     Array,
    stats:     Object,
})

// --- Add form ---
const showAdd  = ref(false)
const addForm  = useForm({
    tour_id:   'none',
    title:     '',
    notes:     '',
    remind_at: '',
})
function submitAdd() {
    if (addForm.tour_id === 'none') addForm.tour_id = ''
    addForm.post(route('reminders.store'), {
        onSuccess: () => { showAdd.value = false; addForm.reset(); addForm.tour_id = 'none' },
    })
}

// --- Edit form ---
const editTarget = ref(null)
const editForm   = useForm({
    tour_id:   'none',
    title:     '',
    notes:     '',
    remind_at: '',
    is_done:   false,
})
function openEdit(r) {
    editTarget.value = r
    editForm.title     = r.title
    editForm.notes     = r.notes ?? ''
    editForm.remind_at = r.remind_at
    editForm.is_done   = r.is_done
    editForm.tour_id   = r.tour_id ? String(r.tour_id) : 'none'
}
function submitEdit() {
    if (editForm.tour_id === 'none') editForm.tour_id = ''
    editForm.patch(route('reminders.update', editTarget.value.id), {
        onSuccess: () => { editTarget.value = null },
    })
}

// --- Actions ---
function markDone(r) {
    router.patch(route('reminders.done', r.id))
}
async function deleteReminder(r) {
    if (await confirm({ title: 'Hapus reminder?', description: 'Reminder ini akan dihapus permanen.', confirmLabel: 'Hapus' })) {
        router.delete(route('reminders.destroy', r.id))
    }
}

// --- Helpers ---
function statusClass(r) {
    if (r.is_done) return 'border-l-4 border-muted bg-muted/20 opacity-60'
    if (r.is_overdue) return 'border-l-4 border-destructive bg-destructive/5'
    if (r.is_today) return 'border-l-4 border-orange-400 bg-orange-50'
    return 'border-l-4 border-blue-400 bg-blue-50/40'
}

function badgeClass(r) {
    if (r.is_done) return 'bg-muted text-muted-foreground'
    if (r.is_overdue) return 'bg-destructive/10 text-destructive'
    if (r.is_today) return 'bg-orange-100 text-orange-700'
    return 'bg-blue-100 text-blue-700'
}

function badgeLabel(r) {
    if (r.is_done) return 'Selesai'
    if (r.is_overdue) return 'Terlambat'
    if (r.is_today) return 'Hari ini'
    return 'Akan datang'
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const PIPELINE_LABEL = {
    inquiry:          'Inquiry',
    quotation_draft:  'Draft Quotation',
    quotation_sent:   'Sent',
    follow_up:        'Follow Up',
    negotiation:      'Negosiasi',
    confirmed:        'Confirmed',
    cancelled:        'Cancelled',
}
</script>

<template>
    <Head title="Reminder" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Reminder Inquiry</h2>
                <Button size="sm" @click="showAdd = true">+ Tambah Reminder</Button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Booking pending banner -->
                <Link
                    v-if="$page.props.pendingBookings > 0"
                    :href="route('bookings.index')"
                    class="flex items-center justify-between gap-3 rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm shadow-sm transition-colors hover:bg-orange-100"
                >
                    <span class="flex items-center gap-2 text-orange-800">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <span><b>{{ $page.props.pendingBookings }} supplier</b> belum di-booking untuk tour confirmed.</span>
                    </span>
                    <span class="shrink-0 font-medium text-orange-700">Buka Booking →</span>
                </Link>

                <!-- Stats -->
                <div class="grid grid-cols-4 gap-3">
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-destructive">{{ stats.overdue }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Terlambat</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-orange-500">{{ stats.today }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Hari ini</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-blue-600">{{ stats.upcoming }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Akan datang</div>
                    </div>
                    <div class="rounded-lg border bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl font-bold text-muted-foreground">{{ stats.done }}</div>
                        <div class="text-xs text-muted-foreground mt-0.5">Selesai</div>
                    </div>
                </div>

                <!-- Reminder list -->
                <div class="space-y-2">
                    <div v-if="reminders.length === 0" class="rounded-lg border bg-white px-4 py-16 text-center shadow-sm">
                        <svg class="mx-auto mb-3 h-9 w-9 text-muted-foreground opacity-25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <p class="text-sm text-muted-foreground">Belum ada reminder. Klik <strong>+ Tambah Reminder</strong> untuk mulai.</p>
                    </div>

                    <div
                        v-for="r in reminders"
                        :key="r.id"
                        :class="['rounded-lg border bg-white shadow-sm p-4', statusClass(r)]"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Done checkbox -->
                            <button
                                v-if="!r.is_done"
                                type="button"
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-full border-2 border-muted-foreground/40 hover:border-primary transition-colors"
                                title="Tandai selesai"
                                @click="markDone(r)"
                            />
                            <div v-else class="mt-0.5 h-5 w-5 shrink-0 rounded-full bg-muted flex items-center justify-center">
                                <svg class="h-3 w-3 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span :class="['text-sm font-medium', r.is_done ? 'line-through text-muted-foreground' : '']">
                                        {{ r.title }}
                                    </span>
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', badgeClass(r)]">
                                        {{ badgeLabel(r) }}
                                    </span>
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                    <span>📅 {{ formatDate(r.remind_at) }}</span>
                                    <span v-if="r.tour">
                                        🔗
                                        <Link :href="route('tours.edit', r.tour.id)" class="underline underline-offset-2 hover:text-foreground">
                                            {{ r.tour.code }} — {{ PIPELINE_LABEL[r.tour.status] }}
                                        </Link>
                                    </span>
                                </div>

                                <p v-if="r.notes" class="mt-1.5 text-sm text-muted-foreground whitespace-pre-line">
                                    {{ r.notes }}
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex shrink-0 gap-1">
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    title="Edit"
                                    @click="openEdit(r)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    title="Hapus"
                                    @click="deleteReminder(r)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Dialog -->
        <Dialog :open="showAdd" @update:open="showAdd = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Tambah Reminder</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitAdd" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Judul Reminder <span class="text-destructive">*</span></Label>
                        <Input v-model="addForm.title" placeholder="Mis. Follow up quotation ke customer" autofocus />
                        <p v-if="addForm.errors.title" class="text-xs text-destructive">{{ addForm.errors.title }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Tanggal Remind <span class="text-destructive">*</span></Label>
                        <Input type="date" v-model="addForm.remind_at" />
                        <p v-if="addForm.errors.remind_at" class="text-xs text-destructive">{{ addForm.errors.remind_at }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Link ke Tour (opsional)</Label>
                        <Select v-model="addForm.tour_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih tour..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">— Tanpa Tour —</SelectItem>
                                <SelectItem v-for="t in tours" :key="t.id" :value="String(t.id)">
                                    {{ t.code }} — {{ t.title || '(tanpa judul)' }}
                                    <span class="text-muted-foreground text-xs ml-1">[{{ PIPELINE_LABEL[t.status] }}]</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Catatan (opsional)</Label>
                        <Textarea v-model="addForm.notes" rows="3" placeholder="Detail follow-up..." />
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="showAdd = false">Batal</Button>
                        <Button type="submit" :disabled="addForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Dialog -->
        <Dialog :open="!!editTarget" @update:open="v => { if (!v) editTarget = null }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Reminder</DialogTitle>
                </DialogHeader>
                <form v-if="editTarget" @submit.prevent="submitEdit" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Judul Reminder <span class="text-destructive">*</span></Label>
                        <Input v-model="editForm.title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Tanggal Remind <span class="text-destructive">*</span></Label>
                        <Input type="date" v-model="editForm.remind_at" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Link ke Tour (opsional)</Label>
                        <Select v-model="editForm.tour_id">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">— Tanpa Tour —</SelectItem>
                                <SelectItem v-for="t in tours" :key="t.id" :value="String(t.id)">
                                    {{ t.code }} — {{ t.title || '(tanpa judul)' }}
                                    <span class="text-muted-foreground text-xs ml-1">[{{ PIPELINE_LABEL[t.status] }}]</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Catatan (opsional)</Label>
                        <Textarea v-model="editForm.notes" rows="3" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_done_edit" v-model="editForm.is_done" class="h-4 w-4 rounded border" />
                        <label for="is_done_edit" class="text-sm">Tandai sebagai selesai</label>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="editTarget = null">Batal</Button>
                        <Button type="submit" :disabled="editForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
