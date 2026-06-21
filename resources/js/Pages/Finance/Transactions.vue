<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'

const props = defineProps({
    month: String,
    transactions: Array,
    categories: Array,
    cashAccounts: Array,
    summary: Object,
})

function fmtDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function changeMonth(e) {
    router.get(route('finance.transactions'), { month: e.target.value }, { preserveState: false })
}

// ── Transaksi dialog ──
const dialogOpen = ref(false)
const editing = ref(null)
const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    direction: 'out',
    fin_category_id: null,
    cash_account_id: props.cashAccounts[0]?.id ?? null,
    amount: '',
    description: '',
})

const formCategories = computed(() =>
    props.categories.filter(c => c.is_active && c.type === (form.direction === 'in' ? 'income' : 'expense'))
)

function setDirection(dir) {
    form.direction = dir
    showNewCat.value = false
    if (!formCategories.value.some(c => c.id === form.fin_category_id)) {
        form.fin_category_id = formCategories.value[0]?.id ?? null
    }
}

// ── Tambah kategori inline (dari form transaksi) ──
const showNewCat = ref(false)
const newCatName = ref('')
const creatingCat = ref(false)
function toggleNewCat() {
    showNewCat.value = !showNewCat.value
    newCatName.value = ''
}
function createCategory() {
    const name = newCatName.value.trim()
    if (!name) return
    const type = form.direction === 'in' ? 'income' : 'expense'
    const beforeIds = new Set(props.categories.map(c => c.id))
    creatingCat.value = true
    router.post(route('finance.categories.store'), { name, type }, {
        preserveScroll: true,
        preserveState: true,
        only: ['categories'],
        onSuccess: () => {
            const added = props.categories.find(c => c.type === type && !beforeIds.has(c.id))
            if (added) form.fin_category_id = added.id
            showNewCat.value = false
            newCatName.value = ''
        },
        onFinish: () => { creatingCat.value = false },
    })
}

function openAdd() {
    editing.value = null
    form.reset()
    form.date = new Date().toISOString().slice(0, 10)
    form.direction = 'out'
    form.cash_account_id = props.cashAccounts[0]?.id ?? null
    form.fin_category_id = formCategories.value[0]?.id ?? null
    form.clearErrors()
    showNewCat.value = false
    dialogOpen.value = true
}
function openEdit(t) {
    editing.value = t
    form.date = t.date.slice(0, 10)
    form.direction = t.direction
    form.fin_category_id = t.fin_category_id
    form.cash_account_id = t.cash_account_id
    form.amount = Number(t.amount)
    form.description = t.description ?? ''
    form.clearErrors()
    showNewCat.value = false
    dialogOpen.value = true
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { dialogOpen.value = false } }
    if (editing.value) form.patch(route('finance.transactions.update', editing.value.id), opts)
    else form.post(route('finance.transactions.store'), opts)
}
async function remove(t) {
    if (await confirm({ title: 'Hapus transaksi ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('finance.transactions.destroy', t.id), { preserveScroll: true })
    }
}

// ── Kelola kategori & akun ──
const manageOpen = ref(false)
const catForm = useForm({ name: '', type: 'expense' })
function addCategory() {
    catForm.post(route('finance.categories.store'), { preserveScroll: true, onSuccess: () => catForm.reset() })
}
async function deleteCategory(c) {
    if (await confirm({ title: `Hapus kategori "${c.name}"?`, confirmLabel: 'Hapus' })) {
        router.delete(route('finance.categories.destroy', c.id), { preserveScroll: true })
    }
}
const accForm = useForm({ name: '', type: 'bank', opening_balance: '' })
function addAccount() {
    accForm.post(route('finance.cash-accounts.store'), { preserveScroll: true, onSuccess: () => accForm.reset() })
}
async function deleteAccount(a) {
    if (await confirm({ title: `Hapus akun "${a.name}"?`, confirmLabel: 'Hapus' })) {
        router.delete(route('finance.cash-accounts.destroy', a.id), { preserveScroll: true })
    }
}

const net = computed(() => props.summary.income - props.summary.expense)
const incomeCats = computed(() => props.categories.filter(c => c.type === 'income'))
const expenseCats = computed(() => props.categories.filter(c => c.type === 'expense'))
</script>

<template>
    <Head title="Transaksi Keuangan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Transaksi Keuangan</h1>
                <input type="month" :value="month" @change="changeMonth" class="text-sm border rounded-md px-2 py-1" />
            </div>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <!-- Ringkasan bulan -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Pemasukan</p>
                    <p class="text-base font-bold text-green-600 mt-1">{{ fmtRp(summary.income) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Pengeluaran</p>
                    <p class="text-base font-bold text-red-600 mt-1">{{ fmtRp(summary.expense) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Selisih</p>
                    <p class="text-base font-bold mt-1" :class="net >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtRp(net) }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <button class="text-sm text-gray-500 hover:text-gray-700" @click="manageOpen = !manageOpen">
                    ⚙ Kelola Kategori & Akun Kas
                </button>
                <Button size="sm" @click="openAdd">+ Catat Transaksi</Button>
            </div>

            <!-- Kelola kategori & akun -->
            <div v-if="manageOpen" class="bg-white rounded-xl border shadow-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Kategori</h3>
                    <div class="space-y-1 mb-3">
                        <div v-for="c in categories" :key="c.id" class="flex items-center justify-between text-sm py-1">
                            <span>
                                <span class="text-xs px-1.5 py-0.5 rounded mr-1" :class="c.type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ c.type === 'income' ? 'Masuk' : 'Keluar' }}</span>
                                {{ c.name }}
                                <span v-if="c.is_system" class="text-[10px] text-gray-400">(bawaan)</span>
                            </span>
                            <button v-if="!c.is_system" class="text-xs text-red-500 hover:underline" @click="deleteCategory(c)">hapus</button>
                        </div>
                    </div>
                    <form @submit.prevent="addCategory" class="flex gap-2">
                        <select v-model="catForm.type" class="text-sm border rounded-md px-2">
                            <option value="income">Masuk</option>
                            <option value="expense">Keluar</option>
                        </select>
                        <Input v-model="catForm.name" placeholder="Nama kategori" class="h-8 text-sm" />
                        <Button type="submit" size="sm" variant="outline" :disabled="catForm.processing">+</Button>
                    </form>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Akun Kas</h3>
                    <div class="space-y-1 mb-3">
                        <div v-for="a in cashAccounts" :key="a.id" class="flex items-center justify-between text-sm py-1">
                            <span>
                                <span class="text-xs px-1.5 py-0.5 rounded mr-1" :class="a.type === 'cash' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">{{ a.type === 'cash' ? 'Kas' : 'Bank' }}</span>
                                {{ a.name }}
                            </span>
                            <button class="text-xs text-red-500 hover:underline" @click="deleteAccount(a)">hapus</button>
                        </div>
                    </div>
                    <form @submit.prevent="addAccount" class="flex gap-2">
                        <select v-model="accForm.type" class="text-sm border rounded-md px-2">
                            <option value="cash">Kas</option>
                            <option value="bank">Bank</option>
                        </select>
                        <Input v-model="accForm.name" placeholder="Nama akun" class="h-8 text-sm" />
                        <Button type="submit" size="sm" variant="outline" :disabled="accForm.processing">+</Button>
                    </form>
                </div>
            </div>

            <!-- Daftar transaksi -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div v-if="!transactions.length" class="px-5 py-10 text-center text-sm text-gray-400">
                    Belum ada transaksi pada bulan ini.
                </div>
                <div v-else class="divide-y">
                    <div v-for="t in transactions" :key="t.id" class="px-5 py-3 flex items-center gap-3">
                        <div class="w-1.5 h-10 rounded" :class="t.direction === 'in' ? 'bg-green-500' : 'bg-red-500'"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium">{{ t.category?.name }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">{{ t.cash_account?.name }}</span>
                                <span v-if="t.source !== 'manual'" class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600">auto: {{ t.source === 'invoice' ? 'AR' : 'AP' }}</span>
                            </div>
                            <div class="text-xs text-gray-400">{{ fmtDate(t.date) }}<span v-if="t.description"> · {{ t.description }}</span></div>
                        </div>
                        <span class="font-mono font-semibold shrink-0" :class="t.direction === 'in' ? 'text-green-600' : 'text-red-600'">
                            {{ t.direction === 'in' ? '+' : '−' }}{{ fmtRp(t.amount) }}
                        </span>
                        <div class="flex gap-1 shrink-0">
                            <template v-if="t.source === 'manual'">
                                <button class="text-xs px-2 py-1 rounded border hover:bg-muted" @click="openEdit(t)">Edit</button>
                                <button class="text-xs px-2 py-1 rounded border border-red-200 text-red-600 hover:bg-red-50" @click="remove(t)">Hapus</button>
                            </template>
                            <span v-else class="text-[10px] text-gray-400 self-center px-2">dari Keuangan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog transaksi -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Transaksi' : 'Catat Transaksi' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-3 mt-2">
                    <div class="flex rounded-md border p-0.5">
                        <button type="button" class="flex-1 py-1 rounded text-sm transition-colors"
                            :class="form.direction === 'in' ? 'bg-green-600 text-white' : 'text-gray-500'"
                            @click="setDirection('in')">Pemasukan</button>
                        <button type="button" class="flex-1 py-1 rounded text-sm transition-colors"
                            :class="form.direction === 'out' ? 'bg-red-600 text-white' : 'text-gray-500'"
                            @click="setDirection('out')">Pengeluaran</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label>Tanggal</Label>
                            <Input type="date" v-model="form.date" required />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Nominal</Label>
                            <Input type="number" v-model.number="form.amount" min="1" step="any" required />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label>Kategori</Label>
                            <button type="button" class="text-xs text-primary hover:underline" @click="toggleNewCat">
                                {{ showNewCat ? '← Pilih yang ada' : '+ Kategori baru' }}
                            </button>
                        </div>
                        <select v-if="!showNewCat" v-model="form.fin_category_id" class="w-full border rounded-md px-3 py-2 text-sm" required>
                            <option v-for="c in formCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <div v-else class="flex gap-2">
                            <Input v-model="newCatName" :placeholder="`Kategori ${form.direction === 'in' ? 'pemasukan' : 'pengeluaran'} baru`" @keyup.enter.prevent="createCategory" autofocus />
                            <Button type="button" size="sm" variant="outline" :disabled="creatingCat || !newCatName.trim()" @click="createCategory">
                                {{ creatingCat ? '…' : 'Tambah' }}
                            </Button>
                        </div>
                        <p v-if="form.errors.fin_category_id" class="text-xs text-red-600">{{ form.errors.fin_category_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Akun Kas</Label>
                        <select v-model="form.cash_account_id" class="w-full border rounded-md px-3 py-2 text-sm" required>
                            <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Keterangan <span class="text-xs text-muted-foreground font-normal">(opsional)</span></Label>
                        <Input v-model="form.description" placeholder="Catatan..." />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Simpan' : 'Catat' }}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
