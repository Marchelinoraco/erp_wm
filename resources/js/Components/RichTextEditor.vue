<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { Placeholder } from '@tiptap/extensions'
import { textToHtml } from '@/lib/richtext'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    minHeight: { type: String, default: '80px' },
})
const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: textToHtml(props.modelValue ?? ''),
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            code: false,
            codeBlock: false,
            horizontalRule: false,
            strike: false,
            link: false,
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate({ editor }) {
        emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML())
    },
})

// Sinkron bila nilai diubah dari luar (mis. hasil Tempel itinerary)
watch(() => props.modelValue, (val) => {
    if (!editor.value) return
    const current = editor.value.isEmpty ? '' : editor.value.getHTML()
    if ((val ?? '') !== current) {
        editor.value.commands.setContent(textToHtml(val ?? ''), { emitUpdate: false })
    }
})

onBeforeUnmount(() => editor.value?.destroy())

function btnClass(active) {
    return [
        'inline-flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-sm transition-colors',
        active ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    ]
}
</script>

<template>
    <div class="rounded-md border border-input bg-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
        :style="{ '--rte-min-h': minHeight }">
        <div class="flex items-center gap-0.5 border-b border-input/60 bg-muted/30 px-1.5 py-1 rounded-t-md">
            <button type="button" title="Tebal (Ctrl/Cmd+B)" tabindex="-1"
                :class="btnClass(editor?.isActive('bold'))"
                @click="editor?.chain().focus().toggleBold().run()">
                <span class="font-bold">B</span>
            </button>
            <button type="button" title="Miring (Ctrl/Cmd+I)" tabindex="-1"
                :class="btnClass(editor?.isActive('italic'))"
                @click="editor?.chain().focus().toggleItalic().run()">
                <span class="italic font-serif">I</span>
            </button>
            <button type="button" title="Daftar poin" tabindex="-1"
                :class="btnClass(editor?.isActive('bulletList'))"
                @click="editor?.chain().focus().toggleBulletList().run()">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="4" cy="6" r="1.2" fill="currentColor" stroke="none" />
                    <circle cx="4" cy="12" r="1.2" fill="currentColor" stroke="none" />
                    <circle cx="4" cy="18" r="1.2" fill="currentColor" stroke="none" />
                    <line x1="9" y1="6" x2="20" y2="6" /><line x1="9" y1="12" x2="20" y2="12" /><line x1="9" y1="18" x2="20" y2="18" />
                </svg>
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
:deep(.tiptap) {
    min-height: var(--rte-min-h, 80px);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    outline: none;
}
:deep(.tiptap p) { margin: 0; }
:deep(.tiptap p + p) { margin-top: 0.25rem; }
:deep(.tiptap ul) { list-style: disc; margin: 0.25rem 0; padding-left: 1.25rem; }
:deep(.tiptap ol) { list-style: decimal; margin: 0.25rem 0; padding-left: 1.25rem; }
:deep(.tiptap p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: hsl(var(--muted-foreground, 215 16% 47%) / 0.7);
}
</style>
