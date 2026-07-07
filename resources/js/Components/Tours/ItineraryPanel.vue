<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/Components/ui/dialog'
import { confirm } from '@/lib/confirm'

const props = defineProps({ tour: Object })

const itineraryDays = ref(props.tour.itinerary_days?.map(d => ({ ...d })) ?? [])

function addDay() {
    const next = (itineraryDays.value.at(-1)?.day_number ?? 0) + 1
    itineraryDays.value.push({ day_number: next, title: '', title_ind: '', description: '', description_ind: '' })
}

// Toggle input versi Indonesia per hari — terbuka otomatis bila sudah terisi
const indOpen = ref({})
itineraryDays.value.forEach((d, i) => {
    if (d.title_ind || d.description_ind) indOpen.value[i] = true
})

function removeDay(i) {
    itineraryDays.value.splice(i, 1)
    itineraryDays.value.forEach((d, idx) => { d.day_number = idx + 1 })
}

function saveItinerary() {
    router.post(route('tours.itinerary.days', props.tour.id), { days: itineraryDays.value }, {
        preserveScroll: true, only: ['tour'],
    })
}

// ── Hourly Itinerary ────────────────────────────────────────────────────────
const itineraryHours    = ref(props.tour.itinerary_hours?.map(h => ({ ...h })) ?? [])
const expandedHourDays  = reactive(new Set())
const hourForm = useForm({ day_number: 1, start_time: '', end_time: '', activity: '', notes: '' })
const editingHourId         = ref(null)
const expandedHourDaysTick  = ref(0)

watch(() => props.tour.itinerary_hours, (hours) => {
    itineraryHours.value = hours?.map(h => ({ ...h })) ?? []
})

const groupedHours = computed(() => {
    const grouped = {}
    itineraryHours.value.forEach(h => {
        if (!grouped[h.day_number]) grouped[h.day_number] = []
        grouped[h.day_number].push(h)
    })
    return grouped
})

function toggleHourDay(day) {
    if (expandedHourDays.has(day)) expandedHourDays.delete(day)
    else expandedHourDays.add(day)
    expandedHourDaysTick.value++
}

function isHourDayExpanded(day) {
    void expandedHourDaysTick.value
    return expandedHourDays.has(day)
}

function startAddHour(day) {
    editingHourId.value = null
    hourForm.day_number = day
    hourForm.start_time = ''
    hourForm.end_time   = ''
    hourForm.activity   = ''
    hourForm.notes      = ''
}

function startEditHour(hour) {
    editingHourId.value = hour.id
    hourForm.day_number = hour.day_number
    hourForm.start_time = hour.start_time
    hourForm.end_time   = hour.end_time
    hourForm.activity   = hour.activity
    hourForm.notes      = hour.notes
}

function saveHour() {
    if (!hourForm.start_time || !hourForm.activity) return
    const data = {
        day_number: hourForm.day_number,
        start_time: hourForm.start_time,
        end_time:   hourForm.end_time,
        activity:   hourForm.activity,
        notes:      hourForm.notes,
    }
    const opts = {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => { editingHourId.value = null; hourForm.reset() },
    }
    if (editingHourId.value) {
        router.patch(route('tours.itinerary.hours.update', [props.tour.id, editingHourId.value]), data, opts)
    } else {
        router.post(route('tours.itinerary.hours.store', props.tour.id), data, opts)
    }
}

async function deleteHour(hourId) {
    if (await confirm({ title: 'Hapus aktivitas ini?', confirmLabel: 'Hapus' })) {
        router.delete(route('tours.itinerary.hours.delete', [props.tour.id, hourId]), {
            preserveScroll: true, only: ['tour'],
        })
    }
}

function cancelHourForm() {
    editingHourId.value = null
    hourForm.reset()
}

// ── Salin itinerary ke clipboard (teks yang bisa ditempel kembali) ───────────
const copied = ref(false)

async function copyItinerary() {
    const lines = []
    itineraryDays.value.forEach(day => {
        lines.push(`HARI ${day.day_number}${day.title ? ': ' + day.title : ''}`)
        if (day.description) lines.push(day.description.trim())
        ;(groupedHours.value[day.day_number] ?? []).forEach(h => {
            let l = `- ${h.start_time}${h.end_time ? '–' + h.end_time : ''} | ${h.activity}`
            if (h.notes) l += ` | ${h.notes}`
            lines.push(l)
        })
        lines.push('')
    })
    const text = lines.join('\n').trim()
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        // Fallback untuk browser tanpa izin clipboard API
        const ta = document.createElement('textarea')
        ta.value = text
        document.body.appendChild(ta)
        ta.select()
        document.execCommand('copy')
        document.body.removeChild(ta)
    }
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}

// ── Tempel itinerary dari clipboard (kebalikan tombol Salin) ─────────────────
const pasteDialogOpen = ref(false)
const pasteText       = ref('')

const DAY_RE  = /^(?:hari|day)\s*(?:ke[-\s]*)?(\d+)\s*[:.\-]?\s*(.*)$/i
const HOUR_RE = /^[-•*]?\s*(\d{1,2})[:.](\d{2})\s*(?:[–—-]\s*(\d{1,2})[:.](\d{2}))?\s*(?:\|(.*))?$/

const parsedPaste = computed(() => {
    const days = []
    const hours = []
    let current = null

    const ensureDay = () => {
        if (!current) {
            current = { day_number: 1, title: '', description: '' }
            days.push(current)
        }
        return current
    }

    for (const raw of pasteText.value.split(/\r?\n/)) {
        const line = raw.trim()
        if (!line) continue

        const dm = line.match(DAY_RE)
        if (dm) {
            current = { day_number: Number(dm[1]), title: dm[2].trim(), description: '' }
            days.push(current)
            continue
        }

        const hm = line.match(HOUR_RE)
        if (hm && hm[5] !== undefined) {
            const [activity, ...noteParts] = hm[5].split('|').map(s => s.trim())
            if (activity) {
                hours.push({
                    day_number: ensureDay().day_number,
                    start_time: `${hm[1].padStart(2, '0')}:${hm[2]}`,
                    end_time:   hm[3] ? `${hm[3].padStart(2, '0')}:${hm[4]}` : null,
                    activity,
                    notes: noteParts.join(' | ') || null,
                })
                continue
            }
        }

        const d = ensureDay()
        d.description = d.description ? d.description + '\n' + line : line
    }

    return { days, hours }
})

function openPasteDialog() {
    pasteText.value = ''
    pasteDialogOpen.value = true
}

async function submitPaste() {
    if (!parsedPaste.value.days.length) return
    if (itineraryDays.value.length
        && !(await confirm({ title: 'Timpa itinerary yang ada?', description: 'Semua hari & aktivitas jam saat ini akan diganti dengan hasil tempel.', confirmLabel: 'Timpa' }))) {
        return
    }
    router.post(route('tours.itinerary.import', props.tour.id), parsedPaste.value, {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => {
            pasteDialogOpen.value = false
            itineraryDays.value = props.tour.itinerary_days?.map(d => ({ ...d })) ?? []
        },
    })
}

watch(() => props.tour.itinerary_days, (days) => {
    itineraryDays.value = days?.map(d => ({ ...d })) ?? []
})

// ── PDF Upload ────────────────────────────────────────────────────────────────
const pdfForm    = useForm({ pdf: null })
const pdfFileRef = ref(null)

function onPdfSelect(e) {
    pdfForm.pdf = e.target.files[0] ?? null
}

function uploadPdf() {
    pdfForm.post(route('tours.itinerary.pdf.upload', props.tour.id), {
        preserveScroll: true,
        only: ['tour'],
        onSuccess: () => { pdfForm.reset(); if (pdfFileRef.value) pdfFileRef.value.value = '' },
    })
}

async function deletePdf() {
    if (!(await confirm({ title: 'Hapus PDF itinerary ini?', confirmLabel: 'Hapus' }))) return
    router.delete(route('tours.itinerary.pdf.delete', props.tour.id), {
        preserveScroll: true, only: ['tour'],
    })
}

// ── Auto-resize directive ────────────────────────────────────────────────────
const vAutoResize = {
    mounted(el) {
        const resize = () => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px' }
        el.style.overflow = 'hidden'
        resize()
        el.addEventListener('input', resize)
    },
}

onMounted(() => nextTick(() => {
    document.querySelectorAll('.itinerary-desc').forEach(el => {
        el.style.overflow = 'hidden'
        el.style.height = 'auto'
        el.style.height = el.scrollHeight + 'px'
    })
}))
</script>

<template>
    <div class="rounded-lg border bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between gap-2 px-5 py-4 border-b">
            <h3 class="font-semibold">Itinerary</h3>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" @click="openPasteDialog">📥 Tempel</Button>
                <Button v-if="itineraryDays.length" type="button" size="sm" variant="outline" @click="copyItinerary">
                    {{ copied ? '✓ Tersalin' : '📋 Salin' }}
                </Button>
                <Button type="button" size="sm" variant="outline" @click="addDay">+ Tambah Hari</Button>
            </div>
        </div>

        <div v-if="!itineraryDays.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
            Belum ada itinerary. Klik "Tambah Hari" untuk memulai.
        </div>

        <div v-else class="divide-y">
            <div v-for="(day, i) in itineraryDays" :key="i" class="px-5 py-4 space-y-2">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary text-xs font-bold">
                        {{ day.day_number }}
                    </span>
                    <Input v-model="day.title" placeholder="Judul hari ini (mis. Arrival & City Tour)" class="flex-1" />
                    <Button type="button" size="sm" variant="ghost" class="text-destructive hover:text-destructive shrink-0" @click="removeDay(i)">✕</Button>
                </div>
                <textarea
                    v-model="day.description"
                    v-auto-resize
                    placeholder="Aktivitas, jadwal, tempat yang dikunjungi..."
                    class="itinerary-desc ml-11 w-[calc(100%-2.75rem)] min-h-[80px] rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                />
                <div class="ml-11">
                    <button type="button" @click="indOpen[i] = !indOpen[i]"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground">
                        {{ indOpen[i] ? '▾' : '▸' }} 🇮🇩 Versi Indonesia untuk tim lapangan
                        <span v-if="day.title_ind || day.description_ind" class="text-green-600">· terisi</span>
                        <span v-else class="text-muted-foreground/60">· opsional</span>
                    </button>
                    <div v-if="indOpen[i]" class="mt-2 space-y-2 rounded-md border border-dashed bg-muted/20 p-3">
                        <p class="text-[11px] text-muted-foreground">
                            Tampil di MyJobs & manifest untuk guide/sopir/tour leader. Quotation customer tetap memakai teks di atas.
                        </p>
                        <Input v-model="day.title_ind" placeholder="Judul hari (bahasa Indonesia)" class="bg-white" />
                        <textarea
                            v-model="day.description_ind"
                            v-auto-resize
                            placeholder="Deskripsi bahasa Indonesia untuk tim lapangan..."
                            class="w-full min-h-[60px] rounded-md border border-input bg-white px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="itineraryDays.length" class="px-5 py-3 bg-muted/20 border-t flex justify-end">
            <Button type="button" size="sm" @click="saveItinerary">Simpan Itinerary</Button>
        </div>

        <!-- Hourly Itinerary -->
        <div v-if="itineraryDays.length" class="px-5 py-4 border-t space-y-3">
            <p class="text-xs font-semibold text-muted-foreground">ITINERARY JAM-KE-JAM (Opsional)</p>
            <div class="space-y-2">
                <div v-for="day in itineraryDays" :key="day.day_number" class="border rounded-md overflow-hidden">
                    <button type="button" @click="toggleHourDay(day.day_number)"
                        class="w-full flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-muted/50 bg-muted/20">
                        <span class="flex items-center gap-2 flex-1 text-left">
                            <span class="text-xs font-semibold">Hari {{ day.day_number }}:</span>
                            <span class="text-sm">{{ day.title || '(Belum ada judul)' }}</span>
                        </span>
                        <span class="text-xs text-muted-foreground">{{ (groupedHours[day.day_number]?.length || 0) }} aktivitas</span>
                        <span class="text-muted-foreground text-lg shrink-0">{{ isHourDayExpanded(day.day_number) ? '−' : '+' }}</span>
                    </button>

                    <div v-if="isHourDayExpanded(day.day_number)" class="bg-white space-y-2 p-3 border-t">
                        <div v-if="!editingHourId || hourForm.day_number === day.day_number"
                            class="space-y-2 p-3 rounded-md border bg-muted/10">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-1">
                                    <Label class="text-xs">Mulai</Label>
                                    <Input type="time" v-model="hourForm.start_time" class="h-8 text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Selesai</Label>
                                    <Input type="time" v-model="hourForm.end_time" class="h-8 text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Aktivitas</Label>
                                    <Input v-model="hourForm.activity" placeholder="Mis. Breakfast" class="h-8 text-sm" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Catatan</Label>
                                <Input v-model="hourForm.notes" placeholder="Lokasi, detail, dll" class="h-8 text-sm" />
                            </div>
                            <div class="flex gap-2">
                                <Button type="button" size="sm" @click="saveHour" class="h-7 text-xs flex-1">
                                    {{ editingHourId ? '✓ Update' : '+ Tambah' }}
                                </Button>
                                <Button v-if="editingHourId" type="button" size="sm" variant="outline" @click="cancelHourForm" class="h-7 text-xs">
                                    Batal
                                </Button>
                            </div>
                        </div>

                        <div v-if="groupedHours[day.day_number]?.length" class="space-y-1.5">
                            <div v-for="hour in groupedHours[day.day_number]" :key="hour.id"
                                class="flex items-start justify-between gap-2 p-2 rounded text-xs bg-white border">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium">
                                        {{ hour.start_time }}<span v-if="hour.end_time">–{{ hour.end_time }}</span>
                                    </div>
                                    <div class="text-muted-foreground">{{ hour.activity }}</div>
                                    <div v-if="hour.notes" class="text-muted-foreground text-xs">{{ hour.notes }}</div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" @click="startEditHour(hour)" class="text-blue-600 hover:text-blue-700">✎</button>
                                    <button type="button" @click="deleteHour(hour.id)" class="text-destructive hover:text-destructive/80">✕</button>
                                </div>
                            </div>
                        </div>

                        <button v-if="!editingHourId || hourForm.day_number !== day.day_number"
                            type="button" @click="startAddHour(day.day_number)"
                            class="text-xs text-primary hover:text-primary/80 font-medium pt-1">
                            + Tambah Aktivitas
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PDF Upload -->
        <div class="px-5 py-4 border-t space-y-3">
            <p class="text-xs font-semibold text-muted-foreground">PDF ITINERARY LENGKAP</p>
            <div v-if="tour.itinerary_pdf_url" class="flex items-center gap-3 p-3 rounded-md bg-muted/30 border">
                <svg class="h-8 w-8 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8.5 17.5h7v-1h-7v1zm0-2.5h7v-1h-7v1zm0-2.5h4v-1h-4v1z"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ tour.code }}-itinerary.pdf</p>
                    <p class="text-xs text-muted-foreground">PDF tersedia</p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <a :href="route('tours.itinerary.pdf.download', tour.id)" target="_blank">
                        <Button type="button" size="sm" variant="outline">⬇ Unduh</Button>
                    </a>
                    <Button type="button" size="sm" variant="destructive" @click="deletePdf">Hapus</Button>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <input ref="pdfFileRef" type="file" accept=".pdf"
                    class="flex-1 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:cursor-pointer cursor-pointer"
                    @change="onPdfSelect" />
                <Button type="button" size="sm" :disabled="!pdfForm.pdf || pdfForm.processing" @click="uploadPdf">
                    Upload PDF
                </Button>
            </div>
            <p v-if="pdfForm.errors.pdf" class="text-xs text-destructive">{{ pdfForm.errors.pdf }}</p>
            <p class="text-xs text-muted-foreground">Maks. 20 MB. PDF ini dapat diunduh oleh admin & sales.</p>
        </div>

        <!-- ── Dialog tempel itinerary ── -->
        <Dialog v-model:open="pasteDialogOpen">
            <DialogContent class="max-w-2xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Tempel Itinerary</DialogTitle>
                </DialogHeader>
                <p class="text-xs text-muted-foreground">
                    Tempel (Ctrl+V / Cmd+V) hasil tombol "Salin" dari tour lain, atau ketik manual.
                    Baris <b>HARI 1: Judul</b> memulai hari baru; baris <b>- 08:00–10:00 | Aktivitas | Catatan</b> menjadi jadwal jam; baris lain menjadi deskripsi hari.
                </p>
                <textarea v-model="pasteText" rows="10" autofocus
                    placeholder="HARI 1: Arrival & City Tour&#10;Penjemputan di bandara, check-in hotel...&#10;- 08:00–10:00 | Breakfast | Hotel resto&#10;&#10;HARI 2: Bunaken Trip&#10;..."
                    class="w-full border rounded px-2 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"></textarea>

                <div v-if="parsedPaste.days.length" class="overflow-auto max-h-60 rounded-md border divide-y">
                    <div v-for="d in parsedPaste.days" :key="d.day_number" class="px-3 py-2 text-xs">
                        <p class="font-semibold">Hari {{ d.day_number }}<template v-if="d.title">: {{ d.title }}</template></p>
                        <p v-if="d.description" class="text-muted-foreground whitespace-pre-line mt-0.5">{{ d.description }}</p>
                        <p v-for="(h, i) in parsedPaste.hours.filter(h => h.day_number === d.day_number)" :key="i"
                            class="text-muted-foreground mt-0.5 font-mono">
                            {{ h.start_time }}<template v-if="h.end_time">–{{ h.end_time }}</template> · {{ h.activity }}<template v-if="h.notes"> ({{ h.notes }})</template>
                        </p>
                    </div>
                </div>
                <p v-else-if="pasteText.trim()" class="text-xs text-amber-600">
                    Tidak ada hari yang bisa dibaca — awali dengan baris "HARI 1: ...".
                </p>

                <DialogFooter>
                    <Button variant="outline" @click="pasteDialogOpen = false">Batal</Button>
                    <Button :disabled="!parsedPaste.days.length" @click="submitPaste">
                        Tempel {{ parsedPaste.days.length }} hari<template v-if="parsedPaste.hours.length"> · {{ parsedPaste.hours.length }} aktivitas</template>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
