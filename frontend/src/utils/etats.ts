/**
 * Miroir frontend de EtatPaiement::normaliserNumero() (backend).
 *
 * Le numéro est stocké sans préfixe : ce sont les écrans et les PDF qui posent
 * le « N° ». Ce helper est la seule façon d'afficher un numéro d'état, pour que
 * les enregistrements anciens — saisis avec le préfixe — ne s'impriment pas
 * « N°N°0069/… ».
 */

/**
 * Retire le préfixe « N° » (N°, N˚, Nº, et « No » devant un chiffre), autant de
 * fois qu'il est présent. « # » n'en est pas un : « ### » est un numéro
 * symbolique valide du registre.
 */
export function normaliserNumeroEtat(numero: string | null | undefined): string | null {
  if (!numero) return null

  const valeur = numero.trim().replace(/^(?:(?:n\s*[°˚º]|n\s*o(?=\s*\d))\s*)+/i, '').trim()

  return valeur === '' ? null : valeur
}

/**
 * Numéro prêt à l'affichage, préfixé une seule fois.
 * `absent` est rendu quand aucun numéro n'a encore été attribué.
 */
export function numeroEtat(numero: string | null | undefined, absent = '—'): string {
  const valeur = normaliserNumeroEtat(numero)

  return valeur === null ? absent : `N°${valeur}`
}
