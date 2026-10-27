/**
 * Libellés de la répartition avance / reliquat d'un OM, à l'écran comme sur le PDF.
 * Miroir de PdfGenerator::libellesRepartition (backend).
 *
 * Le pourcentage vient des montants de l'OM, pas du paramètre courant : la part
 * d'avance a pu changer depuis la retranscription. Une fois l'avance engagée et
 * l'OM amendé, la répartition n'est plus un pourcentage : le reliquat absorbe
 * l'amendement, jusqu'au trop-perçu (cf. docs/amendement-om.md).
 */
export interface RepartitionOm {
  montant_total: number
  avance: number
  reliquat: number
  avance_engagee?: number | null
  amendements?: unknown[]
}

export function libellesRepartition(
  ordre: RepartitionOm,
  partAvanceCourante: number,
): { avance: string; reliquat: string } {
  if (ordre.reliquat < 0) {
    return { avance: 'Avance engagée', reliquat: 'Trop-perçu (à recouvrer par retenue sur salaire)' }
  }
  if (ordre.avance_engagee != null && (ordre.amendements?.length ?? 0) > 0) {
    return { avance: 'Avance engagée', reliquat: 'Reliquat (ajusté par amendement)' }
  }
  const part = ordre.montant_total > 0
    ? Math.round((ordre.avance * 100) / ordre.montant_total)
    : partAvanceCourante
  return { avance: `Avance (${part} %)`, reliquat: `Reliquat (${100 - part} %)` }
}
