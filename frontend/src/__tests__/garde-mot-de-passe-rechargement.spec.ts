import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

/*
 * Garde de route au rechargement (revue d'octobre 2026, §4) : une page rechargée
 * avec un jeton dont le compte doit changer son mot de passe (le backend répond 423
 * PASSWORD_CHANGE_REQUIRED à toute route hors /auth/me, /auth/me/permissions,
 * /auth/logout et /auth/mot-de-passe/changer) doit mener à l'écran de changement
 * imposé. Avant le correctif, la session restaurée sautait directement au contrôle
 * de permission : l'écran demandé s'ouvrait et chacune de ses requêtes tombait en 423.
 */

const { me, mePermissions, logout, chargerPartAvance } = vi.hoisted(() => ({
  me: vi.fn(), mePermissions: vi.fn(), logout: vi.fn(), chargerPartAvance: vi.fn(),
}))
vi.mock('@/services/auth', () => ({ authService: { me, mePermissions, logout } }))
vi.mock('@/composables/usePartAvance', () => ({ chargerPartAvance, reinitialiser: vi.fn() }))
vi.mock('@/composables/useApplication', () => ({ chargerApplication: vi.fn() }))
vi.mock('@/stores/notifications', () => ({ useNotificationsStore: () => ({ vider: vi.fn() }) }))

const utilisateur = (mustChange: boolean) => ({
  id: 3, name: 'Awa Dossou', email: 'gestion@anatt.bj', role: 'GESTIONNAIRE_OM', actif: true,
  must_change_password: mustChange,
})

beforeEach(() => {
  setActivePinia(createPinia())
  for (const f of [me, mePermissions, logout, chargerPartAvance]) f.mockReset()
  // Toute autre lecture que /auth/me* répondrait 423 tant que le mot de passe n'est pas changé.
  chargerPartAvance.mockRejectedValue({ isAxiosError: true, response: { status: 423, data: {} } })
  localStorage.setItem('auth_token', 'jeton-temporaire')
  mePermissions.mockResolvedValue(['dashboard.consulter', 'om.consulter', 'om.retranscrire'])
})

describe('rechargement d’une page — mot de passe à changer', () => {
  it('redirige vers le changement de mot de passe imposé au lieu de l’écran demandé', async () => {
    me.mockResolvedValue(utilisateur(true))
    const { default: router } = await import('@/router')

    await router.push('/ordres?statut=VALIDE')

    expect(me).toHaveBeenCalledTimes(1) // session restaurée depuis le jeton
    expect(router.currentRoute.value.name).toBe('force-password-change')
    // Aucune lecture vouée au 423 (part d'avance) avant le changement.
    expect(chargerPartAvance).not.toHaveBeenCalled()
    expect(localStorage.getItem('auth_token')).toBe('jeton-temporaire')
  })

  it('ouvre normalement l’écran demandé quand le mot de passe n’est pas à changer (témoin)', async () => {
    me.mockResolvedValue(utilisateur(false))
    chargerPartAvance.mockResolvedValue(70)
    const { default: router } = await import('@/router')

    await router.push('/ordres?statut=VALIDE')

    expect(router.currentRoute.value.fullPath).toBe('/ordres?statut=VALIDE')
    expect(chargerPartAvance).toHaveBeenCalled()
  })
})
