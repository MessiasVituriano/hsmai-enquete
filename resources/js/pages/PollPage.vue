<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { api } from '../lib/api'
import { usePolling } from '../composables/usePolling'
import RichText from '../components/RichText.vue'
import ResultBars from '../components/ResultBars.vue'
import DevelopedBy from '../components/DevelopedBy.vue'

/**
 * O celular: as quatro perguntas numa tela só, sem cadastro. Cada toque grava
 * na hora; trocar de ideia vale até o facilitador encerrar a enquete.
 */
const { data, online, refresh } = usePolling(() => api.get('/poll'), { interval: 3000 })

// o que este aparelho marcou. Começa do servidor e passa a ser a fonte da
// verdade local, para o toque responder antes do round-trip
const mine = reactive({})
const pending = reactive({})
const error = ref('')
let lastWrite = 0

// Adota o que o servidor diz, exceto logo depois de um toque: um poll que saiu
// antes do POST pode chegar depois dele e desfazer a escolha na tela. Seguir o
// servidor no resto do tempo é o que faz um "zerar enquete" limpar o celular.
watch(data, (d) => {
    if (!d || Object.keys(pending).length || Date.now() - lastWrite < 5000) return
    for (const key of Object.keys(mine)) delete mine[key]
    Object.assign(mine, d.mine ?? {})
})

const questions = computed(() => data.value?.questions ?? [])
const accepting = computed(() => data.value?.accepting ?? true)
const answered = computed(() => questions.value.filter((q) => mine[q.id]).length)
const done = computed(() => questions.value.length > 0 && answered.value === questions.value.length)

async function choose(question, option) {
    if (!accepting.value || pending[question.id]) return
    if (mine[question.id] === option.id) return

    const previous = mine[question.id]
    mine[question.id] = option.id
    pending[question.id] = true
    error.value = ''

    try {
        await api.post('/answer', { question_id: question.id, option_id: option.id })
    } catch (e) {
        if (previous) mine[question.id] = previous
        else delete mine[question.id]
        error.value = e.status === 423
            ? 'A enquete foi encerrada. Obrigado por participar!'
            : 'Não conseguimos registrar. Toque de novo.'
        if (e.status === 423) refresh()
    } finally {
        lastWrite = Date.now()
        delete pending[question.id]
    }
}
</script>

<template>
    <div class="min-h-dvh bg-brand-100 pb-28">
        <header class="bg-brand-800 px-4 pb-6 pt-[max(1.25rem,env(safe-area-inset-top))] text-white">
            <div class="mx-auto max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-500">
                    {{ data?.event_name ?? 'Commercial Strategy Conference' }}
                </p>
                <h1 class="mt-1 text-2xl font-bold leading-tight">{{ data?.title ?? 'Enquete ao vivo' }}</h1>
                <p class="mt-1 text-sm text-white/75">Toque numa alternativa por pergunta. Pode mudar até o fim da enquete.</p>
            </div>
        </header>
        <div class="h-1 bg-accent-500" />

        <main class="mx-auto flex max-w-xl flex-col gap-3 px-4 pt-4">
            <div v-if="!data" class="py-16 text-center text-ink/60">Carregando perguntas…</div>

            <div
                v-if="data && !accepting"
                class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800"
            >
                Enquete encerrada. Os resultados aparecem no telão ao longo do painel.
            </div>

            <section
                v-for="q in questions"
                :key="q.id"
                class="rounded-xl border border-brand-200 bg-white p-4 shadow-sm"
            >
                <div class="flex gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-800 text-base font-bold text-white">
                        {{ q.number }}
                    </span>
                    <RichText tag="p" :text="q.prompt" class="pt-1 text-[15px] leading-snug text-ink" strong-class="font-bold text-brand-800" />
                </div>

                <div class="mt-4 grid gap-2" :class="q.options.length === 2 ? 'grid-cols-2' : 'grid-cols-1'">
                    <button
                        v-for="o in q.options"
                        :key="o.id"
                        type="button"
                        :disabled="!accepting"
                        :aria-pressed="mine[q.id] === o.id"
                        class="flex min-h-12 items-center justify-center gap-2 rounded-lg border-2 px-3 py-2.5 text-center text-[15px] font-semibold transition active:scale-[0.98] disabled:cursor-not-allowed"
                        :class="mine[q.id] === o.id
                            ? 'animate-pop border-brand-800 bg-brand-800 text-white'
                            : accepting
                                ? 'border-brand-200 bg-brand-50 text-brand-900 hover:border-brand-500'
                                : 'border-brand-200 bg-brand-50 text-brand-900/50'"
                        @click="choose(q, o)"
                    >
                        <span v-if="mine[q.id] === o.id" aria-hidden="true">✓</span>
                        {{ o.label }}
                    </button>
                </div>

                <div v-if="q.results" class="mt-4 border-t border-brand-100 pt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink/50">
                        Resultado da sala · {{ q.results.total }} {{ q.results.total === 1 ? 'resposta' : 'respostas' }}
                    </p>
                    <ResultBars :options="q.options" :results="q.results" :mine="mine[q.id] ?? null" size="sm" />
                </div>
            </section>

            <div class="flex justify-center pb-2 pt-5">
                <DevelopedBy />
            </div>
        </main>

        <footer
            v-if="data"
            class="fixed inset-x-0 bottom-0 border-t border-brand-200 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur"
        >
            <div class="mx-auto max-w-xl">
                <p v-if="error" class="mb-2 text-sm font-semibold text-red-700">{{ error }}</p>
                <p v-else-if="!online" class="mb-2 text-sm text-ink/60">Reconectando…</p>

                <div class="flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-brand-100">
                        <div
                            class="bar-fill h-full rounded-full"
                            :class="done ? 'bg-accent-500' : 'bg-brand-800'"
                            :style="{ width: (questions.length ? (answered / questions.length) * 100 : 0) + '%' }"
                        />
                    </div>
                    <p class="shrink-0 text-sm font-semibold" :class="done ? 'text-accent-600' : 'text-ink/70'">
                        <template v-if="done">✓ Respostas enviadas</template>
                        <template v-else>{{ answered }} de {{ questions.length }}</template>
                    </p>
                </div>
                <p v-if="done" class="mt-1 text-xs text-ink/60">Obrigado! Os resultados voltam no telão ao longo do painel.</p>
            </div>
        </footer>
    </div>
</template>
