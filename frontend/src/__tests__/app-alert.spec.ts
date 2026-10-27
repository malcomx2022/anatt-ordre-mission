import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppAlert from '@/components/shared/AppAlert.vue'

/*
 * Le conteneur d'AppAlert est en flex : un texte brut entrecoupé de <strong>
 * devenait une suite de colonnes (bandeau de l'écran Signatures). Le contenu
 * du slot doit former un seul bloc, où le texte s'enchaîne normalement.
 */
describe('AppAlert', () => {
  it('rend le contenu du slot dans un seul bloc, même du texte entrecoupé de <strong>', () => {
    const wrapper = mount(AppAlert, {
      props: { type: 'info' },
      slots: { default: 'Ces identités sont celles des <strong>titulaires en poste</strong>. Elles sont imprimées.' },
    })

    const enfants = wrapper.element.children
    expect(enfants).toHaveLength(1)
    expect(enfants[0].querySelector('strong')?.textContent).toBe('titulaires en poste')
    expect(enfants[0].textContent).toContain('Ces identités sont celles des titulaires en poste. Elles sont imprimées.')
  })
})
