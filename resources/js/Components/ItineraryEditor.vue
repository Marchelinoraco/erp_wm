<template>
    <div class="itinerary-editor">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 border border-b-0 rounded-t-md bg-muted/40">
            <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="['toolbar-btn', editor?.isActive('bold') && 'is-active']" title="Bold">
                <strong>B</strong>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="['toolbar-btn', editor?.isActive('italic') && 'is-active']" title="Italic">
                <em>I</em>
            </button>
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
                :class="['toolbar-btn', editor?.isActive('bulletList') && 'is-active']" title="Bullet List">
                &#8226;&#8212;
            </button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
                :class="['toolbar-btn', editor?.isActive('orderedList') && 'is-active']" title="Numbered List">
                1&#8212;
            </button>
            <div class="w-px h-5 bg-border mx-1"></div>
            <button type="button" @click="triggerImageUpload" title="Upload Gambar"
                class="toolbar-btn" :disabled="uploading">
                <span v-if="uploading" class="text-xs">...</span>
                <span v-else>&#128247;</span>
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
        </div>

        <!-- Editor area -->
        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none border rounded-b-md bg-background px-3 py-2 min-h-[100px] focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
        />
    </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'

const props = defineProps({
    modelValue: { type: String, default: '' },
    tourId:     { type: [Number, String], required: true },
    placeholder: { type: String, default: 'Aktivitas, jadwal, tempat yang dikunjungi...' },
})
const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const uploading = ref(false)

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit,
        Image.configure({ inline: false, allowBase64: false }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: { class: 'outline-none min-h-[80px]' },
    },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML())
    },
})

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val || '', false)
    }
})

onBeforeUnmount(() => editor.value?.destroy())

function triggerImageUpload() {
    fileInput.value?.click()
}

async function handleImageUpload(e) {
    const file = e.target.files?.[0]
    if (!file) return

    uploading.value = true
    const formData = new FormData()
    formData.append('image', file)

    try {
        const res = await fetch(route('tours.itinerary.image.upload', props.tourId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: formData,
        })
        const data = await res.json()
        if (data.url) {
            editor.value?.chain().focus().setImage({ src: data.url }).run()
        }
    } catch (err) {
        console.error('Upload gagal:', err)
    } finally {
        uploading.value = false
        e.target.value = ''
    }
}
</script>

<style scoped>
.toolbar-btn {
    @apply inline-flex items-center justify-center w-7 h-7 rounded text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors;
}
.toolbar-btn.is-active {
    @apply bg-primary/10 text-primary;
}
</style>
