import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

/*
 * Part de l'avance : un échec de lecture (ex. 423 « mot de passe à changer »)
 * ne doit PAS figer le défaut 70 % en cache pour toute la session ; la vraie
 * valeur serveur est relue au prochain appel — notamment après le changement
 * de mot de passe imposé (auth.fetchUser).
 */

const get = vi.fn()
vi.mock('@/services/http', () => ({ api: { get: (...a: unknown[]) => get(...a) } }))

const me = vi.fn()
const mePermissions = vi.fn()
const login = vi.fn()
vi.mock('@/services/auth', () => ({
  authService: {
    me: (...a: unknown[]) => me(...a),
    mePermissions: (...a: unknown[]) => mePermissions(...a),
    login: (...a: unknown[]) => login(...a),
    logout: vi.fn(),
  },
}))
vi.mock('@/stores/notifications', () => ({ useNotificationsStore: () => ({ vider: vi.fn() }) }))

const erreur423 = Object.assign(new Error('Changement de mot de passe requis'), { response: { status: 423 } })

beforeEach(async () => {
  get.mockReset()
  me.mockReset()
  mePermissions.mockReset()
  login.mockReset()
  setActivePinia(createPinia())
  const { reinitialiser } = await import('../composables/usePartAvance')
  reinitialiser()
})

describe('chargerPartAvance', () => {
  it('ne met pas en cache le défaut en cas d’erreur et relit la valeur au prochain appel', async () => {
    const { chargerPartAvance, usePartAvance } = await import('../composables/usePartAvance')
    const { partAvance } = usePartAvance()

    get.mockRejectedValueOnce(erreur423)
    expect(await chargerPartAvance()).toBe(70)
    expect(partAvance.value).toBe(70)

    get.mockResolvedValueOnce({ data: { data: 60 } })
    expect(await chargerPartAvance()).toBe(60)
    expect(partAvance.value).toBe(60)
    expect(get).toHaveBeenCalledTimes(2)

    // Succès : mis en cache, plus de requête.
    expect(await chargerPartAvance()).toBe(60)
    expect(get).toHaveBeenCalledTimes(2)
  })
})

describe('auth store et mot de passe imposé', () => {
  it('ne charge pas la part pendant le changement imposé, puis la relit après fetchUser', async () => {
    const { useAuthStore } = await import('../stores/auth')
    const { usePartAvance } = await import('../composables/usePartAvance')
    const { partAvance } = usePartAvance()
    const auth = useAuthStore()

    login.mockResolvedValue({ token: 'tok', user: { id: 1, role: 'LECTEUR', must_change_password: true }, permissions: [] })
    await auth.login('a@anatt.bj', 'password')
    expect(get).not.toHaveBeenCalled()

    // Mot de passe changé : le profil revient sans must_change_password.
    me.mockResolvedValue({ id: 1, role: 'LECTEUR', must_change_password: false })
    mePermissions.mockResolvedValue([])
    get.mockResolvedValue({ data: { data: 55 } })
    await auth.fetchUser()
    await vi.waitFor(() => expect(partAvance.value).toBe(55))
    expect(get).toHaveBeenCalledWith('/api/admin/parametres/part-avance')
  })
})
