// Util teks kaya (rich text) untuk deskripsi itinerary.
// Deskripsi lama tersimpan sebagai teks polos; deskripsi baru dari editor
// Tiptap tersimpan sebagai HTML sederhana (<p>, <strong>, <em>, <ul>, <li>).

const ALLOWED_TAGS = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI'])

export function isHtml(value) {
    return /<[a-z][\s\S]*>/i.test(value ?? '')
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
}

/** Teks polos (dengan baris baru) → HTML paragraf, untuk dimuat ke editor. */
export function textToHtml(text) {
    if (!text) return ''
    if (isHtml(text)) return text
    return text
        .split(/\r?\n/)
        .map(line => `<p>${escapeHtml(line)}</p>`)
        .join('')
}

/** Buang tag & atribut di luar whitelist. Konten berasal dari staf internal,
 *  ini sekadar pengaman bila ada HTML tempelan dari luar. */
export function sanitizeHtml(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html')
    doc.body.querySelectorAll('script, style').forEach(el => el.remove())
    doc.body.querySelectorAll('*').forEach(el => {
        if (!ALLOWED_TAGS.has(el.tagName)) {
            el.replaceWith(...el.childNodes)
            return
        }
        for (const attr of [...el.attributes]) el.removeAttribute(attr.name)
    })
    return doc.body.innerHTML
}

/** Untuk v-html di halaman tampilan: HTML disanitasi, teks polos di-nl2br. */
export function toDisplayHtml(value) {
    if (!value) return ''
    if (isHtml(value)) return sanitizeHtml(value)
    return escapeHtml(value).replace(/\r?\n/g, '<br>')
}

/** HTML editor → teks polos (untuk tombol Salin & kompatibilitas Tempel). */
export function htmlToText(value) {
    if (!value) return ''
    if (!isHtml(value)) return value
    const doc = new DOMParser().parseFromString(value, 'text/html')
    const lines = []
    doc.body.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
            if (node.textContent.trim()) lines.push(node.textContent.trim())
        } else if (node.tagName === 'UL' || node.tagName === 'OL') {
            node.querySelectorAll('li').forEach(li => lines.push(li.textContent.trim()))
        } else {
            const text = node.textContent.trim()
            if (text) lines.push(text)
        }
    })
    return lines.join('\n')
}
