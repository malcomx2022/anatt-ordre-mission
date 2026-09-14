<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import { agentsService } from '@/services/agents'
import { useToast } from '@/composables/useToast'
import type { ImportResultat } from '@/types'

const toast = useToast()
const fichier = ref<File | null>(null)
const uploading = ref(false)
const resultat = ref<ImportResultat | null>(null)

const colonnesAttendues = [
  'nom', 'prenoms', 'matricule', 'ifu', 'grade',
  'indice', 'salaire_brut', 'categorie', 'telephone', 'operateur',
]

function onFile(e: Event) {
  const t = e.target as HTMLInputElement
  fichier.value = t.files?.[0] ?? null
  resultat.value = null
}

async function lancer() {
  if (!fichier.value) return
  uploading.value = true
  resultat.value = null
  try {
    resultat.value = await agentsService.import(fichier.value)
    toast.success(`${resultat.value.importes} agent(s) importé(s).`)
  } catch (e: unknown) {
    const data = (e as { response?: { data?: { errors?: unknown } } })?.response?.data
    if (data?.errors && Array.isArray(data.errors)) {
      resultat.value = { importes: 0, ignores: data.errors.length, rollback: true, erreurs: data.errors }
    } else {
      toast.error("Échec de l'import. Vérifiez le format du fichier.")
    }
  } finally {
    uploading.value = false
  }
}

function reset() {
  fichier.value = null
  resultat.value = null
}
</script>

<template>
  <AppLayout title="Import du Personnel" subtitle="Fichier Excel / CSV — import atomique">
    <template #header-actions>
      <router-link to="/agents" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Personnel</router-link>
    </template>

    <div class="max-w-3xl mx-auto space-y-5">
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-2">Charger le fichier</h3>
        <p class="text-sm font-body text-gray-500 mb-4">Formats : .xlsx / .csv — import <strong>tout ou rien</strong> (rollback en cas d'erreur).</p>

        <div class="bg-gray-50 rounded-xl p-4 mb-4">
          <p class="text-xs font-display font-semibold text-gray-600 mb-2">Colonnes attendues (1ʳᵉ ligne = en-têtes)</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
            <span v-for="c in colonnesAttendues" :key="c" class="bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-center font-mono">{{ c }}</span>
          </div>
          <p class="text-[11px] font-body text-gray-500 mt-3">
            <span class="font-mono">categorie</span> : <span class="font-mono">INDICE_SUP_800</span> ·
            <span class="font-mono">INDICE_400_799</span> · <span class="font-mono">INDICE_INF_400</span>.
            Laissée vide, elle est déduite de l'indice (ou du salaire brut).
            Les anciens codes <span class="font-mono">ORDRE_1/2/3</span> restent acceptés et sont traduits à l'import.
          </p>
        </div>

        <input type="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm font-body text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-anatt-500 file:text-white file:font-display file:text-sm hover:file:bg-anatt-600" @change="onFile">

        <div class="flex justify-end gap-3 mt-5">
          <AppButton variant="ghost" :disabled="!fichier && !resultat" @click="reset">Réinitialiser</AppButton>
          <AppButton :loading="uploading" :disabled="!fichier" @click="lancer">Lancer l'import</AppButton>
        </div>
      </AppCard>

      <!-- Résultat succès -->
      <AppCard v-if="resultat && !resultat.rollback">
        <AppAlert type="success">
          <div>
            <p class="font-display font-semibold">Import réussi</p>
            <p class="text-sm">{{ resultat.importes }} agent(s) importé(s).</p>
          </div>
        </AppAlert>
        <div class="flex justify-end mt-4">
          <router-link to="/agents"><AppButton>Voir le personnel</AppButton></router-link>
        </div>
      </AppCard>

      <!-- Rapport d'erreurs (rollback) -->
      <AppCard v-else-if="resultat && resultat.rollback">
        <AppAlert type="error">
          <div>
            <p class="font-display font-semibold">Import annulé (rollback)</p>
            <p class="text-sm">{{ resultat.erreurs.length }} ligne(s) en erreur. Aucun agent n'a été enregistré.</p>
          </div>
        </AppAlert>
        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-sm font-body">
            <thead>
              <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                <th class="py-2 w-16">Ligne</th>
                <th>Erreurs</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(err, i) in resultat.erreurs" :key="i" class="border-b border-gray-50 align-top">
                <td class="py-2 font-display font-bold text-red-600">#{{ err.ligne }}</td>
                <td class="py-2">
                  <ul class="list-disc ml-4 text-red-600 space-y-0.5">
                    <li v-for="(m, j) in err.erreurs" :key="j">{{ m }}</li>
                  </ul>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>
  </AppLayout>
</template>
