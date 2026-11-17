/**
 * Thin fetch wrapper over the REST API.
 *
 * Every screen talks to the backend only through here, so replacing polling
 * with a websocket transport later is a change confined to this file plus
 * usePolling — no component needs to know.
 */

const BASE = '/api'

function authHeaders() {
    const headers = { Accept: 'application/json' }
    const master = masterToken.get()

    headers['X-Respondent'] = respondentId()
    if (master) headers['X-Master-Token'] = master

    return headers
}

// A versão de assets com que esta aba subiu. Se o servidor passar a responder
// outra, a SPA em memória ficou velha e precisa recarregar — senão ela
// interpreta payloads novos com código antigo e falha em silêncio.
let bootVersion = null

function checkVersion(response) {
    const version = response.headers.get('X-App-Version')

    if (!version) return

    if (bootVersion === null) {
        bootVersion = version
        return
    }

    if (version === bootVersion) return

    // guarda contra loop de reload caso duas instâncias sirvam versões
    // diferentes: recarrega no máximo uma vez a cada 30s
    const last = Number(sessionStorage.getItem('enquete:reloaded') ?? 0)

    if (Date.now() - last < 30_000) return

    sessionStorage.setItem('enquete:reloaded', String(Date.now()))
    window.location.reload()
}

async function request(method, path, body) {
    const response = await fetch(BASE + path, {
        method,
        headers: {
            ...authHeaders(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    })

    checkVersion(response)

    const payload = response.status === 204 ? null : await response.json().catch(() => null)

    if (!response.ok) {
        const error = new Error(payload?.message || `Erro ${response.status}`)
        error.status = response.status
        error.payload = payload
        throw error
    }

    return payload
}

export const api = {
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    patch: (path, body) => request('PATCH', path, body),
    delete: (path) => request('DELETE', path),
}

// localStorage pode lançar (aba anônima, dados bloqueados): nesse caso o
// aparelho ainda vota, só não lembra o id depois de recarregar.
function store(key) {
    return {
        get: () => { try { return localStorage.getItem(key) } catch { return null } },
        set: (v) => { try { localStorage.setItem(key, v) } catch { /* sem storage */ } },
        clear: () => { try { localStorage.removeItem(key) } catch { /* sem storage */ } },
    }
}

export const masterToken = store('enquete:master')

const respondentStore = store('enquete:respondent')
let memoryId = null

/**
 * O id anônimo deste aparelho: um UUID v4 gerado aqui e guardado no
 * localStorage. Não usa crypto.randomUUID porque ele só existe em contexto
 * seguro, e no ensaio pela rede local o celular abre em http://.
 */
/**
 * Troca o id deste aparelho. Acontece quando o cadastro encontra a mesma
 * pessoa já cadastrada em outro aparelho: este passa a ser ela.
 */
export function setRespondentId(id) {
    memoryId = id
    respondentStore.set(id)
}

export function respondentId() {
    let id = respondentStore.get() ?? memoryId

    if (!id) {
        const b = crypto.getRandomValues(new Uint8Array(16))
        b[6] = (b[6] & 0x0f) | 0x40
        b[8] = (b[8] & 0x3f) | 0x80
        const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('')
        id = `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
        memoryId = id
        respondentStore.set(id)
    }

    return id
}
