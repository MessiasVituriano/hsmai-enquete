<script setup>
import { ref, watch } from 'vue'

/** Logo opcional: se o arquivo não existe em public/brand/, o espaço some. */
const props = defineProps({ src: { type: String, default: '' }, alt: { type: String, default: '' } })

const ok = ref(true)
watch(() => props.src, () => { ok.value = true })
</script>

<template>
    <img v-if="src && ok" :src="src" :alt="alt" @error="ok = false">
</template>
