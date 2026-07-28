<script setup lang="ts">
/**
 * Affichage des notifications empilées par useToast().
 *
 * Ce conteneur doit être monté UNE fois, à la racine de l'application : sans lui,
 * tous les appels à toast.success/error/warning sont empilés puis oubliés, et
 * l'utilisateur ne voit rien — ni confirmation, ni message d'erreur.
 */
import { useToast } from '@/composables/useToast'

const { toasts, remove } = useToast()
</script>

<template>
  <!-- aria-live : les messages sont annoncés aux lecteurs d'écran sans voler le focus. -->
  <div
    class="fixed top-4 right-4 z-[100] flex flex-col gap-3 w-[min(22rem,calc(100vw-2rem))]"
    role="status"
    aria-live="polite"
  >
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-x-4"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-200 ease-in absolute"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 translate-x-4"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        class="rounded-xl border p-4 pr-10 shadow-lg relative flex items-start gap-3 text-sm font-body"
        :class="[
          t.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' :
          t.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' :
          t.type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' :
          'bg-blue-50 border-blue-200 text-blue-800'
        ]"
      >
        <svg
          class="w-5 h-5 shrink-0 mt-0.5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <template v-if="t.type === 'success'">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </template>
          <template v-else-if="t.type === 'error'">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </template>
          <template v-else-if="t.type === 'warning'">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </template>
          <template v-else>
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </template>
        </svg>

        <p class="flex-1 leading-snug">{{ t.message }}</p>

        <button
          type="button"
          class="absolute top-3 right-3 text-current opacity-50 hover:opacity-100 transition-opacity"
          aria-label="Fermer la notification"
          @click="remove(t.id)"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
