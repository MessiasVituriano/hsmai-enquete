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
 * O telão. Três telas, trocadas pelo painel:
 *   invite   — o slide da enquete: as quatro perguntas e o QR code
 *   question — o resultado de uma pergunta, ao vivo
 *   all      — as quatro lado a lado, para o fecho do painel
 * Duplo clique alterna a tela cheia.
 */
const { data } = usePolling(() => api.get('/display'), { interval: 1000 })

const questions = computed(() => data.value?.questions ?? [])
const current = computed(() => {
    const d = data.value
    if (!d) return null
    if (d.screen === 'question') {
        const q = questions.value.find((x) => x.id === d.screen_question_id)
        if (q?.results) return q
    }
    return null
})
const screen = computed(() => {
    const d = data.value
    if (!d) return null
    if (d.screen === 'question') return current.value ? 'question' : 'invite'
    return d.screen
})
const respondentsLabel = computed(() => {
    const n = data.value?.respondents ?? 0
    return `${n} ${n === 1 ? 'pessoa respondeu' : 'pessoas responderam'}`
})

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
                        Resultado da <strong class="font-bold text-brand-800">pergunta {{ current.number }}</strong>
                        · {{ current.results.total }} {{ current.results.total === 1 ? 'resposta' : 'respostas' }}
                    </template>
                    <template v-else-if="screen === 'all'">
                        <strong class="font-bold text-brand-800">Como a sala respondeu</strong> · {{ respondentsLabel }}
                    </template>
                    <RichText v-else :text="data.subtitle" strong-class="font-bold text-brand-900" />
                </p>
                <BrandImage :src="data.logos?.header" alt="" class="absolute right-[64px] top-[44px] max-h-[150px] max-w-[260px] object-contain" />
            </header>

            <!-- conteúdo -->
            <main class="min-h-0 flex-1 px-[100px] pb-[40px] pt-[44px]">
                <Transition name="fade" mode="out-in">
                    <!-- convite: o slide da enquete -->
                    <div v-if="screen === 'invite'" key="invite" class="flex h-full gap-[42px]">
                        <ol class="flex w-[1165px] flex-col justify-center gap-[22px]">
                            <li
                                v-for="q in questions"
                                :key="q.id"
                                class="flex items-center gap-[34px] rounded-[10px] border-2 border-brand-200 bg-white px-[30px] py-[24px]"
                            >
                                <span class="grid size-[66px] shrink-0 place-items-center rounded-full bg-brand-800 text-[30px] font-bold text-white">
                                    {{ q.number }}
                                </span>
                                <div class="text-[27px] leading-[1.35] text-ink">
                                    <RichText tag="p" :text="q.prompt" strong-class="font-bold text-brand-900" />
                                    <p v-if="q.inline_options" class="font-bold text-brand-900">
                                        {{ q.options.map((o) => o.label).join(' · ') }}
                                    </p>
                                </div>
                            </li>
                        </ol>

                        <aside class="flex flex-1 flex-col items-center justify-center rounded-[22px] bg-brand-800 px-[40px] text-center text-white">
                            <div class="rounded-[14px] bg-white p-[22px] shadow-xl">
                                <JoinQrCode :size="330" />
                            </div>
                            <template v-if="data.accepting">
                                <RichText tag="p" :text="data.call_to_action" class="mt-[34px] text-[31px] font-semibold" strong-class="font-bold text-accent-500" />
                                <p class="mt-[8px] text-[27px] text-white/90">{{ data.call_to_action_note }}</p>
                            </template>
                            <p v-else class="mt-[34px] text-[31px] font-semibold">Enquete encerrada</p>
                            <p class="mt-[26px] rounded-full bg-white/10 px-[22px] py-[8px] text-[22px] font-semibold tabular-nums text-white/85">
                                {{ respondentsLabel }}
                            </p>
                        </aside>
                    </div>

                    <!-- resultado de uma pergunta -->
                    <div v-else-if="screen === 'question'" :key="'q' + current.id" class="flex h-full gap-[42px]">
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
                            <p class="text-[120px] font-bold leading-none tabular-nums text-accent-500">{{ current.results.total }}</p>
                            <p class="mt-[10px] text-[30px] font-semibold">{{ current.results.total === 1 ? 'resposta' : 'respostas' }}</p>
                            <template v-if="data.accepting">
                                <div class="mt-[44px] rounded-[12px] bg-white p-[14px]">
                                    <JoinQrCode :size="190" />
                                </div>
                                <p class="mt-[16px] text-[24px] text-white/85">Ainda dá tempo de responder</p>
                            </template>
                        </aside>
                    </div>

                    <!-- as quatro lado a lado -->
                    <div v-else-if="screen === 'all'" key="all" class="grid h-full grid-cols-4 gap-[28px]">
                        <section
                            v-for="q in questions"
                            :key="q.id"
                            class="flex flex-col rounded-[14px] border-2 border-brand-200 bg-white px-[28px] py-[28px]"
                        >
                            <span class="grid size-[54px] place-items-center rounded-full bg-brand-800 text-[26px] font-bold text-white">{{ q.number }}</span>
                            <RichText tag="p" :text="q.prompt" class="mt-[20px] text-[24px] leading-[1.35] text-ink" strong-class="font-bold text-brand-900" />
                            <div class="mt-auto pt-[22px]">
                                <ResultBars v-if="q.results" :options="q.options" :results="q.results" size="stage" />
                                <p v-else class="text-[22px] text-ink/50">Aguardando…</p>
                            </div>
                        </section>
                    </div>
                </Transition>
            </main>

            <!-- rodapé -->
            <footer class="h-[118px] shrink-0 border-t-[6px] border-accent-500 bg-brand-800">
                <div class="flex h-full items-center gap-[64px] px-[100px]">
                    <BrandImage :src="data.logos?.footer" alt="" class="max-h-[72px] max-w-[300px] object-contain" />
                    <p class="text-[36px] font-bold text-white">{{ data.event_name }}</p>
                    <DevelopedBy tone="dark" size="stage" class="ml-auto" />
                </div>
            </footer>
        </div>
    </ScaledStage>
</template>
