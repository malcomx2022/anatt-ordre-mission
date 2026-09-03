/** Option d'un AppCombobox : une valeur, un libellé, et un indice facultatif. */
export interface ComboOption {
  value: string | number
  label: string
  /** Texte secondaire affiché à droite (matricule, catégorie, référence…). */
  hint?: string
}
