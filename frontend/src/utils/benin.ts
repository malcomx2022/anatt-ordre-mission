import type { Categorie, Operateur } from '@/types'

/**
 * Miroir frontend de config/benin.php (téléphonie + déduction de catégorie),
 * pour le retour visuel temps réel. La validation source de vérité reste l'API.
 */

const PREFIXES: Record<Operateur, string[]> = {
  MTN: ['50', '51', '52', '53', '54', '55', '56', '57', '58', '59',
    '90', '91', '92', '93', '94', '95', '96', '97', '98', '99'],
  MOOV: ['40', '41', '42', '43', '44', '45', '46', '47', '48', '49',
    '60', '61', '62', '63', '64', '65', '66', '67', '68', '69'],
}

/** Numéro national valide : 10 chiffres débutant par 01. */
export function telephoneValide(tel: string | null | undefined): boolean {
  return !!tel && /^01\d{8}$/.test(tel)
}

/** Opérateur déduit du numéro, ou null si inconnu/invalide. */
export function operateurPour(tel: string | null | undefined): Operateur | null {
  if (!telephoneValide(tel)) return null
  const prefixe = tel!.slice(2, 4)
  if (PREFIXES.MTN.includes(prefixe)) return 'MTN'
  if (PREFIXES.MOOV.includes(prefixe)) return 'MOOV'
  return null
}

/** Cohérence numéro ↔ opérateur. */
export function operateurCoherent(tel: string | null | undefined, operateur: Operateur): boolean {
  const deduit = operateurPour(tel)
  return deduit !== null && deduit === operateur
}

/**
 * Catégorie déduite de l'indice (prioritaire) ou du salaire brut.
 * Concordance ANaTT : INDICE_SUP_800 ≥ 800 / ≥ 372000 ; INDICE_400_799 400-799 / 186000-372000 ; INDICE_INF_400 sinon.
 */
export function categorieDeduite(indice?: number | null, salaireBrut?: number | null): Categorie | null {
  if (indice != null) {
    if (indice >= 800) return 'INDICE_SUP_800'
    if (indice >= 400) return 'INDICE_400_799'
    return 'INDICE_INF_400'
  }
  if (salaireBrut != null) {
    if (salaireBrut >= 372000) return 'INDICE_SUP_800'
    if (salaireBrut >= 186000) return 'INDICE_400_799'
    return 'INDICE_INF_400'
  }
  return null
}
