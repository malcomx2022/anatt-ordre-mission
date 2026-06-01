/// <reference types="vite/client" />

// Configuration injectée au runtime via public/runtime-config.js
// (permet de changer l'URL de l'API après déploiement, sans recompiler).
interface Window {
  __ANATT_CONFIG__?: {
    apiUrl?: string
  }
}
