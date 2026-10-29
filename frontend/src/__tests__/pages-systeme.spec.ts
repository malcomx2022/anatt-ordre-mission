import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Pages système (404, 403, 500, maintenance) : textes accentués et raccourcis lisibles.
 * Relevé à la recapture de la galerie (29/10/2026) : « Page precedente », « Retour a
 * l'accueil », « Incident en cours de resolution », un raccourci « Dashboard » en anglais
 * et, sur la 404, une même icône tronquée (un carré) pour les trois raccourcis.
 */

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, fullPath: '/page-inexistante' }),
  useRouter: () => ({ back: vi.fn(), push: vi.fn() }),
}))

const stubs = {
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter(fichier: string) {
  const { default: View } = await import(`../views/system/${fichier}.vue`)
  return mount(View, { global: { stubs, mocks: { $route: { fullPath: '/page-inexistante', query: {} } } } })
}

describe('Pages système', () => {
  it.each(['NotFoundView', 'ForbiddenView', 'ServerErrorView'])('%s : libellés en français, accentués', async (vue) => {
    const texte = (await monter(vue)).text()
    expect(texte).toContain('Tableau de bord')
    expect(texte).not.toContain('Dashboard')
    expect(texte).not.toMatch(/precedente|Retour a l|resolution|verrouille|non trouvee|a ete/)
  })

  it('404 : chaque raccourci porte sa propre icône', async () => {
    const w = await monter('NotFoundView')
    const icones = w.findAll('.grid a svg').map((s) => s.html()) // les trois cartes de raccourci
    expect(icones).toHaveLength(3)
    expect(new Set(icones).size).toBe(3)
  })
})
