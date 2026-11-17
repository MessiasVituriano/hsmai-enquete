<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, masterToken } from '../lib/api'
import { usePolling } from '../composables/usePolling'
import RichText from '../components/RichText.vue'
import ResultBars from '../components/ResultBars.vue'

/**
 * O painel de quem conduz: resultados ao vivo de todas as perguntas (nunca
 * projetado) e os botões que trocam o que o telão mostra.
 */
const route = useRoute()
const router = useRouter()

const authed = ref(false)
const tokenInput = ref('')
const authError = ref('')
const busy = ref(false)
const actionError = ref('')

const { data, online, start, stop, refresh } = usePolling(async () => {
    try {
        return await api.get('/admin/overview')
    } catch (e) {
        if (e.status === 401) {
            stop()
            authed.value = false
            authError.value = 'Token inválido.'
        }
        throw e
    }
}, { interval: 1000, immediate: false })

async function login(token) {
    masterToken.set(token)
    authError.value = ''
    try {
        data.value = await api.get('/admin/overview')
        authed.value = true
        start()
    } catch (e) {
        authError.value = e.status === 401 ? 'Token inválido.' : 'Servidor fora do ar.'
    }
}

onMounted(async () => {
    // /master?token=... uma vez: guarda e limpa da barra de endereço
    const fromUrl = route.query.token
    if (typeof fromUrl === 'string' && fromUrl) {
        router.replace({ query: {} })
        await login(fromUrl)
    } else if (masterToken.get()) {
        await login(masterToken.get())
    }
})

async function act(path, body = {}, confirmText = null) {
    if (confirmText && !window.confirm(confirmText)) return
    busy.value = true
    actionError.value = ''
    try {
        data.value = await api.post(path, body)
    } catch (e) {
        actionError.value = e.message
        refresh()
    } finally {
        busy.value = false
    }
}

const questions = computed(() => data.value?.questions ?? [])
const isOn = (screen, id = null) =>
    data.value?.screen === screen && (screen !== 'question' || data.value?.screen_question_id === id)

const screenLabel = computed(() => {
    const d = data.value
    if (!d) return ''
    if (d.screen === 'question') {
        const q = questions.value.find((x) => x.id === d.screen_question_id)
        return q ? `Resultado da pergunta ${q.number}` : 'Convite + QR code'
    }
    return d.screen === 'all' ? 'As quatro perguntas' : 'Convite + QR code'
})
</script>

<template>
    <div class="min-h-dvh bg-brand-100">
        <!-- portão do token -->
        <div v-if="!authed" class="grid min-h-dvh place-items-center px-4">
            <form class="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-6 shadow-sm" @submit.prevent="login(tokenInput)">
                <h1 class="text-xl font-bold text-brand-800">Painel da enquete</h1>
                <p class="mt-1 text-sm text-ink/70">Informe o token do facilitador (<code>MASTER_TOKEN</code>).</p>
                <input
                    v-model="tokenInput"
                    type="password"
                    autocomplete="current-password"
                    class="mt-4 w-full rounded-lg border border-brand-300 px-3 py-2 outline-none focus:border-brand-800"
                    placeholder="Token"
                >
                <p v-if="authError" class="mt-2 text-sm font-semibold text-red-700">{{ authError }}</p>
                <button class="mt-4 w-full rounded-lg bg-brand-800 py-2.5 font-semibold text-white hover:bg-brand-900">Entrar</button>
            </form>
        </div>

        <template v-else-if="data">
            <header class="bg-brand-800 text-white">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-500">{{ data.event_name }}</p>
                        <h1 class="text-xl font-bold">Painel da enquete</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span v-if="!online" class="rounded-full bg-red-600 px-3 py-1 font-semibold">sem conexão</span>
                        <a href="/display" target="_blank" class="rounded-lg bg-white/10 px-3 py-1.5 font-semibold hover:bg-white/20">Abrir telão ↗</a>
                        <a href="/" target="_blank" class="rounded-lg bg-white/10 px-3 py-1.5 font-semibold hover:bg-white/20">Ver como participante ↗</a>
                    </div>
                </div>
                <div class="h-1 bg-accent-500" />
            </header>

            <main class="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-5">
                <p v-if="actionError" class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">{{ actionError }}</p>

                <!-- estado geral -->
                <section class="grid gap-4 md:grid-cols-[1fr_2fr]">
                    <div class="rounded-xl border border-brand-200 bg-white p-5">
                        <p class="text-sm font-semibold text-ink/60">Pessoas que responderam</p>
                        <p class="mt-1 text-5xl font-bold tabular-nums text-brand-800">{{ data.respondents }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <span
                                class="whitespace-nowrap rounded-full px-3 py-1 text-sm font-semibold"
                                :class="data.accepting ? 'bg-accent-500/20 text-accent-600' : 'bg-brand-200 text-brand-800'"
                            >
                                {{ data.accepting ? '● Recebendo respostas' : 'Encerrada' }}
                            </span>
                            <button
                                :disabled="busy"
                                class="rounded-lg border border-brand-300 px-3 py-1.5 text-sm font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-50"
                                @click="act('/admin/accepting', { accepting: !data.accepting })"
                            >
                                {{ data.accepting ? 'Encerrar respostas' : 'Reabrir respostas' }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-brand-200 bg-white p-5">
                        <p class="text-sm font-semibold text-ink/60">O telão está mostrando</p>
                        <p class="mt-1 text-2xl font-bold text-brand-800">{{ screenLabel }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                :disabled="busy"
                                class="rounded-lg px-4 py-2 font-semibold transition disabled:opacity-50"
                                :class="isOn('invite') ? 'bg-brand-800 text-white' : 'border border-brand-300 text-brand-800 hover:bg-brand-50'"
                                @click="act('/admin/screen', { screen: 'invite' })"
                            >
                                Convite + QR
                            </button>
                            <button
                                v-for="q in questions"
                                :key="q.id"
                                :disabled="busy"
                                class="rounded-lg px-4 py-2 font-semibold transition disabled:opacity-50"
                                :class="isOn('question', q.id) ? 'bg-brand-800 text-white' : 'border border-brand-300 text-brand-800 hover:bg-brand-50'"
                                @click="act('/admin/screen', { screen: 'question', question_id: q.id })"
                            >
                                Pergunta {{ q.number }}
                            </button>
                            <button
                                :disabled="busy"
                                class="rounded-lg px-4 py-2 font-semibold transition disabled:opacity-50"
                                :class="isOn('all') ? 'bg-brand-800 text-white' : 'border border-brand-300 text-brand-800 hover:bg-brand-50'"
                                @click="act('/admin/screen', { screen: 'all' })"
                            >
                                Todas
                            </button>
                        </div>
                        <p class="mt-3 text-xs text-ink/60">
                            Levar um resultado ao telão também o libera nos celulares. “Recolher” desfaz as duas coisas.
                        </p>
                    </div>
                </section>

                <!-- resultados ao vivo -->
                <section class="grid gap-4 md:grid-cols-2">
                    <article v-for="q in questions" :key="q.id" class="flex flex-col rounded-xl border border-brand-200 bg-white p-5">
                        <div class="flex gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-800 font-bold text-white">{{ q.number }}</span>
                            <RichText tag="p" :text="q.prompt" class="pt-1 text-sm leading-snug" strong-class="font-bold text-brand-800" />
                        </div>

                        <div class="mt-4">
                            <ResultBars :options="q.options" :results="q.results" size="md" show-counts />
                        </div>

                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-4">
                            <span class="text-sm text-ink/60">
                                {{ q.results.total }} {{ q.results.total === 1 ? 'resposta' : 'respostas' }}
                                <span v-if="q.revealed" class="ml-2 rounded-full bg-accent-500/20 px-2 py-0.5 text-xs font-semibold text-accent-600">liberada</span>
                            </span>
                            <div class="flex gap-2">
                                <button
                                    v-if="q.revealed"
                                    :disabled="busy"
                                    class="rounded-lg border border-brand-300 px-3 py-1.5 text-sm font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-50"
                                    @click="act(`/admin/questions/${q.id}/hide`)"
                                >
                                    Recolher
                                </button>
                                <button
                                    :disabled="busy || isOn('question', q.id)"
                                    class="rounded-lg bg-brand-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-900 disabled:opacity-50"
                                    @click="act('/admin/screen', { screen: 'question', question_id: q.id })"
                                >
                                    {{ isOn('question', q.id) ? 'No telão' : 'Mostrar no telão' }}
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- zona de risco -->
                <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-white p-5">
                    <div>
                        <p class="font-semibold text-red-800">Zerar enquete</p>
                        <p class="text-sm text-ink/60">Apaga todas as respostas, recolhe os resultados e volta o telão ao convite. Use no ensaio, não durante o painel.</p>
                    </div>
                    <button
                        :disabled="busy"
                        class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50"
                        @click="act('/admin/reset', {}, `Apagar as respostas de ${data.respondents} pessoa(s)? Não dá para desfazer.`)"
                    >
                        Zerar tudo
                    </button>
                </section>
            </main>
        </template>
    </div>
</template>
