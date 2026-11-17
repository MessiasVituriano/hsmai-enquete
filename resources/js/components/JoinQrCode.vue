<script setup>
import { onMounted, ref } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
    size: { type: Number, default: 360 },
})

const src = ref('')
// a enquete mora na raiz, no mesmo host que o telão está usando
const url = window.location.origin + '/'

onMounted(async () => {
    src.value = await QRCode.toDataURL(url, {
        width: props.size * 2,
        margin: 1,
        color: { dark: '#061f3d', light: '#ffffff' },
    })
})
</script>

<template>
    <img v-if="src" :src="src" alt="QR code da enquete" :width="size" :height="size" class="block">
    <div v-else :style="{ width: size + 'px', height: size + 'px' }" />
</template>
