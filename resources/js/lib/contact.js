/**
 * As regras do contato, num lugar só (vieram do ROC): e-mail **ou** WhatsApp.
 * O servidor valida de novo; aqui é só para avisar antes de enviar.
 */

export const CONTACT_TYPES = [
    { key: 'email', label: 'E-mail' },
    { key: 'phone', label: 'WhatsApp' },
]

export const emailLooksValid = (value) => /^\S+@\S+\.\S+$/.test(String(value).trim())

/** 10 ou 11 dígitos: DDD + número. */
export const phoneLooksValid = (digits) => String(digits).length >= 10

export const contactLooksValid = (type, email, phone) =>
    type === 'email' ? emailLooksValid(email) : phoneLooksValid(phone)

export const contactFilled = (type, email, phone) =>
    (type === 'email' ? String(email).trim() : String(phone)).length > 0

/** Só dígitos viajam para a API; a máscara é da tela. */
export const phoneDigitsOf = (value) => String(value).replace(/\D/g, '').slice(0, 11)

export function maskPhone(digits) {
    const d = String(digits)

    if (d.length <= 2) return d
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`

    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7, 11)}`
}

/** O corpo que a API espera: só o contato escolhido, nunca os dois. */
export const contactPayload = (type, email, phone) =>
    type === 'email' ? { email: String(email).trim() } : { phone: String(phone) }
