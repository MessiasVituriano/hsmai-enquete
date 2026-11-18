<script setup>
import { computed } from 'vue'
import { api } from '../lib/api'
import { usePolling } from '../composables/usePolling'
import ScaledStage from '../components/ScaledStage.vue'
import RichText from '../components/RichText.vue'
import ResultBars from '../components/ResultBars.vue'
import JoinQrCode from '../components/JoinQrCode.vue'
import BrandImage from '../components/BrandImage.vue'
import DevelopedBy from '../components/DevelopedBy.vue'

/**
 * O telão. Nunca mostra a lista inteira: uma coisa por vez, trocada pelo painel.
 *   invite   — o QR code, para a sala entrar
 *   question — a pergunta N com as alternativas e as respostas chegando
 *   results  — o resultado da pergunta N (o fecho do painel)
 * Duplo clique alterna a tela cheia.
 */
const { data } = usePolling(() => api.get('/display'), { interval: 1000 })

const current = computed(() => data.value?.questions?.[0] ?? null)
const screen = computed(() => {
    const d = data.value
    if (!d) return null
    if (d.screen === 'results' && current.value?.results) return 'results'
    if (d.screen === 'question' && current.value) return 'question'
    return 'invite'
})
// no convite conta quem já entrou (cadastrou), não só quem respondeu: é o
// número que sobe enquanto a sala escaneia o QR
const respondentsLabel = computed(() => {
    const n = data.value?.registered ?? 0
    return `${n} ${n === 1 ? 'pessoa conectada' : 'pessoas conectadas'}`
})
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`
const LETTERS = 'ABCDEFGH'

function toggleFullscreen() {
    if (document.fullscreenElement) document.exitFullscreen()
    else document.documentElement.requestFullscreen?.()
}
</script>

<template>
    <ScaledStage>
        <div v-if="data" class="relative flex h-full w-full flex-col bg-brand-100" @dblclick="toggleFullscreen">
            <!-- cabeçalho -->
            <header class="relative px-[100px] pt-[64px]">
                <h1 class="text-[62px] font-bold leading-none tracking-[-0.01em] text-brand-800">{{ data.title }}</h1>
                <p class="mt-[22px] text-[31px] leading-tight text-ink/80">
                    <template v-if="screen === 'question'">
                        <strong class="font-bold text-brand-800">Pergunta {{ current.number }} de {{ data.total_questions }}</strong>
                        · responda pelo celular
                    </template>
                    <template v-else-if="screen === 'results'">
                        <strong class="font-bold text-brand-800">Resultado da pergunta {{ current.number }}</strong>
                        · {{ plural(current.results.total, 'resposta', 'respostas') }}
                    </template>
                    <RichText v-else :text="data.subtitle" strong-class="font-bold text-brand-900" />
                </p>
                <BrandImage :src="data.logos?.header" alt="" class="absolute right-[64px] top-[44px] max-h-[150px] max-w-[260px] object-contain" />
            </header>

            <!-- conteúdo -->
            <main class="min-h-0 flex-1 px-[100px] pb-[40px] pt-[44px]">
                <Transition name="fade" mode="out-in">
                    <!-- convite: só o QR -->
                    <div v-if="screen === 'invite'" key="invite" class="flex h-full items-center gap-[90px]">
                        <div class="shrink-0 rounded-[24px] border-2 border-brand-200 bg-white p-[30px] shadow-xl">
                            <JoinQrCode :size="560" />
                        </div>
                        <div class="flex flex-col">
                            <p class="text-[58px] font-bold leading-[1.1] text-brand-800">
                                Aponte a câmera do celular para o QR code
                            </p>
                            <p class="mt-[26px] text-[32px] leading-snug text-ink/80">
                                As perguntas aparecem aqui e no seu celular, uma de cada vez.
                            </p>
                            <template v-if="data.accepting">
                                <RichText tag="p" :text="data.call_to_action" class="mt-[34px] text-[36px] font-semibold text-ink" strong-class="font-bold text-accent-600" />
                                <p class="mt-[6px] text-[28px] text-ink/70">{{ data.call_to_action_note }}</p>
                            </template>
                            <p v-else class="mt-[34px] text-[36px] font-semibold text-ink">Enquete encerrada</p>
                            <p class="mt-[36px] self-start rounded-full bg-brand-800 px-[26px] py-[10px] text-[24px] font-semibold tabular-nums text-white">
                                {{ respondentsLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- a pergunta sendo respondida -->
                    <div v-else-if="screen === 'question'" :key="'q' + current.id" class="flex h-full gap-[42px]">
                        <section class="flex w-[1165px] flex-col rounded-[14px] border-2 border-brand-200 bg-white px-[52px] py-[44px]">
                            <div class="flex items-start gap-[30px]">
                                <span class="grid size-[80px] shrink-0 place-items-center rounded-full bg-brand-800 text-[38px] font-bold text-white">
                                    {{ current.number }}
                                </span>
                                <RichText tag="p" :text="current.prompt" class="pt-[6px] text-[38px] leading-[1.3] text-ink" strong-class="font-bold text-brand-900" />
                            </div>
                            <ul
                                class="mt-auto grid grid-cols-2 gap-[18px] pt-[30px]"
                                
                            >
                                <li
                                    v-for="(o, i) in current.options"
                                    :key="o.id"
                                    class="flex items-center gap-[18px] rounded-[12px] border-2 border-brand-200 bg-brand-50 px-[24px] py-[20px]"
                                >
                                    <span class="grid size-[48px] shrink-0 place-items-center rounded-full bg-brand-200 text-[24px] font-bold text-brand-800">{{ LETTERS[i] }}</span>
                                    <span class="text-[30px] font-semibold leading-tight text-brand-900">{{ o.label }}</span>
                                </li>
                            </ul>
                        </section>

                        <aside class="flex flex-1 flex-col items-center justify-center rounded-[22px] bg-brand-800 px-[40px] text-center text-white">
                            <p class="text-[120px] font-bold leading-none tabular-nums text-accent-500">{{ current.answers }}</p>
                            <p class="mt-[10px] text-[30px] font-semibold">{{ current.answers === 1 ? 'resposta' : 'respostas' }}</p>
                            <template v-if="data.accepting">
                                <div class="mt-[40px] rounded-[12px] bg-white p-[14px]">
                                    <JoinQrCode :size="210" />
                                </div>
                                <RichText tag="p" :text="data.call_to_action" class="mt-[16px] text-[24px] text-white/90" strong-class="font-bold text-accent-500" />
                            </template>
                            <p v-else class="mt-[30px] text-[26px] text-white/85">Respostas encerradas</p>
                        </aside>
                    </div>

                    <!-- o resultado de uma pergunta -->
                    <div v-else-if="screen === 'results'" :key="'r' + current.id" class="flex h-full gap-[42px]">
                        <section class="flex w-[1165px] flex-col rounded-[14px] border-2 border-brand-200 bg-white px-[48px] py-[40px]">
                            <div class="flex items-start gap-[30px]">
                                <span class="grid size-[72px] shrink-0 place-items-center rounded-full bg-brand-800 text-[34px] font-bold text-white">
                                    {{ current.number }}
                                </span>
                                <RichText tag="p" :text="current.prompt" class="pt-[6px] text-[30px] leading-[1.35] text-ink" strong-class="font-bold text-brand-900" />
                            </div>
                            <div class="flex flex-1 flex-col justify-center pt-[24px]">
                                <ResultBars
                                    :options="current.options"
                                    :results="current.results"
                                    :size="current.options.length > 2 ? 'lg' : 'xl'"
                                />
                            </div>
                        </section>

                        <aside class="flex flex-1 flex-col items-center justify-center rounded-[22px] bg-brand-800 px-[40px] text-center text-white">
                            <p class="text-[26px] font-semibold uppercase tracking-[0.14em] text-white/70">Resultado</p>
                            <p class="mt-[18px] text-[120px] font-bold leading-none tabular-nums text-accent-500">{{ current.results.total }}</p>
                            <p class="mt-[10px] text-[30px] font-semibold">{{ current.results.total === 1 ? 'resposta' : 'respostas' }}</p>
                            <p class="mt-[40px] text-[24px] text-white/75">Pergunta {{ current.number }} de {{ data.total_questions }}</p>
                        </aside>
                    </div>
                </Transition>
            </main>

            <!-- rodapé -->
            <footer class="h-[136px] shrink-0 border-t-[6px] border-accent-500 bg-brand-800">
                <div class="flex h-full items-center gap-[64px] px-[100px]">
                    <BrandImage :src="data.logos?.footer" alt="" class="max-h-[72px] max-w-[300px] object-contain" />
                    <p class="text-[36px] font-bold text-white">{{ data.event_name }}</p>
                    <DevelopedBy tone="dark" size="stage" class="ml-auto" />
                </div>
            </footer>
        </div>
    </ScaledStage>
</template>
