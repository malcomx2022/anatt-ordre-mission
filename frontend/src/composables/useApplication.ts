import { api } from '@/services/http'

/**
 * Réglages publics de l'application (Paramètres › Général et Paiements), lus par
 * la route `parametres/application`, ouverte à tout compte connecté — `settings`
 * reste réservée au SUPER_ADMIN.
 *
 * Le nom de l'application devient le titre de l'onglet du navigateur. Un échec
 * de lecture (401, 423, réseau) laisse le titre en place.
 */
export async function chargerApplication(): Promise<void>
{
  try {
    const { data } = await api.get('/api/admin/parametres/application')
    const nom = data?.data?.nom
    if (typeof nom === 'string' && nom.trim() !== '') document.title = nom
  } catch {
    // Titre par défaut (index.html) conservé.
  }
}
