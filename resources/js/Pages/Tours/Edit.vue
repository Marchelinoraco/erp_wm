<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { typeLabel } from '@/lib/inquiryTypes'
import { STATUS_CONFIG } from '@/lib/tourConstants'
import HeaderPanel      from '@/Components/Tours/HeaderPanel.vue'
import ItemsPanel       from '@/Components/Tours/ItemsPanel.vue'
import InvoicesPanel    from '@/Components/Tours/InvoicesPanel.vue'
import OperasionalPanel from '@/Components/Tours/OperasionalPanel.vue'
import ItineraryPanel   from '@/Components/Tours/ItineraryPanel.vue'
import QuotationPanel   from '@/Components/Tours/QuotationPanel.vue'
import QItemsPanel         from '@/Components/Tours/QItemsPanel.vue'
import MiceTemplatePanel   from '@/Components/Tours/MiceTemplatePanel.vue'
import HistoryPanel        from '@/Components/Tours/HistoryPanel.vue'
import CostingPanel        from '@/Components/Tours/CostingPanel.vue'

const props = defineProps({
    tour:              Object,
    customers:         Array,
    products:          Array,
    manifestUrl:       String,
    fieldUsers:        Array,
    emailTemplates:    Object,
    quotationDefaults: Object,
    miceTemplates:     { type: Array, default: () => [] },
})

const tourType      = props.tour.type ?? 'tour'
const isTour        = computed(() => tourType === 'tour')
const isMice        = computed(() => tourType === 'mice')
const customerEmail = computed(() => props.tour.customer?.email ?? '')

// ── Email dialog ───────────────────────────────────────────────────────────────
const emailDialogOpen = ref(false)
const emailForm = useForm({ to: '', subject: '', body: '' })

function openEmailDialog() {
    const tpl = props.emailTemplates?.[props.tour.status] ?? {}
    emailForm.to      = customerEmail.value
    emailForm.subject = tpl.subject ?? ''
    emailForm.body    = tpl.body    ?? ''
    emailDialogOpen.value = true
}

function sendEmail() {
    emailForm.post(route('tours.email.send', props.tour.id), {
        preserveScroll: true,
        onSuccess: () => { emailDialogOpen.value = false },
    })
}
</script>

<template>
    <Head :title="`${tour.code} — ${typeLabel(tourType)} Builder`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <Link :href="route('tours.index', { type: tourType })" class="text-muted-foreground hover:text-foreground">
                        ← {{ typeLabel(tourType) }}
                    </Link>
                    <span class="text-muted-foreground">/</span>
                    <span class="font-mono font-semibold">{{ tour.code }}</span>
                    <span :class="[STATUS_CONFIG[tour.status]?.class, 'px-2 py-0.5 rounded-full text-xs font-medium']">
                        {{ STATUS_CONFIG[tour.status]?.label ?? tour.status }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="customerEmail" variant="outline" size="sm" class="gap-1.5" type="button" @click="openEmailDialog">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        Email Customer
                    </Button>
                    <a :href="route('quotation.preview', tour.id)" target="_blank">
                        <Button variant="outline" size="sm">Preview PDF</Button>
                    </a>
                    <a :href="route('quotation.word', tour.id)">
                        <Button variant="outline" size="sm">⬇ Word</Button>
                    </a>
                    <a :href="route('quotation.download', tour.id)">
                        <Button size="sm">⬇ Download Quotation</Button>
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] gap-6 items-start">

                    <div class="space-y-6">
                        <HeaderPanel :tour="tour" :customers="customers" />
                        <ItemsPanel :tour="tour" :products="products" />
                        <InvoicesPanel v-if="tour.status === 'confirmed'" :tour="tour" :products="products" />
                        <OperasionalPanel :tour="tour" :field-users="fieldUsers" :manifest-url="manifestUrl" />
                        <ItineraryPanel v-if="isTour" :tour="tour" />
                        <QuotationPanel :tour="tour" :quotation-defaults="quotationDefaults" :is-tour="isTour" />
                        <QItemsPanel :tour="tour" :products="products" />
                        <MiceTemplatePanel v-if="isMice" :tour="tour" :mice-templates="miceTemplates" />
                        <HistoryPanel :tour="tour" />
                    </div>

                    <CostingPanel :tour="tour" />

                </div>
            </div>
        </div>

        <!-- Email Customer Dialog -->
        <Dialog v-model:open="emailDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        Email ke Customer
                    </DialogTitle>
                </DialogHeader>
                <form @submit.prevent="sendEmail" class="space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <Label>Kepada (To) <span class="text-destructive">*</span></Label>
                        <Input v-model="emailForm.to" type="email" placeholder="email@customer.com" />
                        <p v-if="emailForm.errors.to" class="text-xs text-destructive">{{ emailForm.errors.to }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Subject <span class="text-destructive">*</span></Label>
                        <Input v-model="emailForm.subject" placeholder="Subjek email..." />
                        <p v-if="emailForm.errors.subject" class="text-xs text-destructive">{{ emailForm.errors.subject }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label>Isi Email <span class="text-destructive">*</span></Label>
                            <span class="text-xs text-muted-foreground">Template: <strong>{{ STATUS_CONFIG[tour.status]?.label }}</strong></span>
                        </div>
                        <Textarea v-model="emailForm.body" rows="12" class="font-mono text-sm" />
                        <p v-if="emailForm.errors.body" class="text-xs text-destructive">{{ emailForm.errors.body }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="emailDialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="emailForm.processing" class="gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                            </svg>
                            Kirim Email
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
