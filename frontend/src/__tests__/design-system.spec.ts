import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

/*
 * Design system ANaTT (CLAUDE.md §4 ; revue d'octobre 2026, §4 « Design system ») :
 * - page de connexion : `flag-bar` avant le footer (règle n° 4) et année du footer
 *   calculée (jamais « © 2026 » figé) ;
 * - icônes SVG inline uniquement (règle n° 7) : la case « accordée » des matrices
 *   de permissions en lecture seule est une icône SVG `check`, pas le glyphe « ✓ ».
 */

vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ login: vi.fn(), fetchUser: vi.fn(), can: () => false }),
}))
vi.mock('@/composables/useApi', () => ({ useApi: () => ({}), getErrorMessage: () => 'Erreur' }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), show: vi.fn() }),
}))

const matrice = vi.fn()
const permissionsUtilisateur = vi.fn()
vi.mock('@/services', () => ({
  rolesService: { matrice: (...a: unknown[]) => matrice(...a) },
  usersService: { permissions: (...a: unknown[]) => permissionsUtilisateur(...a), updatePermissions: vi.fn() },
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
}

function routeur() {
  const vide = { render: () => null }
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: vide },
      { path: '/login', component: vide },
      { path: '/mot-de-passe-oublie', component: vide },
      { path: '/admin/utilisateurs', component: vide },
      { path: '/admin/utilisateurs/:id/permissions', component: vide },
    ],
  })
}

describe('LoginView — design system', () => {
  afterEach(() => vi.useRealTimers())

  async function monterLogin() {
    const router = routeur()
    await router.push('/login')
    const { default: View } = await import('../views/auth/LoginView.vue')
    return mount(View, { global: { plugins: [router] } })
  }

  it('place la barre tricolore juste avant le footer', async () => {
    const w = await monterLogin()
    const barre = w.find('.flag-bar')
    expect(barre.exists()).toBe(true)
    const suivant = barre.element.nextElementSibling
    expect(suivant?.textContent).toContain('Agence Nationale des Transports Terrestres')
  })

  it('affiche l’année courante dans le footer, jamais une année figée', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2031-03-15T09:00:00Z'))
    const w = await monterLogin()
    expect(w.text()).toContain('© 2031 ANaTT')
    expect(w.text()).not.toContain('© 2026')
  })

  it('n’utilise que des icônes SVG (aucun glyphe « ✓ »)', async () => {
    const w = await monterLogin()
    expect(w.findAll('svg').length).toBeGreaterThan(0)
    expect(w.html()).not.toContain('✓')
  })
})

describe('Matrices de permissions — case « accordée » en icône SVG', () => {
  beforeEach(() => {
    matrice.mockReset()
    permissionsUtilisateur.mockReset()
  })

  it('RolesView (lecture seule) : icône SVG check, pas de « ✓ »', async () => {
    matrice.mockResolvedValue({
      profils: [{ cle: 'DG', libelle: 'Directeur Général', systeme: true, verrouille: false, personnalise: false, nb_utilisateurs: 1 }],
      permissions: ['om.consulter', 'om.approuver'],
      matrice: { DG: ['om.approuver'] },
      non_assignables: [],
    })
    const { default: View } = await import('../views/admin/RolesView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    const accordees = w.findAll('[aria-label="Accordée"]')
    expect(accordees.length).toBeGreaterThan(0)
    for (const a of accordees) expect(a.find('svg').exists()).toBe(true)
    expect(w.html()).not.toContain('✓')
  })

  it('UserPermissionsView (lecture seule) : icône SVG check, pas de « ✓ »', async () => {
    permissionsUtilisateur.mockResolvedValue({
      user_id: 4, role: 'DG', super_admin: false,
      base: ['om.consulter', 'om.approuver'], granted: ['rapports.consulter'], revoked: [],
      effectives: ['om.consulter', 'om.approuver', 'rapports.consulter'], non_assignables: ['roles.gerer'],
    })
    const router = routeur()
    await router.push('/admin/utilisateurs/4/permissions')
    const { default: View } = await import('../views/admin/UserPermissionsView.vue')
    const w = mount(View, { global: { stubs, plugins: [router] } })
    await flushPromises()

    const accordees = w.findAll('[aria-label="Accordée"]')
    expect(accordees.length).toBe(3)
    for (const a of accordees) expect(a.find('svg').exists()).toBe(true)
    expect(w.html()).not.toContain('✓')
  })
})
