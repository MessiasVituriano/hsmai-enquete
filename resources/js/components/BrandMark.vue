<script setup>
// O símbolo "io" oficial da Sistema IO (public/brand/logo-io.png, sem margem).
// `size` é a largura em px; a altura segue a proporção do arquivo (76 × 97).
// Caminho em variável: com `src` literal o Vite tenta importar como módulo.
const SRC = '/brand/logo-io.png'

defineProps({
    size: { type: [Number, String], default: 40 },
})
</script>

<template>
    <img
        :src="SRC"
        alt="Sistema IO"
        :width="size"
        :height="Math.round(Number(size) * 97 / 76)"
        class="shrink-0 select-none"
        draggable="false"
    >
</template>
