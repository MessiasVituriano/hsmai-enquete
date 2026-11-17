<script setup>
import { computed } from 'vue'
import {
    CONTACT_TYPES,
    contactFilled,
    contactLooksValid,
    emailLooksValid,
    maskPhone,
    phoneDigitsOf,
    phoneLooksValid,
} from '../lib/contact'

/**
 * E-mail ou WhatsApp num campo só, com o tipo escolhido no alternador.
 * Os dois valores ficam guardados separados: alternar e voltar não apaga o
 * que já foi digitado. (Adaptado do ROC para o tema claro.)
 */
const type = defineModel('type', { type: String, required: true })
const email = defineModel('email', { type: String, required: true })
const phone = defineModel('phone', { type: String, required: true })

const masked = computed(() => maskPhone(phone.value))
const invalid = computed(
    () => contactFilled(type.value, email.value, phone.value)
        && !contactLooksValid(type.value, email.value, phone.value),
)
const input = 'w-full rounded-lg border-2 bg-white px-3.5 py-3 text-base text-ink outline-none transition placeholder:text-ink/35'
</script>

<template>
    <div>
        <div class="mb-1.5 flex items-center gap-2">
            <label class="text-sm font-semibold text-ink/80" for="contact">Contato</label>
            <div class="ml-auto flex gap-1 rounded-lg bg-brand-100 p-1" role="group" aria-label="Tipo de contato">
                <button
                    v-for="option in CONTACT_TYPES"
                    :key="option.key"
                    type="button"
                    class="rounded-md px-3 py-1 text-xs font-bold transition"
                    :class="type === option.key ? 'bg-brand-800 text-white' : 'text-brand-800 hover:bg-white'"
                    :aria-pressed="type === option.key"
                    @click="type = option.key"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <input
            v-if="type === 'email'"
            id="contact"
            v-model="email"
            type="email"
            maxlength="120"
            inputmode="email"
            autocapitalize="off"
            autocomplete="email"
            placeholder="voce@hotel.com.br"
            :class="[input, email && !emailLooksValid(email) ? 'border-red-400' : 'border-brand-200 focus:border-brand-800']"
        >
        <input
            v-else
            id="contact"
            :value="masked"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="(11) 99999-9999"
            :class="[input, phone && !phoneLooksValid(phone) ? 'border-red-400' : 'border-brand-200 focus:border-brand-800']"
            @input="phone = phoneDigitsOf($event.target.value)"
        >
        <p v-if="invalid" class="mt-1 text-xs text-red-700">
            {{ type === 'email' ? 'Informe um e-mail válido.' : 'Informe o DDD e o número.' }}
        </p>
    </div>
</template>
