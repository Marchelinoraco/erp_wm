<script setup>
import { Button } from '@/Components/ui/button'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/Components/ui/dialog'
import { confirmState, settleConfirm } from '@/lib/confirm'

// Ditutup lewat overlay / Escape / tombol X → dianggap batal.
function onOpenChange(val) {
    if (!val) settleConfirm(false)
}
</script>

<template>
    <Dialog :open="confirmState.open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ confirmState.title }}</DialogTitle>
                <DialogDescription v-if="confirmState.description">
                    {{ confirmState.description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="settleConfirm(false)">
                    {{ confirmState.cancelLabel }}
                </Button>
                <Button
                    :variant="confirmState.destructive ? 'destructive' : 'default'"
                    @click="settleConfirm(true)"
                >
                    {{ confirmState.confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
