<script setup lang="ts">
/**
 * Fine barre de progression en haut de l'écran pendant les navigations.
 *
 * Les écrans sont chargés à la demande (import() par route) : au premier accès
 * à une page, le navigateur télécharge son chunk, ce qui laisse un temps mort
 * sans aucun retour visuel. Cette barre comble ce vide — l'utilisateur voit
 * que quelque chose se passe, même si le composant n'est pas encore prêt.
 */
import { ref, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const actif = ref(false)
const largeur = ref(0)
let timer: ReturnType<typeof setInterval> | null = null
let fin: ReturnType<typeof setTimeout> | null = null

function demarrer() {
  if (fin) clearTimeout(fin)
  actif.value = true
  largeur.value = 8
  if (timer) clearInterval(timer)
  // Progression asymptotique : on approche 90 % sans jamais l'atteindre tant
  // que la navigation n'est pas résolue.
  timer = setInterval(() => {
    largeur.value += Math.max(0.5, (90 - largeur.value) * 0.12)
    if (largeur.value >= 90 && timer) {
      clearInterval(timer)
      timer = null
    }
  }, 120)
}

function terminer() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
  largeur.value = 100
  fin = setTimeout(() => {
    actif.value = false
    largeur.value = 0
  }, 240)
}

// beforeEach part AVANT le chargement du chunk ; afterEach arrive une fois la
// navigation entièrement résolue (chunk téléchargé, garde passée).
const stopBefore = router.beforeEach(() => {
  demarrer()
  return true
})
// Les trois crochets sont retirés au démontage : sinon chaque navigation suivante
// réarmerait le minuteur de fin d'un composant qui n'est plus affiché.
const stopAfter = router.afterEach(terminer)
const stopError = router.onError(terminer)

onBeforeUnmount(() => {
  stopBefore()
  stopAfter()
  stopError()
  if (timer) clearInterval(timer)
  if (fin) clearTimeout(fin)
})
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-150"
    leave-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    leave-to-class="opacity-0"
  >
    <div
      v-if="actif"
      class="fixed top-0 left-0 right-0 z-[200] h-0.5 pointer-events-none"
      role="progressbar"
      aria-label="Chargement de la page"
    >
      <div
        class="h-full bg-anatt-500 transition-[width] duration-150 ease-out shadow-[0_0_8px_rgba(29,158,79,0.6)]"
        :style="{ width: largeur + '%' }"
      ></div>
    </div>
  </Transition>
</template>
