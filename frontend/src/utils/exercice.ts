/**
 * Exercice (année fiscale) auquel appartient une date — miroir de
 * `Parametres::anneeFiscalePour()` côté serveur. L'exercice est étiqueté par
 * son année de DÉBUT : avec un début en juillet, mars 2027 relève de 2026.
 */
export function exerciceDe(date: Date, moisDebut: number): number {
  const mois = date.getMonth() + 1
  return mois >= moisDebut ? date.getFullYear() : date.getFullYear() - 1
}
