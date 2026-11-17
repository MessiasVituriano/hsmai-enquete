<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { api } from '../lib/api'
import { usePolling } from '../composables/usePolling'
import RichText from '../components/RichText.vue'
import ResultBars from '../components/ResultBars.vue'
import DevelopedBy from '../components/DevelopedBy.vue'

/**
 * O celular, sem cadastro. Espelha o telão, uma pergunta por vez, e quem
 * escolhe qual é o facilitador no painel:
 *   invite   — aguardando a primeira pergunta
 *   question — a pergunta N para responder
 *   results  — o resultado da pergunta N, com a resposta da pessoa marcada
 * Cada toque grava na hora; trocar de ideia vale até a enquete fechar.
 */
// 2s: quando o facilitador troca a pergunta, o celular acompanha quase junto
const { data, online, refresh } = usePolling(() => api.get('/poll'), { interval: 2000 })

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

const current = computed(() => data.value?.questions?.[0] ?? null)
const screen = computed(() => {
    if (!data.value) return null
    if (!current.value) return 'invite'
    return data.value.screen === 'results' && current.value.results ? 'results' : 'question'
})
const accepting = computed(() => data.value?.accepting ?? true)
const total = computed(() => data.value?.total_questions ?? 0)
const answered = computed(() => Object.keys(mine).length)

// a pergunta mudou: a mensagem de erro da anterior não vale mais
watch(() => current.value?.id, () => { error.value = '' })

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
    <div class="flex min-h-dvh flex-col bg-brand-100">
        <header class="bg-brand-800 px-4 pb-6 pt-[max(1.25rem,env(safe-area-inset-top))] text-white">
            <div class="mx-auto max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-500">
                    {{ data?.event_name ?? 'Commercial Strategy Conference' }}
                </p>
                <h1 class="mt-1 text-2xl font-bold leading-tight">{{ data?.title ?? 'Enquete ao vivo' }}</h1>
            </div>
        </header>
        <div class="h-1 bg-accent-500" />

        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 px-4 pt-5">
            <div v-if="!data" class="py-16 text-center text-ink/60">Carregando…</div>

            <div
                v-if="data && !accepting && screen === 'question'"
                class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800"
            >
                Respostas encerradas. O resultado aparece no telão e aqui em instantes.
            </div>

            <Transition name="fade" mode="out-in">
                <!-- aguardando o facilitador -->
                <section
                    v-if="screen === 'invite'"
                    key="invite"
                    class="rounded-xl border border-brand-200 bg-white p-6 text-center shadow-sm"
                >
                    <div class="mx-auto flex w-fit gap-1.5" aria-hidden="true">
                        <span class="size-2.5 animate-pulse rounded-full bg-brand-500" />
                        <span class="size-2.5 animate-pulse rounded-full bg-brand-500 [animation-delay:200ms]" />
                        <span class="size-2.5 animate-pulse rounded-full bg-brand-500 [animation-delay:400ms]" />
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-brand-800">
                        {{ answered ? 'Aguarde a próxima pergunta' : 'Você está dentro!' }}
                    </h2>
                    <p class="mt-1 text-sm text-ink/70">
                        As perguntas aparecem aqui, uma de cada vez, junto com o telão. Fique com esta página aberta.
                    </p>
                </section>

                <!-- a pergunta da vez -->
                <section
                    v-else-if="screen === 'question'"
                    :key="'q' + current.id"
                    class="rounded-xl border border-brand-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-500">
                        Pergunta {{ current.number }} de {{ total }}
                    </p>
                    <RichText tag="p" :text="current.prompt" class="mt-2 text-lg leading-snug text-ink" strong-class="font-bold text-brand-800" />

                    <div class="mt-5 grid gap-2.5" :class="current.options.length === 2 ? 'grid-cols-2' : 'grid-cols-1'">
                        <button
                            v-for="o in current.options"
                            :key="o.id"
                            type="button"
                            :disabled="!accepting"
                            :aria-pressed="mine[current.id] === o.id"
                            class="flex min-h-14 items-center justify-center gap-2 rounded-lg border-2 px-3 py-3 text-center text-base font-semibold transition active:scale-[0.98] disabled:cursor-not-allowed"
                            :class="mine[current.id] === o.id
                                ? 'animate-pop border-brand-800 bg-brand-800 text-white'
                                : accepting
                                    ? 'border-brand-200 bg-brand-50 text-brand-900 hover:border-brand-500'
                                    : 'border-brand-200 bg-brand-50 text-brand-900/50'"
                            @click="choose(current, o)"
                        >
                            <span v-if="mine[current.id] === o.id" aria-hidden="true">✓</span>
                            {{ o.label }}
                        </button>
                    </div>

                    <p v-if="mine[current.id]" class="mt-4 text-center text-sm font-semibold text-accent-600">
                        ✓ Resposta registrada. O resultado aparece aqui logo depois.
                    </p>
                    <p v-if="error" class="mt-4 text-center text-sm font-semibold text-red-700">{{ error }}</p>
                </section>

                <!-- o resultado da pergunta -->
                <section
                    v-else-if="screen === 'results'"
                    :key="'r' + current.id"
                    class="rounded-xl border border-brand-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-500">
                        Resultado · pergunta {{ current.number }} de {{ total }}
                    </p>
                    <RichText tag="p" :text="current.prompt" class="mt-2 text-base leading-snug text-ink" strong-class="font-bold text-brand-800" />
                    <div class="mt-5">
                        <ResultBars :options="current.options" :results="current.results" :mine="mine[current.id] ?? null" size="md" />
                    </div>
                    <p class="mt-4 text-xs text-ink/55">
                        {{ current.results.total }} {{ current.results.total === 1 ? 'resposta' : 'respostas' }}
                        <template v-if="!mine[current.id]"> · você não respondeu esta</template>
                    </p>
                </section>
            </Transition>

            <p v-if="data && !online" class="text-center text-sm text-ink/60">Reconectando…</p>

            <div class="mt-auto flex justify-center pb-[max(1rem,env(safe-area-inset-bottom))] pt-6">
                <DevelopedBy />
            </div>
        </main>
    </div>
</template>
