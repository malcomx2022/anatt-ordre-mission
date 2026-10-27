import { describe, it, expect } from 'vitest'
import { libellesRepartition } from '@/utils/repartition'

/* Miroir de PdfGenerator::libellesRepartition (backend) : mêmes libellés à l'écran et sur le PDF. */
describe('libellesRepartition', () => {
  const base = { montant_total: 90000, avance: 63000, reliquat: 27000, avance_engagee: null, amendements: [] }

  it('donne la part réelle de l’OM, pas le paramètre courant', () => {
    // OM retranscrit à 70 % alors que le paramètre vaut aujourd'hui 60 %.
    expect(libellesRepartition(base, 60)).toEqual({ avance: 'Avance (70 %)', reliquat: 'Reliquat (30 %)' })
  })

  it('retombe sur le paramètre courant pour un OM à montant nul (exonéré)', () => {
    expect(libellesRepartition({ ...base, montant_total: 0, avance: 0, reliquat: 0 }, 60))
      .toEqual({ avance: 'Avance (60 %)', reliquat: 'Reliquat (40 %)' })
  })

  it('après un amendement, avance engagée et reliquat ajusté', () => {
    const amende = { ...base, avance_engagee: 63000, amendements: [{ id: 1 }] }
    expect(libellesRepartition(amende, 70)).toEqual({ avance: 'Avance engagée', reliquat: 'Reliquat (ajusté par amendement)' })
  })

  it('un reliquat négatif est un trop-perçu', () => {
    expect(libellesRepartition({ ...base, reliquat: -49500, avance_engagee: 94500, amendements: [{ id: 1 }] }, 70))
      .toEqual({ avance: 'Avance engagée', reliquat: 'Trop-perçu (à recouvrer par retenue sur salaire)' })
  })

  it('avance engagée sans amendement : répartition en pourcentage, comme le PDF', () => {
    expect(libellesRepartition({ ...base, avance_engagee: 63000 }, 70)).toEqual({ avance: 'Avance (70 %)', reliquat: 'Reliquat (30 %)' })
  })
})
