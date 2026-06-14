<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'

const props = defineProps({ tour: Object, fieldUsers: Array, manifestUrl: String })

const ROLE_LABELS = { guide: 'Guide', driver: 'Driver', tour_leader: 'Tour Leader' }
const ROLE_COLORS = {
    guide:       'bg-blue-100 text-blue-700',
    driver:      'bg-green-100 text-green-700',
    tour_leader: 'bg-yellow-100 text-yellow-700',
}

const assignDialogOpen  = ref(false)
const editingAssignment = ref(null)
const linkCopied        = ref(false)

const assignForm = useForm({
    role: 'guide', user_id: null, person_name: '', phone: '', vehicle: '', pickup_time: '', notes: '',
})

function openAddAssignment() {
    editingAssignment.value = null
    assignForm.reset()
    assignForm.role = 'guide'
    assignDialogOpen.value = true
}

function openEditAssignment(a) {
    editingAssignment.value = a
    assignForm.role        = a.role
    assignForm.user_id     = a.user_id     ?? null
    assignForm.person_name = a.person_name ?? ''
    assignForm.phone       = a.phone       ?? ''
    assignForm.vehicle     = a.vehicle     ?? ''
    assignForm.pickup_time = a.pickup_time ?? ''
    assignForm.notes       = a.notes       ?? ''
    assignDialogOpen.value = true
}

function onFieldUserSelect(userId) {
    const u = props.fieldUsers?.find(f => f.id === Number(userId))
    if (u) {
        assignForm.user_id     = u.id
        assignForm.person_name = u.name
        assignForm.role        = u.role
    }
}

function submitAssignment() {
    if (editingAssignment.value) {
        assignForm.patch(route('assignments.update', editingAssignment.value.id), {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => { assignDialogOpen.value = false },
        })
    } else {
        assignForm.post(route('assignments.store', props.tour.id), {
            preserveScroll: true,
            only: ['tour'],
            onSuccess: () => { assignDialogOpen.value = false; assignForm.reset() },
        })
    }
}

async function deleteAssignment(id) {
    if (await confirm({ title: 'Hapus assignment ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('assignments.destroy', id), {
            preserveScroll: true, only: ['tour'],
        })
    }
}

function copyManifestLink() {
    navigator.clipboard.writeText(props.manifestUrl).then(() => {
        linkCopied.value = true
        setTimeout(() => { linkCopied.value = false }, 2000)
    })
}
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 class="font-semibold">Operasional</h3>
            <Button size="sm" variant="outline" @click="openAddAssignment">+ Tambah Guide / Driver</Button>
        </div>

        <div v-if="!tour.assignments?.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
            Belum ada guide / driver ditugaskan.
        </div>
        <div v-else class="divide-y">
            <div v-for="a in tour.assignments" :key="a.id" class="flex items-center gap-4 px-5 py-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-sm">{{ a.person_name ?? '—' }}</span>
                        <span :class="[ROLE_COLORS[a.role], 'text-xs px-2 py-0.5 rounded-full font-medium']">
                            {{ ROLE_LABELS[a.role] ?? a.role }}
                        </span>
                    </div>
                    <div class="text-xs text-muted-foreground mt-0.5 flex flex-wrap gap-3">
                        <span v-if="a.phone">📱 {{ a.phone }}</span>
                        <span v-if="a.vehicle">🚐 {{ a.vehicle }}</span>
                        <span v-if="a.pickup_time">🕐 {{ a.pickup_time }}</span>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <Button size="sm" variant="outline" @click="openEditAssignment(a)">Edit</Button>
                    <Button size="sm" variant="destructive" @click="deleteAssignment(a.id)">Hapus</Button>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t bg-muted/20">
            <p class="text-xs font-semibold text-muted-foreground mb-2">SHARE MANIFEST KE GUIDE / DRIVER</p>
            <div class="flex gap-2">
                <input :value="manifestUrl" readonly
                    class="flex-1 border rounded px-3 py-1.5 text-xs font-mono bg-white truncate focus:outline-none" />
                <Button size="sm" variant="outline" @click="copyManifestLink">
                    {{ linkCopied ? '✓ Disalin!' : 'Salin Link' }}
                </Button>
                <a :href="manifestUrl" target="_blank">
                    <Button size="sm" variant="outline">Buka</Button>
                </a>
            </div>
            <p class="text-xs text-muted-foreground mt-1.5">Link ini aman dibagikan via WhatsApp — tidak perlu login.</p>
        </div>
    </div>

    <!-- Assignment Dialog -->
    <Dialog v-model:open="assignDialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingAssignment ? 'Edit Assignment' : 'Tambah Guide / Driver' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitAssignment" class="space-y-4 mt-2">
                <div v-if="fieldUsers?.length" class="space-y-1.5">
                    <Label>Link ke Akun (Opsional)</Label>
                    <Select :model-value="assignForm.user_id?.toString() ?? ''" @update:model-value="onFieldUserSelect">
                        <SelectTrigger><SelectValue placeholder="— Pilih atau isi manual —" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="fu in fieldUsers" :key="fu.id" :value="fu.id.toString()">
                                {{ fu.name }}
                                <span class="ml-1 text-xs text-gray-400 capitalize">· {{ fu.role.replace('_', ' ') }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <Label>Role</Label>
                    <Select v-model="assignForm.role">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="guide">Tour Guide</SelectItem>
                            <SelectItem value="driver">Driver</SelectItem>
                            <SelectItem value="tour_leader">Tour Leader</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="a_name">Nama</Label>
                        <Input id="a_name" v-model="assignForm.person_name" placeholder="Nama lengkap" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="a_phone">Telepon / WA</Label>
                        <Input id="a_phone" v-model="assignForm.phone" placeholder="08xx..." />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="a_vehicle">Kendaraan</Label>
                        <Input id="a_vehicle" v-model="assignForm.vehicle" placeholder="Mis. Innova B 1234 XX" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="a_pickup">Pickup Time</Label>
                        <Input id="a_pickup" type="time" v-model="assignForm.pickup_time" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label for="a_notes">Catatan</Label>
                    <Input id="a_notes" v-model="assignForm.notes" placeholder="Info tambahan..." />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" @click="assignDialogOpen = false">Batal</Button>
                    <Button type="submit" :disabled="assignForm.processing">
                        {{ editingAssignment ? 'Simpan' : 'Tambahkan' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
