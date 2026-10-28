import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'

/*
 * Session restaurée au rechargement d'une page : une erreur PASSAGÈRE de
 * /auth/me (429 au-delà de 60 requêtes/min, 500, réseau, 503) ne doit pas
 * déconnecter l'utilisateur. Avant, fetchUser() appelait logout() sur toute
 * erreur — qui RÉVOQUE le jeton côté serveur : la session était perdue pour
 * de bon. Seul un 401 (jeton expiré ou révoqué) met fin à la session.
 */

const { me, mePermissions, logout } = vi.hoisted(() => ({
  me: vi.fn(), mePermissions: vi.fn(), logout: vi.fn(),
}))
vi.mock('@/services/auth', () => ({ authService: { me, mePermissions, logout } }))
vi.mock('@/composables/usePartAvance', () => ({ chargerPartAvance: vi.fn(), reinitialiser: vi.fn() }))
vi.mock('@/composables/useApplication', () => ({ chargerApplication: vi.fn() }))
vi.mock('@/stores/notifications', () => ({ useNotificationsStore: () => ({ vider: vi.fn() }) }))

const erreur = (status?: number) => ({ isAxiosError: true, response: status ? { status, data: {} } : undefined })

beforeEach(() => {
  setActivePinia(createPinia())
  me.mockReset(); mePermissions.mockReset(); logout.mockReset()
  localStorage.setItem('auth_token', 'jeton-valide')
  mePermissions.mockResolvedValue([])
})

describe('fetchUser — erreur passagère', () => {
  it.each([
    ['limite de requêtes (429)', erreur(429)],
    ['erreur serveur (500)', erreur(500)],
    ['maintenance (503)', erreur(503)],
    ['réseau injoignable', erreur()],
  ])('garde la session et son jeton : %s', async (_cas, e) => {
    const { useAuthStore } = await import('@/stores/auth')
    me.mockRejectedValue(e)
    const auth = useAuthStore()

    await expect(auth.fetchUser()).rejects.toBe(e)
    expect(logout).not.toHaveBeenCalled() // le jeton n'est pas révoqué
    expect(auth.token).toBe('jeton-valide')
    expect(localStorage.getItem('auth_token')).toBe('jeton-valide')
  })

  it('met fin à la session sur un 401 (jeton expiré ou révoqué)', async () => {
    const { useAuthStore } = await import('@/stores/auth')
    me.mockRejectedValue(erreur(401))
    const auth = useAuthStore()

    await auth.fetchUser()
    expect(auth.token).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })
})

describe('garde de navigation — erreur passagère au rechargement', () => {
  it('ouvre l’écran d’erreur en gardant la page demandée, pas la connexion', async () => {
    const { default: router } = await import('@/router')
    me.mockRejectedValue(erreur(429))

    await router.push('/ordres?statut=VALIDE')
    expect(router.currentRoute.value.name).toBe('server-error')
    expect(router.currentRoute.value.query.redirect).toBe('/ordres?statut=VALIDE')
    expect(localStorage.getItem('auth_token')).toBe('jeton-valide')
  })

  it('renvoie à la connexion quand la session est réellement expirée', async () => {
    const { default: router } = await import('@/router')
    me.mockRejectedValue(erreur(401))

    await router.push('/paiements')
    expect(router.currentRoute.value.name).toBe('login')
  })
})

describe('ServerErrorView — « Réessayer »', () => {
  it('revient à la page demandée plutôt que de recharger /500', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign, reload: vi.fn() })
    const { default: router } = await import('@/router')
    await router.push('/500?redirect=' + encodeURIComponent('/ordres?statut=VALIDE'))
    const { default: View } = await import('../views/system/ServerErrorView.vue')
    const w = mount(View, { global: { plugins: [router] } })

    await w.findAll('button').find((b) => b.text().includes('Réessayer'))!.trigger('click')
    expect(assign).toHaveBeenCalledWith('/ordres?statut=VALIDE')
    vi.unstubAllGlobals()
  })
})
