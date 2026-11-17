<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, masterToken } from '../lib/api'
import { usePolling } from '../composables/usePolling'
import RichText from '../components/RichText.vue'
import ResultBars from '../components/ResultBars.vue'
import DevelopedBy from '../components/DevelopedBy.vue'

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
            people.stop()
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
        // start() só agenda; a primeira busca da lista sai agora
        people.refresh()
        people.start()
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

// --- cadastros: lista a cada 5s (fora do poll de 1s) e planilha -------------
const people = usePolling(() => api.get('/admin/respondents'), { interval: 5000, immediate: false })
const respondents = computed(() => people.data.value?.respondents ?? [])
const peopleFilter = ref('')
const filteredPeople = computed(() => {
    const term = peopleFilter.value.trim().toLowerCase()
    if (!term) return respondents.value
    return respondents.value.filter((r) =>
        [r.name, r.email, r.phone, r.hotel].some((v) => v && String(v).toLowerCase().includes(term)))
})
const downloading = ref(false)

async function downloadCsv() {
    downloading.value = true
    actionError.value = ''
    try {
        // fetch com o token no header (não na URL, que ficaria no log do servidor)
        const res = await fetch('/api/admin/respondents.csv', { headers: { 'X-Master-Token': masterToken.get() ?? '' } })
        if (!res.ok) throw new Error(`Erro ${res.status}`)
        const blob = await res.blob()
        const name = res.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1] ?? 'enquete-participantes.csv'
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: name })
        a.click()
        URL.revokeObjectURL(a.href)
    } catch (e) {
        actionError.value = 'Não foi possível baixar a planilha: ' + e.message
    } finally {
        downloading.value = false
    }
}

const questions = computed(() => data.value?.questions ?? [])
const isOn = (screen, id = null) =>
    data.value?.screen === screen && (screen === 'invite' || data.value?.screen_question_id === id)

/*
 * O roteiro em ordem: convite → pergunta 1 → resultado 1 → pergunta 2 →
 * resultado 2 → … Cada pergunta fecha com o próprio resultado antes de a
 * próxima abrir. "Próxima" e "Anterior" andam por esta lista; os botões de
 * cada pergunta saltam para qualquer ponto dela.
 */
const steps = computed(() => [
    { screen: 'invite', label: 'Convite + QR code' },
    ...questions.value.flatMap((q) => [
        { screen: 'question', question_id: q.id, label: `Pergunta ${q.number} (respondendo)` },
        { screen: 'results', question_id: q.id, label: `Resultado da pergunta ${q.number}` },
    ]),
])
const stepIndex = computed(() => {
    const d = data.value
    const i = steps.value.findIndex((s) => s.screen === d?.screen && (s.screen === 'invite' || s.question_id === d?.screen_question_id))
    return Math.max(i, 0)
})
const currentStep = computed(() => steps.value[stepIndex.value])
const prevStep = computed(() => steps.value[stepIndex.value - 1] ?? null)
const nextStep = computed(() => steps.value[stepIndex.value + 1] ?? null)

function goTo(step) {
    if (!step) return
    act('/admin/screen', { screen: step.screen, question_id: step.question_id ?? null })
}

const phoneLabel = computed(() => {
    const d = data.value
    const q = questions.value.find((x) => x.id === d?.screen_question_id)
    if (d?.screen === 'question' && q) return `pergunta ${q.number} para responder`
    if (d?.screen === 'results' && q) return `resultado da pergunta ${q.number}`
    return 'aguardando a primeira pergunta'
})
</script>

<template>
    <div class="min-h-dvh bg-brand-100">
        <!-- portão do token -->
        <div v-if="!authed" class="flex min-h-dvh flex-col items-center justify-center gap-6 px-4">
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
            <DevelopedBy />
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
                        <div class="flex gap-6">
                            <div>
                                <p class="text-sm font-semibold text-ink/60">Cadastrados</p>
                                <p class="mt-1 text-5xl font-bold tabular-nums text-brand-800">{{ data.registered }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-ink/60">Já responderam</p>
                                <p class="mt-1 text-5xl font-bold tabular-nums text-brand-500">{{ data.respondents }}</p>
                            </div>
                        </div>
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
                        <p class="text-sm font-semibold text-ink/60">No telão agora · passo {{ stepIndex + 1 }} de {{ steps.length }}</p>
                        <p class="mt-1 text-2xl font-bold text-brand-800">{{ currentStep.label }}</p>
                        <p class="mt-1 text-sm text-ink/70">
                            📱 Nos celulares: <strong class="font-semibold text-brand-800">{{ phoneLabel }}</strong>
                        </p>

                        <div class="mt-4 flex flex-wrap items-stretch gap-2">
                            <button
                                :disabled="busy || !prevStep"
                                class="rounded-lg border border-brand-300 px-4 py-2.5 font-semibold text-brand-800 hover:bg-brand-50 disabled:opacity-40"
                                @click="goTo(prevStep)"
                            >
                                ‹ Anterior
                            </button>
                            <button
                                :disabled="busy || !nextStep"
                                class="flex-1 rounded-lg bg-brand-800 px-4 py-2.5 text-left font-semibold text-white hover:bg-brand-900 disabled:opacity-40"
                                @click="goTo(nextStep)"
                            >
                                <span class="block text-xs font-medium uppercase tracking-wider text-white/70">Próxima ›</span>
                                {{ nextStep ? nextStep.label : 'Fim do roteiro' }}
                            </button>
                        </div>
                        <button
                            v-if="!isOn('invite')"
                            :disabled="busy"
                            class="mt-2 text-sm font-semibold text-brand-700 hover:underline disabled:opacity-50"
                            @click="act('/admin/screen', { screen: 'invite' })"
                        >
                            Voltar ao convite com o QR code
                        </button>
                        <p class="mt-3 text-xs text-ink/60">
                            A ordem é pergunta → resultado → próxima pergunta. A pergunta vai ao telão e aos celulares sem
                            resultado; o resultado dela entra no clique seguinte.
                        </p>
                    </div>
                </section>

                <!-- resultados ao vivo: só aqui, nunca projetado -->
                <p class="-mb-2 text-sm font-semibold text-ink/60">Resultados ao vivo (só você vê)</p>
                <section class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="q in questions"
                        :key="q.id"
                        class="flex flex-col rounded-xl border-2 bg-white p-5 transition"
                        :class="data.screen_question_id === q.id ? 'border-brand-800' : 'border-brand-200'"
                    >
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
                                <span v-if="q.revealed" class="ml-2 rounded-full bg-accent-500/20 px-2 py-0.5 text-xs font-semibold text-accent-600">resultado já mostrado</span>
                            </span>
                            <div class="flex gap-2">
                                <button
                                    :disabled="busy || isOn('question', q.id)"
                                    class="rounded-lg px-3 py-1.5 text-sm font-semibold disabled:cursor-default"
                                    :class="isOn('question', q.id) ? 'bg-accent-500 text-white' : 'bg-brand-800 text-white hover:bg-brand-900 disabled:opacity-50'"
                                    @click="act('/admin/screen', { screen: 'question', question_id: q.id })"
                                >
                                    {{ isOn('question', q.id) ? '● Respondendo agora' : 'Perguntar agora' }}
                                </button>
                                <button
                                    :disabled="busy || isOn('results', q.id)"
                                    class="rounded-lg px-3 py-1.5 text-sm font-semibold disabled:cursor-default"
                                    :class="isOn('results', q.id) ? 'bg-accent-500 text-white' : 'border border-brand-300 text-brand-800 hover:bg-brand-50 disabled:opacity-50'"
                                    @click="act('/admin/screen', { screen: 'results', question_id: q.id })"
                                >
                                    {{ isOn('results', q.id) ? '● Resultado na tela' : 'Mostrar resultado' }}
                                </button>
                            </div>
                        </div>
                    </article>
                </section>

                <!-- participantes -->
                <section class="rounded-xl border border-brand-200 bg-white p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="mr-auto">
                            <h2 class="text-lg font-bold text-brand-800">Participantes</h2>
                            <p class="text-sm text-ink/60">{{ respondents.length }} cadastrados · atualiza a cada 5 segundos</p>
                        </div>
                        <input
                            v-model="peopleFilter"
                            type="search"
                            placeholder="Buscar nome, contato ou hotel"
                            class="w-full rounded-lg border border-brand-300 px-3 py-2 text-sm outline-none focus:border-brand-800 sm:w-64"
                        >
                        <button
                            :disabled="downloading || !respondents.length"
                            class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-900 disabled:opacity-40"
                            @click="downloadCsv"
                        >
                            {{ downloading ? 'Baixando…' : '⬇ Baixar planilha (CSV)' }}
                        </button>
                    </div>

                    <div class="mt-4 max-h-[28rem] overflow-auto rounded-lg border border-brand-100">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="sticky top-0 bg-brand-50 text-xs uppercase tracking-wider text-ink/60">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Nome</th>
                                    <th class="px-3 py-2 font-semibold">Contato</th>
                                    <th class="px-3 py-2 font-semibold">Hotel</th>
                                    <th v-for="q in questions" :key="q.id" class="px-3 py-2 font-semibold">P{{ q.number }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in filteredPeople" :key="r.id" class="border-t border-brand-100">
                                    <td class="px-3 py-2 font-semibold text-ink">{{ r.name }}</td>
                                    <td class="px-3 py-2 text-ink/80">
                                        <span v-if="r.email">{{ r.email }}</span>
                                        <span v-else>
                                            <span class="mr-1 rounded bg-accent-500/20 px-1 text-[10px] font-bold text-accent-600">WA</span>{{ r.phone }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-ink/80">{{ r.hotel }}</td>
                                    <td v-for="(a, i) in r.answers" :key="i" class="px-3 py-2 text-ink/80">{{ a ?? '—' }}</td>
                                </tr>
                                <tr v-if="!filteredPeople.length">
                                    <td :colspan="3 + questions.length" class="px-3 py-6 text-center text-ink/50">
                                        {{ respondents.length ? 'Ninguém encontrado.' : 'Ninguém cadastrado ainda.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- zona de risco -->
                <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-white p-5">
                    <div>
                        <p class="font-semibold text-red-800">Zerar enquete</p>
                        <p class="text-sm text-ink/60">Apaga todos os cadastros e respostas e volta o telão ao convite. Baixe a planilha antes. Use no ensaio, não durante o painel.</p>
                    </div>
                    <button
                        :disabled="busy"
                        class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50"
                        @click="act('/admin/reset', {}, `Apagar ${data.registered} cadastro(s) e todas as respostas? Não dá para desfazer.`).then(() => people.refresh())"
                    >
                        Zerar tudo
                    </button>
                </section>

                <footer class="flex justify-center py-4">
                    <DevelopedBy />
                </footer>
            </main>
        </template>
    </div>
</template>
