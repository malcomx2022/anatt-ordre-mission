<script setup lang="ts">
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
</script>

<template>
  <AppLayout title="Confirmation des paiements" subtitle="Modèle manuel — aucun réimport de fichier">
    <div class="max-w-2xl mx-auto space-y-5">
      <AppAlert type="info">
        <div>
          <p class="font-display font-semibold mb-1">Pas de réconciliation automatique</p>
          <p class="text-sm">
            L'application <strong>génère</strong> les fichiers de paiement à déposer sur les plateformes
            MTN / Moov. Elle ne réimporte <strong>aucun</strong> fichier retour et n'appelle aucune API opérateur.
          </p>
        </div>
      </AppAlert>

      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-3">Cycle de paiement</h3>
        <ol class="space-y-3 text-sm font-body text-gray-600">
          <li><span class="font-display font-bold text-anatt-600">1.</span> Génération du fichier (lot → <strong>GENERE</strong>), téléchargement.</li>
          <li><span class="font-display font-bold text-anatt-600">2.</span> Dépôt manuel sur la plateforme de l'opérateur.</li>
          <li><span class="font-display font-bold text-anatt-600">3.</span> Après dépôt réussi : <strong>Confirmer le paiement</strong> dans l'app (lot + versements → <strong>CONFIRME</strong>, horodatés).</li>
          <li><span class="font-display font-bold text-anatt-600">4.</span> Tant qu'un lot n'est pas confirmé, il peut être <strong>régénéré</strong>. Un versement confirmé n'est jamais réinclus.</li>
        </ol>
        <div class="mt-5 flex gap-3">
          <router-link to="/paiements/mtn" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600">Générer un lot</router-link>
          <router-link to="/paiements" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">Voir les lots</router-link>
        </div>
      </AppCard>
    </div>
  </AppLayout>
</template>
