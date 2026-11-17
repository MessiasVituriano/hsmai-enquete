<script setup>
import BrandMark from './BrandMark.vue'

/**
 * Crédito "desenvolvido por: Sistema IO" com o símbolo da marca.
 *
 * tone="light" é para fundo claro (celular, painel). tone="dark" é para o
 * rodapé roxo do telão: lá o azul do símbolo some, então a marca vai num
 * selo branco em vez de direto sobre o roxo.
 */
defineProps({
    tone: { type: String, default: 'light' }, // light | dark
    size: { type: String, default: 'sm' },    // sm | stage
})
</script>

<template>
    <p
        class="inline-flex select-none items-center"
        :class="size === 'stage' ? 'gap-[16px]' : 'gap-2'"
    >
        <span
            class="font-medium"
            :class="[
                size === 'stage' ? 'text-[22px]' : 'text-xs',
                tone === 'dark' ? 'text-white/75' : 'text-ink/55',
            ]"
        >desenvolvido por:</span>

        <span
            class="inline-flex items-center"
            :class="[
                size === 'stage' ? 'gap-[10px] rounded-[12px] px-[16px] py-[8px]' : 'gap-1.5',
                tone === 'dark' ? 'bg-white' : '',
            ]"
        >
            <BrandMark :size="size === 'stage' ? 26 : 13" aria-hidden="true" />
            <span
                class="bg-gradient-to-r from-[#0b4c93] via-[#1b86c4] to-[#3fa63c] bg-clip-text font-black leading-none text-transparent"
                :class="size === 'stage' ? 'text-[28px]' : 'text-sm'"
            >Sistema IO</span>
        </span>
    </p>
</template>
