import { describe, it, expect } from 'vitest'

import { PROFILS, badgeDeRole } from '../utils/roles'
import { ROLE_LABELS } from '../types'
import { ROLE_PERMISSIONS } from '../utils/permissions'
import type { UserRole } from '../types'

/*
 * Les écrans d'administration recopiaient chacun leur liste de profils, et les
 * deux avaient oublié DAF et DG : les deux signataires du circuit étaient
 * incréables depuis l'interface, alors que l'API les accepte. Ce test refuse
 * qu'un profil déclaré côté RBAC disparaisse à nouveau du catalogue.
 */

describe('catalogue des profils', () => {
  it('propose tous les profils du RBAC', () => {
    const attendus = Object.keys(ROLE_PERMISSIONS) as UserRole[]
    const proposes = PROFILS.map((p) => p.value)

    expect([...proposes].sort()).toEqual([...attendus].sort())
  })

  it('inclut nommément les deux signataires du circuit', () => {
    const proposes = PROFILS.map((p) => p.value)

    expect(proposes).toContain('DAF')
    expect(proposes).toContain('DG')
  })

  it('reprend les libellés officiels', () => {
    for (const profil of PROFILS) {
      expect(profil.label).toBe(ROLE_LABELS[profil.value])
    }
  })

  it('décrit chaque profil et lui donne des couleurs distinctes', () => {
    for (const profil of PROFILS) {
      expect(profil.desc.length).toBeGreaterThan(10)
      expect(profil.perms.length).toBeGreaterThan(0)
    }

    const badges = PROFILS.map((p) => p.badge)
    expect(new Set(badges).size).toBe(badges.length)
  })

  it('écrit les classes Tailwind en toutes lettres', () => {
    // Une classe interpolée (`bg-${couleur}-100`) n'est jamais générée par le
    // compilateur : les pastilles s'affichaient sans couleur.
    for (const profil of PROFILS) {
      expect(profil.badge).toMatch(/^bg-[a-z]+-\d{2,3} text-[a-z]+-\d{2,3}$/)
      expect(profil.puce).toMatch(/^bg-[a-z]+-\d{2,3} text-[a-z]+-\d{2,3}$/)
    }
  })

  it('retombe sur une pastille neutre pour un rôle inconnu', () => {
    expect(badgeDeRole('PROFIL_INEXISTANT')).toBe('bg-gray-100 text-gray-600')
  })
})
