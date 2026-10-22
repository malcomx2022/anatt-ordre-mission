import { describe, it, expect } from 'vitest'

import { categorieDeduite, moisLocal } from '../utils/benin'

/*
 * Miroirs frontend des règles du référentiel agents : la catégorie suggérée
 * pilote le formulaire agent (et donc le per diem), le mois courant pilote le
 * tableau de bord et le rapport mensuel.
 */

describe('categorieDeduite', () => {
  it('suit les seuils d’indice, prioritaires sur le salaire', () => {
    expect(categorieDeduite(800, 100000)).toBe('INDICE_SUP_800')
    expect(categorieDeduite(400, null)).toBe('INDICE_400_799')
    expect(categorieDeduite(399, null)).toBe('INDICE_INF_400')
  })

  it('ignore un champ indice vidé (v-model.number donne « » et non null)', () => {
    // Avant : '' passait le test « != null » et donnait INDICE_INF_400 —
    // un agent à 250 000 FCFA était enregistré sous la mauvaise catégorie.
    // @ts-expect-error valeur réelle d'un <input type=number> vidé
    expect(categorieDeduite('', 250000)).toBe('INDICE_400_799')
    // @ts-expect-error idem pour le salaire
    expect(categorieDeduite(null, '')).toBeNull()
  })
})

describe('moisLocal', () => {
  it('renvoie le mois à l’heure locale, pas en UTC', () => {
    // Minuit et demie le 1er octobre, heure locale : toISOString() donnerait
    // « 2026-09 » sur un poste en UTC+1 (Bénin).
    expect(moisLocal(new Date(2026, 9, 1, 0, 30))).toBe('2026-10')
    expect(moisLocal(new Date(2026, 0, 31, 23, 59))).toBe('2026-01')
  })
})
