<script setup>
import { computed, reactive, ref } from 'vue'
import { api, respondentId, setRespondentId } from '../lib/api'
import { contactLooksValid, contactPayload } from '../lib/contact'
import ContactField from './ContactField.vue'

/**
 * Cadastro do celular: nome, e-mail ou WhatsApp, e hotel. Serve também para
 * corrigir os dados (com `me` preenchido). Rápido de propósito: a pessoa faz
 * isso em pé, no auditório, enquanto o telão ainda mostra o QR code.
 */
const props = defineProps({
    me: { type: Object, default: null },
})
const emit = defineEmits(['saved', 'cancel'])

const form = reactive({
    name: props.me?.name ?? '',
    hotel: props.me?.hotel ?? '',
    type: props.me?.phone && !props.me?.email ? 'phone' : 'email',
    email: props.me?.email ?? '',
    phone: props.me?.phone ?? '',
})
const errors = ref({})
const sending = ref(false)

const ready = computed(() =>
    form.name.trim().length > 1
    && form.hotel.trim().length > 1
    && contactLooksValid(form.type, form.email, form.phone),
)

async function submit() {
    if (!ready.value || sending.value) return
    sending.value = true
    errors.value = {}

    try {
        const res = await api.post('/register', {
            name: form.name.trim(),
            hotel: form.hotel.trim(),
            ...contactPayload(form.type, form.email, form.phone),
        })
        // o mesmo contato já estava cadastrado em outro aparelho: este passa a ser ele
        if (res.token && res.token !== respondentId()) setRespondentId(res.token)
        emit('saved', res.me)
    } catch (e) {
        errors.value = e.payload?.errors
            ? Object.fromEntries(Object.entries(e.payload.errors).map(([k, v]) => [k, v[0]]))
            : { form: 'Não conseguimos salvar. Confira a conexão e tente de novo.' }
    } finally {
        sending.value = false
    }
}

const input = 'w-full rounded-lg border-2 border-brand-200 bg-white px-3.5 py-3 text-base text-ink outline-none transition placeholder:text-ink/35 focus:border-brand-800'
</script>

<template>
    <form class="rounded-xl border border-brand-200 bg-white p-5 shadow-sm" novalidate @submit.prevent="submit">
        <h2 class="text-lg font-bold text-brand-800">{{ me ? 'Seus dados' : 'Antes de começar' }}</h2>
        <p v-if="!me" class="mt-0.5 text-sm text-ink/70">Leva 15 segundos. Depois é só responder.</p>

        <div class="mt-4 flex flex-col gap-4">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink/80" for="name">Nome</label>
                <input id="name" v-model="form.name" :class="input" maxlength="80" autocomplete="name" autocapitalize="words" placeholder="Seu nome">
                <p v-if="errors.name" class="mt-1 text-xs text-red-700">{{ errors.name }}</p>
            </div>

            <div>
                <ContactField v-model:type="form.type" v-model:email="form.email" v-model:phone="form.phone" />
                <p v-if="errors.email || errors.phone" class="mt-1 text-xs text-red-700">{{ errors.email || errors.phone }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink/80" for="hotel">Hotel</label>
                <input id="hotel" v-model="form.hotel" :class="input" maxlength="120" autocomplete="organization" autocapitalize="words" placeholder="Hotel em que você trabalha">
                <p v-if="errors.hotel" class="mt-1 text-xs text-red-700">{{ errors.hotel }}</p>
            </div>
        </div>

        <p v-if="errors.form" class="mt-4 text-sm font-semibold text-red-700">{{ errors.form }}</p>

        <button
            type="submit"
            :disabled="!ready || sending"
            class="mt-5 w-full rounded-lg bg-brand-800 py-3.5 text-base font-bold text-white transition hover:bg-brand-900 disabled:opacity-40"
        >
            {{ sending ? 'Salvando…' : me ? 'Salvar' : 'Entrar na enquete' }}
        </button>
        <button v-if="me" type="button" class="mt-2 w-full py-2 text-sm font-semibold text-brand-800" @click="emit('cancel')">
            Cancelar
        </button>

        <p class="mt-3 text-center text-xs text-ink/50">Seus dados são usados só para contato sobre este painel.</p>
    </form>
</template>
