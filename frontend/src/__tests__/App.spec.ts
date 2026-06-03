import { describe, it, expect } from 'vitest'

import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent, h } from 'vue'
import App from '../App.vue'

// App.vue ne contient qu'un <RouterView /> : on le monte avec un routeur
// mémoire et une route minimale pour vérifier qu'il rend la vue active.
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    {
      path: '/',
      component: defineComponent({ render: () => h('div', 'Accueil ANaTT') }),
    },
  ],
})

describe('App', () => {
  it('monte le composant racine et rend la vue routée', async () => {
    router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
      global: { plugins: [router] },
    })

    expect(wrapper.html()).toContain('Accueil ANaTT')
  })
})
