<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'

/**
 * Barras horizontais de uma pergunta. Mesma ordem das alternativas (não ordena
 * por voto: a sala reconhece a pergunta pela ordem em que leu as opções).
 *
 * Ao aparecer, as barras crescem do zero uma após a outra e os percentuais
 * contam até o valor; depois, cada voto novo anima a diferença. É o momento da
 * revelação no telão — sem isso o resultado só "pisca" pronto na tela.
 */
const props = defineProps({
    options: { type: Array, required: true },   // [{ id, label }]
    results: { type: Object, required: true },  // { total, options: [{ id, count, percent }] }
    size: { type: String, default: 'md' },      // sm | md | lg | xl
    mine: { type: Number, default: null },
    showCounts: { type: Boolean, default: false },
})

const rows = computed(() => {
    const byId = Object.fromEntries(props.results.options.map((r) => [r.id, r]))
    return props.options.map((o, i) => ({
        ...o,
        color: PALETTE[i % PALETTE.length],
        count: byId[o.id]?.count ?? 0,
        percent: byId[o.id]?.percent ?? 0,
    }))
})

// --- animação --------------------------------------------------------------
const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
const width = reactive({})   // id -> % da barra (a transição CSS faz o movimento)
const shown = reactive({})   // id -> % escrito, contado quadro a quadro
const entering = ref(!reduced)
let frame = null
let enterTimer = null

function countTo(targets) {
    cancelAnimationFrame(frame)
    const from = { ...shown }
    const start = performance.now()
    const duration = entering.value ? 1100 : 600

    const step = (now) => {
        const t = Math.min(1, (now - start) / duration)
        const eased = 1 - Math.pow(1 - t, 3)
        for (const [id, to] of Object.entries(targets)) {
            const a = from[id] ?? 0
            shown[id] = Math.round(a + (to - a) * eased)
        }
        if (t < 1) frame = requestAnimationFrame(step)
    }
    frame = requestAnimationFrame(step)
}

function apply() {
    const targets = Object.fromEntries(rows.value.map((r) => [r.id, r.percent]))
    if (reduced) {
        Object.assign(width, targets)
        Object.assign(shown, targets)
        return
    }
    // um quadro com as barras em zero antes de mirar o alvo, senão não há transição
    requestAnimationFrame(() => Object.assign(width, targets))
    countTo(targets)
}

onMounted(() => {
    for (const r of rows.value) { width[r.id] = 0; shown[r.id] = 0 }
    apply()
    enterTimer = setTimeout(() => { entering.value = false }, 1400)
})
onUnmounted(() => { cancelAnimationFrame(frame); clearTimeout(enterTimer) })
watch(() => rows.value.map((r) => `${r.id}:${r.percent}`).join(), apply)

const S = {
    sm: { label: 'text-sm', pct: 'text-sm', bar: 'h-2.5', gap: 'gap-2.5' },
    md: { label: 'text-base', pct: 'text-base', bar: 'h-3.5', gap: 'gap-3' },
    stage: { label: 'text-[24px]', pct: 'text-[32px]', bar: 'h-6', gap: 'gap-5' },
    lg: { label: 'text-[30px]', pct: 'text-[38px]', bar: 'h-8', gap: 'gap-7' },
    xl: { label: 'text-[38px]', pct: 'text-[52px]', bar: 'h-12', gap: 'gap-9' },
}
const s = computed(() => S[props.size] ?? S.md)
</script>

<script>
export const PALETTE = ['#0b4c93', '#8cc63f', '#1b86c4', '#5f6b7a']
</script>

<template>
    <ul class="flex flex-col" :class="s.gap">
        <li v-for="(row, i) in rows" :key="row.id">
            <div class="flex items-baseline justify-between gap-4 leading-tight">
                <span class="font-semibold text-ink" :class="s.label">
                    {{ row.label }}
                    <span v-if="mine === row.id" class="ml-1 text-[0.75em] font-bold text-accent-600">✓ sua resposta</span>
                </span>
                <span class="shrink-0 tabular-nums font-bold" :class="s.pct" :style="{ color: row.color }">
                    {{ shown[row.id] ?? row.percent }}%<span v-if="showCounts" class="ml-2 text-[0.6em] font-semibold text-ink/50">{{ row.count }}</span>
                </span>
            </div>
            <div class="mt-1.5 w-full overflow-hidden rounded-full bg-brand-200/70" :class="s.bar">
                <div
                    class="bar-fill h-full rounded-full"
                    :class="{ 'bar-enter': entering }"
                    :style="{ width: (width[row.id] ?? row.percent) + '%', background: row.color, transitionDelay: entering ? i * 140 + 'ms' : '0ms' }"
                />
            </div>
        </li>
    </ul>
</template>
