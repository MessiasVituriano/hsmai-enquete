<script setup>
import { computed } from 'vue'

/** Texto do config com **negrito**. Escapa tudo antes, então o v-html só vê <strong>. */
const props = defineProps({
    text: { type: String, default: '' },
    tag: { type: String, default: 'span' },
    strongClass: { type: String, default: '' },
})

const html = computed(() => {
    const escaped = props.text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')

    const cls = props.strongClass ? ` class="${props.strongClass}"` : ''

    return escaped.replace(/\*\*(.+?)\*\*/g, `<strong${cls}>$1</strong>`)
})
</script>

<template>
    <component :is="tag" v-html="html" />
</template>
