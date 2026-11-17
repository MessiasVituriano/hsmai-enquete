<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

/**
 * Palco fixo de 1920×1080, escalado para caber na janela. O telão fica
 * idêntico ao slide em qualquer projetor ou notebook — nada reflui, só escala.
 */
const W = 1920
const H = 1080
const scale = ref(1)

function fit() {
    scale.value = Math.min(window.innerWidth / W, window.innerHeight / H)
}

onMounted(() => {
    fit()
    window.addEventListener('resize', fit)
})
onUnmounted(() => window.removeEventListener('resize', fit))
</script>

<template>
    <div class="fixed inset-0 overflow-hidden bg-brand-950">
        <div
            class="absolute left-1/2 top-1/2 overflow-hidden"
            :style="{ width: W + 'px', height: H + 'px', transform: `translate(-50%, -50%) scale(${scale})` }"
        >
            <slot />
        </div>
    </div>
</template>
