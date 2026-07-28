import { describe, it, expect, beforeEach } from 'vitest'

import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h, nextTick } from 'vue'
import App from '../App.vue'
import AppToast from '../components/shared/AppToast.vue'
import { useToast } from '../composables/useToast'

/*
 * Les notifications ont longtemps été empilées sans jamais être affichées :
 * aucun conteneur ne les rendait, si bien que TOUS les messages de succès et
 * d'erreur de l'application étaient invisibles. Ces tests verrouillent le
 * rendu, pour que la régression ne puisse pas repasser inaperçue.
 */

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', component: defineComponent({ render: () => h('div', 'Accueil') }) }],
})

/** Vide la pile entre deux tests (le composable est un singleton de module). */
beforeEach(() => {
  const { toasts, remove } = useToast()
  ;[...toasts.value].forEach((t) => remove(t.id))
})

describe('AppToast', () => {
  it('affiche le message empilé par le composable', async () => {
    const wrapper = mount(AppToast)

    useToast().error('Échec de la génération du fichier')
    await nextTick()

    expect(wrapper.text()).toContain('Échec de la génération du fichier')
  })

  it('distingue visuellement une erreur d’un succès', async () => {
    const wrapper = mount(AppToast)

    useToast().error('Erreur')
    await nextTick()
    expect(wrapper.html()).toContain('bg-red-50')

    useToast().success('Succès')
    await nextTick()
    expect(wrapper.html()).toContain('bg-green-50')
  })

  it('empile plusieurs notifications simultanées', async () => {
    const wrapper = mount(AppToast)
    const toast = useToast()

    toast.success('Premier')
    toast.warning('Second')
    await nextTick()

    expect(wrapper.text()).toContain('Premier')
    expect(wrapper.text()).toContain('Second')
  })

  it('ferme une notification au clic sur le bouton dédié', async () => {
    const wrapper = mount(AppToast)

    useToast().success('À fermer')
    await nextTick()
    expect(wrapper.text()).toContain('À fermer')

    await wrapper.get('button[aria-label="Fermer la notification"]').trigger('click')
    await nextTick()

    expect(wrapper.text()).not.toContain('À fermer')
  })

  it('annonce les notifications aux lecteurs d’écran', async () => {
    const wrapper = mount(AppToast)

    // aria-live polite : annoncé sans voler le focus de l'utilisateur.
    expect(wrapper.get('[role="status"]').attributes('aria-live')).toBe('polite')
  })
})

describe('App', () => {
  it('monte le conteneur de notifications à la racine', async () => {
    router.push('/')
    await router.isReady()

    const wrapper = mount(App, { global: { plugins: [router] } })

    useToast().success('Message visible depuis la racine')
    await nextTick()

    // Sans ce montage, aucun message de l'application ne serait affiché.
    expect(wrapper.findComponent(AppToast).exists()).toBe(true)
    expect(wrapper.text()).toContain('Message visible depuis la racine')
  })
})
