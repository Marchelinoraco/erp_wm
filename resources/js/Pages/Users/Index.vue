<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { confirm } from '@/lib/confirm'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

const props  = defineProps({ users: Array })
const me     = usePage().props.auth.user

// --- Roles config — harus selalu sinkron dengan enum users.role di database ---
const ROLES = [
    { value: 'admin',        label: 'Admin',         color: 'bg-red-100 text-red-700',       dot: 'bg-red-500' },
    { value: 'sales',        label: 'Sales',         color: 'bg-blue-100 text-blue-700',     dot: 'bg-blue-500' },
    { value: 'accountant',   label: 'Akuntansi',     color: 'bg-green-100 text-green-700',   dot: 'bg-green-500' },
    { value: 'operation',    label: 'Operation',     color: 'bg-cyan-100 text-cyan-700',     dot: 'bg-cyan-500' },
    { value: 'travel_agent', label: 'Travel Agent',  color: 'bg-pink-100 text-pink-700',     dot: 'bg-pink-500' },
    { value: 'guide',        label: 'Guide',         color: 'bg-purple-100 text-purple-700', dot: 'bg-purple-500' },
    { value: 'driver',       label: 'Driver',        color: 'bg-yellow-100 text-yellow-700', dot: 'bg-yellow-500' },
    { value: 'tour_leader',  label: 'Tour Leader',   color: 'bg-orange-100 text-orange-700', dot: 'bg-orange-500' },
]
const GROUP_ORDER = ROLES.map(r => r.value)

function roleConfig(value) {
    return ROLES.find(r => r.value === value) ?? { label: value, color: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' }
}

// --- Add ---
const showAdd = ref(false)
const addForm = useForm({ name: '', email: '', password: '', role: 'sales' })
function submitAdd() {
    addForm.post(route('users.store'), {
        onSuccess: () => { showAdd.value = false; addForm.reset(); addForm.role = 'sales' },
    })
}

// --- Edit ---
const editTarget = ref(null)
const editForm   = useForm({ name: '', email: '', password: '', role: 'sales' })
function openEdit(u) {
    editTarget.value = u
    editForm.name     = u.name
    editForm.email    = u.email
    editForm.password = ''
    editForm.role     = u.role
}
function submitEdit() {
    editForm.patch(route('users.update', editTarget.value.id), {
        onSuccess: () => { editTarget.value = null },
    })
}

// --- Delete ---
async function deleteUser(u) {
    if (u.id === me.id) return
    if (await confirm({ title: `Hapus akun "${u.name}"?`, description: 'Tindakan ini tidak bisa dibatalkan.', confirmLabel: 'Hapus' })) {
        router.delete(route('users.destroy', u.id))
    }
}

// --- Pencarian & filter role (klik kartu statistik utk filter cepat) ---
const search       = ref('')
const activeFilter = ref(null) // role value, atau null = semua

function toggleFilter(role) {
    activeFilter.value = activeFilter.value === role ? null : role
}

function countByRole(role) {
    return props.users.filter(u => u.role === role).length
}

const filteredUsers = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.users.filter(u => {
        const matchesRole   = !activeFilter.value || u.role === activeFilter.value
        const matchesSearch = !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
        return matchesRole && matchesSearch
    })
})

const grouped = computed(() =>
    GROUP_ORDER
        .map(role => ({
            role,
            config: roleConfig(role),
            users:  filteredUsers.value.filter(u => u.role === role),
        }))
        .filter(g => g.users.length > 0)
)

function formatDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="Kelola Akun" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Kelola Akun</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ users.length }} akun terdaftar di seluruh role</p>
                </div>
                <Button size="sm" @click="showAdd = true">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Akun
                </Button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Stats — klik utk filter cepat -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    <button
                        v-for="r in ROLES" :key="r.value"
                        type="button"
                        class="cursor-pointer rounded-lg border bg-white p-3 text-center shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5"
                        :class="activeFilter === r.value ? 'ring-2 ring-offset-1 ring-gray-400 border-transparent' : 'border-gray-200'"
                        @click="toggleFilter(r.value)"
                    >
                        <div class="text-xl font-bold text-gray-800">{{ countByRole(r.value) }}</div>
                        <div class="mt-1.5 flex items-center justify-center gap-1">
                            <span :class="['h-1.5 w-1.5 rounded-full', r.dot]"></span>
                            <span class="text-[11px] font-medium text-gray-500 truncate">{{ r.label }}</span>
                        </div>
                    </button>
                </div>

                <!-- Toolbar: search + active filter chip -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 min-w-[14rem]">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m21 21-4.35-4.35" />
                        </svg>
                        <Input v-model="search" placeholder="Cari nama atau email..." class="pl-9" />
                    </div>
                    <button
                        v-if="activeFilter"
                        type="button"
                        class="cursor-pointer inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        @click="activeFilter = null"
                    >
                        Filter: {{ roleConfig(activeFilter).label }}
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Table -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50/80">
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Nama</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Email</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Role</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Dibuat</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="group in grouped" :key="group.role">
                                <!-- Group header -->
                                <tr class="bg-gray-50/60">
                                    <td colspan="5" class="px-4 py-1.5">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span :class="['h-1.5 w-1.5 rounded-full', group.config.dot]"></span>
                                            <span class="text-xs font-semibold text-gray-600">{{ group.config.label }} ({{ group.users.length }})</span>
                                        </span>
                                    </td>
                                </tr>
                                <!-- Users in group -->
                                <tr
                                    v-for="u in group.users"
                                    :key="u.id"
                                    class="border-t border-gray-50 hover:bg-gray-50/50 transition-colors"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div :class="['flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold uppercase', group.config.color]">
                                                {{ u.name.charAt(0) }}
                                            </div>
                                            <span class="font-medium text-gray-900">{{ u.name }}</span>
                                            <span v-if="u.id === me.id" class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">Kamu</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ u.email }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', group.config.color]">
                                            {{ group.config.label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground text-xs">{{ formatDate(u.created_at) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                :aria-label="`Edit akun ${u.name}`"
                                                @click="openEdit(u)"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded p-1.5 transition-colors',
                                                    u.id === me.id
                                                        ? 'cursor-not-allowed text-muted-foreground/30'
                                                        : 'cursor-pointer text-muted-foreground hover:bg-destructive/10 hover:text-destructive'
                                                ]"
                                                :aria-label="u.id === me.id ? 'Tidak bisa hapus akun sendiri' : `Hapus akun ${u.name}`"
                                                :title="u.id === me.id ? 'Tidak bisa hapus akun sendiri' : 'Hapus'"
                                                :disabled="u.id === me.id"
                                                @click="deleteUser(u)"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14H6L5 6"/>
                                                    <path d="M10 11v6M14 11v6"/>
                                                    <path d="M9 6V4h6v2"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <p class="text-sm text-muted-foreground">
                                        {{ users.length === 0 ? 'Belum ada akun.' : 'Tidak ada akun yang cocok.' }}
                                    </p>
                                    <button
                                        v-if="users.length > 0 && (search || activeFilter)"
                                        type="button"
                                        class="cursor-pointer mt-2 text-xs font-medium text-primary hover:underline"
                                        @click="search = ''; activeFilter = null"
                                    >
                                        Hapus pencarian & filter
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Dialog -->
        <Dialog :open="showAdd" @update:open="showAdd = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Tambah Akun</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitAdd" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Nama <span class="text-destructive">*</span></Label>
                        <Input v-model="addForm.name" placeholder="Nama lengkap" autofocus />
                        <p v-if="addForm.errors.name" class="text-xs text-destructive">{{ addForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Email <span class="text-destructive">*</span></Label>
                        <Input v-model="addForm.email" type="email" placeholder="email@example.com" />
                        <p v-if="addForm.errors.email" class="text-xs text-destructive">{{ addForm.errors.email }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Password <span class="text-destructive">*</span></Label>
                        <Input v-model="addForm.password" type="password" placeholder="Min. 8 karakter" />
                        <p v-if="addForm.errors.password" class="text-xs text-destructive">{{ addForm.errors.password }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Role <span class="text-destructive">*</span></Label>
                        <Select v-model="addForm.role">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in ROLES" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="showAdd = false">Batal</Button>
                        <Button type="submit" :disabled="addForm.processing" :loading="addForm.processing">
                            {{ addForm.processing ? 'Menyimpan...' : 'Buat Akun' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Dialog -->
        <Dialog :open="!!editTarget" @update:open="v => { if (!v) editTarget = null }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Akun</DialogTitle>
                </DialogHeader>
                <form v-if="editTarget" @submit.prevent="submitEdit" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Nama <span class="text-destructive">*</span></Label>
                        <Input v-model="editForm.name" />
                        <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Email <span class="text-destructive">*</span></Label>
                        <Input v-model="editForm.email" type="email" />
                        <p v-if="editForm.errors.email" class="text-xs text-destructive">{{ editForm.errors.email }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Password Baru <span class="text-muted-foreground text-xs font-normal">(kosongkan jika tidak diganti)</span></Label>
                        <Input v-model="editForm.password" type="password" placeholder="Min. 8 karakter" />
                        <p v-if="editForm.errors.password" class="text-xs text-destructive">{{ editForm.errors.password }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Role <span class="text-destructive">*</span></Label>
                        <Select v-model="editForm.role">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in ROLES" :key="r.value" :value="r.value">
                                    {{ r.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="editTarget = null">Batal</Button>
                        <Button type="submit" :disabled="editForm.processing" :loading="editForm.processing">
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
