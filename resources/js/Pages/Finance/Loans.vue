<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    loans:        Array,
    types:        Object,
    modalDisetor: Number,
    totals:       Object,
})

// ── Modal Disetor ────────────────────────────────────────────────
const editingModal  = ref(false)
const modalForm     = useForm({ modal_disetor: props.modalDisetor })
function saveModal() {
    modalForm.patch(route('finance.settings.update'), {
        onSuccess: () => { editingModal.value = false },
    })
}

// ── Add form ─────────────────────────────────────────────────────
const showAddForm = ref(false)
const addForm = useForm({
    name:                '',
    lender:              '',
    loan_type:           'leasing',
    original_amount:     '',
    start_date:          '',
    tenor_months:        '',
    monthly_installment: '',
    outstanding_balance: '',
    notes:               '',
})
function submitAdd() {
    addForm.post(route('loans.store'), {
        onSuccess: () => { addForm.reset(); showAddForm.value = false },
    })
}

// ── Inline edit ──────────────────────────────────────────────────
const editingId   = ref(null)
const editForm    = useForm({
    name:                '',
    lender:              '',
    loan_type:           '',
    original_amount:     '',
    start_date:          '',
    tenor_months:        '',
    monthly_installment: '',
    outstanding_balance: '',
    notes:               '',
    is_active:           true,
})
function startEdit(loan) {
    editingId.value = loan.id
    Object.assign(editForm, {
        name:                loan.name,
        lender:              loan.lender ?? '',
        loan_type:           loan.loan_type,
        original_amount:     loan.original_amount,
        start_date:          loan.start_date,
        tenor_months:        loan.tenor_months,
        monthly_installment: loan.monthly_installment,
        outstanding_balance: loan.outstanding_balance,
        notes:               loan.notes ?? '',
        is_active:           loan.is_active,
    })
}
function cancelEdit() { editingId.value = null }
function submitEdit(id) {
    editForm.patch(route('loans.update', id), {
        onSuccess: () => { editingId.value = null },
    })
}
function deleteLoan(id) {
    if (!confirm('Hapus data pinjaman ini?')) return
    router.delete(route('loans.destroy', id))
}

// ── Display helpers ──────────────────────────────────────────────
const typeLabel = (t) => props.types[t] ?? t

const TYPE_COLORS = {
    bank_loan: 'bg-blue-100 text-blue-800',
    leasing:   'bg-indigo-100 text-indigo-800',
    other:     'bg-gray-100 text-gray-700',
}
const typeColor = (t) => TYPE_COLORS[t] ?? TYPE_COLORS.other

const paidPct = (loan) => {
    if (!loan.original_amount) return 0
    const paid = loan.original_amount - loan.outstanding_balance
    return Math.round(Math.min(Math.max(paid / loan.original_amount * 100, 0), 100))
}

const groupedLoans = computed(() => {
    const g = {}
    for (const l of props.loans) {
        if (!g[l.loan_type]) g[l.loan_type] = []
        g[l.loan_type].push(l)
    }
    return g
})
</script>

<template>
    <Head title="Hutang & Pinjaman" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Hutang &amp; Pinjaman</h1>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            <!-- Modal Disetor card -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-gray-700">Modal Disetor</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Digunakan sebagai ekuitas awal pada Neraca</p>
                    </div>
                    <button v-if="!editingModal" @click="editingModal = true"
                        class="text-xs px-3 py-1 border rounded-md hover:bg-gray-50">Edit</button>
                </div>
                <div v-if="!editingModal" class="text-2xl font-bold font-mono text-blue-800">
                    {{ fmtRp(modalDisetor) }}
                </div>
                <div v-else class="flex items-center gap-3">
                    <input v-model="modalForm.modal_disetor" type="number" min="0"
                        class="border rounded-md px-3 py-1.5 text-sm w-56 font-mono"
                        placeholder="0" />
                    <button @click="saveModal"
                        :disabled="modalForm.processing"
                        class="text-sm px-4 py-1.5 bg-blue-800 text-white rounded-md hover:bg-blue-900 disabled:opacity-50">
                        Simpan
                    </button>
                    <button @click="editingModal = false" class="text-sm text-gray-500 hover:text-gray-700">Batal</button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Pinjaman Awal</p>
                    <p class="text-lg font-bold font-mono text-gray-800">{{ fmtRp(totals.original) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Saldo Outstanding</p>
                    <p class="text-lg font-bold font-mono text-red-700">{{ fmtRp(totals.outstanding) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Pinjaman aktif saat ini</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Cicilan / Bulan</p>
                    <p class="text-lg font-bold font-mono text-amber-700">{{ fmtRp(totals.monthly) }}</p>
                </div>
            </div>

            <!-- Add button -->
            <div class="flex justify-end">
                <button @click="showAddForm = !showAddForm"
                    class="text-sm px-4 py-2 rounded-lg border bg-white hover:bg-gray-50 font-medium">
                    {{ showAddForm ? '✕ Tutup' : '+ Tambah Pinjaman' }}
                </button>
            </div>

            <!-- Add form -->
            <div v-if="showAddForm" class="bg-white rounded-xl border shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Tambah Pinjaman Baru</h3>
                <form @submit.prevent="submitAdd" class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Nama Pinjaman / Kendaraan</label>
                        <input v-model="addForm.name" type="text" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm"
                            placeholder="cth: Toyota Innova Zenix — BCA Finance" />
                        <p v-if="addForm.errors.name" class="text-xs text-red-600 mt-1">{{ addForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Kreditur / Lembaga</label>
                        <input v-model="addForm.lender" type="text"
                            class="w-full border rounded-md px-3 py-1.5 text-sm"
                            placeholder="cth: BCA Finance, Bank Mandiri" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Jenis Pinjaman</label>
                        <select v-model="addForm.loan_type" class="w-full border rounded-md px-3 py-1.5 text-sm">
                            <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Jumlah Pinjaman Awal (Rp)</label>
                        <input v-model="addForm.original_amount" type="number" min="0" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm font-mono" />
                        <p v-if="addForm.errors.original_amount" class="text-xs text-red-600 mt-1">{{ addForm.errors.original_amount }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Tanggal Mulai</label>
                        <input v-model="addForm.start_date" type="date" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Tenor (bulan)</label>
                        <input v-model="addForm.tenor_months" type="number" min="1" max="600" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm"
                            placeholder="60" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Cicilan / Bulan (Rp)</label>
                        <input v-model="addForm.monthly_installment" type="number" min="0" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm font-mono" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Saldo Outstanding Saat Ini (Rp)</label>
                        <input v-model="addForm.outstanding_balance" type="number" min="0" required
                            class="w-full border rounded-md px-3 py-1.5 text-sm font-mono"
                            placeholder="Masukkan sisa pokok pinjaman sesuai surat tagihan" />
                        <p class="text-xs text-gray-400 mt-1">Perbarui angka ini sesuai tagihan dari kreditur.</p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Catatan</label>
                        <input v-model="addForm.notes" type="text"
                            class="w-full border rounded-md px-3 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" :disabled="addForm.processing"
                            class="px-5 py-2 bg-blue-800 text-white text-sm rounded-lg disabled:opacity-50 hover:bg-blue-900">
                            Simpan
                        </button>
                        <button type="button" @click="showAddForm = false"
                            class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Batal</button>
                    </div>
                </form>
            </div>

            <!-- Loans table per group -->
            <template v-if="loans.length">
                <div v-for="(group, type) in groupedLoans" :key="type"
                    class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b bg-gray-50/50">
                        <span class="text-sm font-bold text-gray-700">{{ types[type] ?? type }}</span>
                    </div>

                    <div class="divide-y">
                        <template v-for="loan in group" :key="loan.id">
                            <!-- Normal row -->
                            <div v-if="editingId !== loan.id" class="px-5 py-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-semibold text-gray-800">{{ loan.name }}</span>
                                            <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                                                :class="typeColor(loan.loan_type)">
                                                {{ typeLabel(loan.loan_type) }}
                                            </span>
                                            <span v-if="!loan.is_active"
                                                class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500">
                                                Non-aktif
                                            </span>
                                        </div>
                                        <p v-if="loan.lender" class="text-xs text-gray-500 mt-0.5">{{ loan.lender }}</p>
                                    </div>
                                    <div class="flex gap-2 ml-4 shrink-0">
                                        <button @click="startEdit(loan)"
                                            class="text-xs px-2.5 py-1 border rounded hover:bg-gray-50">Edit</button>
                                        <button @click="deleteLoan(loan.id)"
                                            class="text-xs px-2.5 py-1 border border-red-200 text-red-600 rounded hover:bg-red-50">
                                            Hapus
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <p class="text-gray-400">Pokok Awal</p>
                                        <p class="font-mono font-medium">{{ fmtRp(loan.original_amount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Cicilan/Bln</p>
                                        <p class="font-mono font-medium">{{ fmtRp(loan.monthly_installment) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Tenor</p>
                                        <p class="font-medium">{{ loan.tenor_months }} bln · mulai {{ loan.start_date }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Saldo Outstanding</p>
                                        <p class="font-mono font-bold text-red-700">{{ fmtRp(loan.outstanding_balance) }}</p>
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-2.5">
                                    <div class="flex justify-between text-xs text-gray-400 mb-1">
                                        <span>Terlunasi {{ paidPct(loan) }}%</span>
                                        <span>Sisa {{ fmtRp(loan.outstanding_balance) }}</span>
                                    </div>
                                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full transition-all"
                                            :style="{ width: paidPct(loan) + '%' }"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit row -->
                            <div v-else class="px-5 py-4 bg-blue-50/30">
                                <p class="text-xs font-semibold text-blue-700 mb-3">Edit: {{ loan.name }}</p>
                                <form @submit.prevent="submitEdit(loan.id)" class="grid grid-cols-2 gap-3">
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-600 mb-1">Nama</label>
                                        <input v-model="editForm.name" type="text" required
                                            class="w-full border rounded px-2.5 py-1.5 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Kreditur</label>
                                        <input v-model="editForm.lender" type="text"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Jenis</label>
                                        <select v-model="editForm.loan_type"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm">
                                            <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Pokok Awal (Rp)</label>
                                        <input v-model="editForm.original_amount" type="number" min="0"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Tanggal Mulai</label>
                                        <input v-model="editForm.start_date" type="date"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Tenor (bulan)</label>
                                        <input v-model="editForm.tenor_months" type="number" min="1"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Cicilan/Bln (Rp)</label>
                                        <input v-model="editForm.monthly_installment" type="number" min="0"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm font-mono" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-600 mb-1">
                                            Saldo Outstanding Saat Ini (Rp)
                                            <span class="text-gray-400 font-normal">— perbarui sesuai tagihan kreditur</span>
                                        </label>
                                        <input v-model="editForm.outstanding_balance" type="number" min="0"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">Catatan</label>
                                        <input v-model="editForm.notes" type="text"
                                            class="w-full border rounded px-2.5 py-1.5 text-sm" />
                                    </div>
                                    <div class="flex items-center gap-2 pt-4">
                                        <input v-model="editForm.is_active" type="checkbox" id="ea" class="rounded" />
                                        <label for="ea" class="text-xs text-gray-600">Aktif</label>
                                    </div>
                                    <div class="col-span-2 flex gap-2 pt-1">
                                        <button type="submit" :disabled="editForm.processing"
                                            class="px-4 py-1.5 bg-blue-800 text-white text-xs rounded hover:bg-blue-900 disabled:opacity-50">
                                            Simpan
                                        </button>
                                        <button type="button" @click="cancelEdit"
                                            class="px-3 py-1.5 text-xs border rounded hover:bg-gray-50">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>

                    <!-- Group footer -->
                    <div class="px-5 py-3 border-t bg-gray-50/50 flex justify-between text-sm font-semibold">
                        <span class="text-gray-600">Total {{ types[type] ?? type }}</span>
                        <span class="font-mono text-red-700">
                            {{ fmtRp(group.reduce((s, l) => s + (l.is_active ? l.outstanding_balance : 0), 0)) }}
                        </span>
                    </div>
                </div>
            </template>

            <div v-else class="bg-white rounded-xl border shadow-sm p-10 text-center text-sm text-gray-400">
                Belum ada data pinjaman. Tambahkan hutang bank atau leasing kendaraan.
            </div>

            <!-- Info box -->
            <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 text-xs text-blue-700">
                <p class="font-semibold mb-1">Cara penggunaan</p>
                <ul class="space-y-1 list-disc list-inside text-blue-600">
                    <li>Masukkan setiap pinjaman bank atau leasing kendaraan/aset.</li>
                    <li><strong>Saldo Outstanding</strong> adalah sisa pokok pinjaman — perbarui secara manual sesuai tagihan dari kreditur.</li>
                    <li>Angka ini akan otomatis masuk ke sisi <strong>Kewajiban</strong> pada Neraca.</li>
                    <li>Non-aktifkan pinjaman yang sudah lunas agar tidak muncul di Neraca.</li>
                </ul>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
