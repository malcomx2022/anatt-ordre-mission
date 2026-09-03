import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AppCombobox from '../components/shared/AppCombobox.vue'
import type { ComboOption } from '../components/shared/combobox'

/*
 * AppCombobox : sélecteur recherchable pour les listes longues.
 * On vérifie les deux modes (local / distant), le filtrage, la sélection et
 * l'effacement — le cœur de la remédiation des <select> volumineux.
 */

const OPTIONS: ComboOption[] = [
  { value: 1, label: 'Alpha', hint: 'A' },
  { value: 2, label: 'Beta', hint: 'B' },
  { value: 3, label: 'Gamma', hint: 'C' },
]

async function ouvrir(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('button').trigger('click') // bouton d'ouverture (aucune valeur)
  await flushPromises()
}

/** Attend le minuteur de debounce (macrotâche) PUIS la résolution du fetcher. */
async function attendreDebounce() {
  await new Promise((r) => setTimeout(r, 5))
  await flushPromises()
}

describe('AppCombobox — mode local', () => {
  it('ouvre, affiche toutes les options puis filtre à la frappe', async () => {
    const wrapper = mount(AppCombobox, { props: { modelValue: null, options: OPTIONS } })

    await ouvrir(wrapper)
    expect(wrapper.findAll('li[role="option"]')).toHaveLength(3)

    await wrapper.get('input[role="combobox"]').setValue('be')
    await flushPromises()

    const items = wrapper.findAll('li[role="option"]')
    expect(items).toHaveLength(1)
    expect(items[0]!.text()).toContain('Beta')
  })

  it('émet la valeur choisie et la met en évidence', async () => {
    const wrapper = mount(AppCombobox, { props: { modelValue: null, options: OPTIONS } })
    await ouvrir(wrapper)

    await wrapper.findAll('li[role="option"]')[2]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([3])
    expect(wrapper.emitted('change')?.[0]).toEqual([OPTIONS[2]])
  })

  it('filtre aussi sur l’indice (hint)', async () => {
    const wrapper = mount(AppCombobox, { props: { modelValue: null, options: OPTIONS } })
    await ouvrir(wrapper)

    await wrapper.get('input[role="combobox"]').setValue('C')
    await flushPromises()

    const items = wrapper.findAll('li[role="option"]')
    expect(items).toHaveLength(1)
    expect(items[0]!.text()).toContain('Gamma')
  })
})

describe('AppCombobox — mode distant', () => {
  it('appelle le fetcher débouncé et sélectionne un résultat', async () => {
    const fetcher = vi.fn(async (q: string): Promise<ComboOption[]> => [
      { value: 9, label: `Mission ${q.toUpperCase()}` },
    ])
    const wrapper = mount(AppCombobox, {
      props: { modelValue: null, fetcher, debounce: 0, minChars: 1 },
    })

    await ouvrir(wrapper)
    await wrapper.get('input[role="combobox"]').setValue('x')
    await attendreDebounce()

    expect(fetcher).toHaveBeenCalledWith('x')
    const items = wrapper.findAll('li[role="option"]')
    expect(items).toHaveLength(1)

    await items[0]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([9])
  })

  it('exige le nombre minimum de caractères avant d’appeler le fetcher', async () => {
    const fetcher = vi.fn(async (): Promise<ComboOption[]> => [])
    const wrapper = mount(AppCombobox, {
      props: { modelValue: null, fetcher, debounce: 0, minChars: 2 },
    })

    await ouvrir(wrapper)
    await wrapper.get('input[role="combobox"]').setValue('a')
    await attendreDebounce()

    expect(fetcher).not.toHaveBeenCalled()
  })
})
