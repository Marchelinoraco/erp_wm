import { reactive } from 'vue'

// State tunggal untuk dialog konfirmasi global (lihat ConfirmDialog.vue yang
// di-mount sekali di layout). Pengganti window.confirm() yang tidak ter-style.
export const confirmState = reactive({
    open: false,
    title: 'Yakin?',
    description: '',
    confirmLabel: 'Hapus',
    cancelLabel: 'Batal',
    destructive: true,
    _resolve: null,
})

/**
 * Tampilkan dialog konfirmasi. Mengembalikan Promise<boolean>.
 *
 *   if (await confirm({ title: 'Hapus produk?', confirmLabel: 'Hapus' })) { ... }
 */
export function confirm(options = {}) {
    confirmState.title        = options.title        ?? 'Yakin?'
    confirmState.description  = options.description   ?? ''
    confirmState.confirmLabel = options.confirmLabel  ?? 'Hapus'
    confirmState.cancelLabel  = options.cancelLabel   ?? 'Batal'
    confirmState.destructive  = options.destructive   ?? true
    confirmState.open         = true

    return new Promise((resolve) => {
        confirmState._resolve = resolve
    })
}

// Dipanggil ConfirmDialog.vue saat user menekan tombol / menutup dialog.
export function settleConfirm(result) {
    confirmState.open = false
    if (confirmState._resolve) {
        confirmState._resolve(result)
        confirmState._resolve = null
    }
}
