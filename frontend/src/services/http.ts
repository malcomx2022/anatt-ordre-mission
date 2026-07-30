import { useApi } from '@/composables/useApi'
import type { PaginationMeta } from '@/types'

/** Instance axios partagée (intercepteur Sanctum dans useApi). */
export const api = useApi()

export interface Page<T> {
  items: T[]
  meta: PaginationMeta
}

/** Normalise une réponse paginée (collection de Resource OU paginateur brut Laravel). */
export function toPage<T>(payload: any): Page<T> {
  const items: T[] = payload?.data ?? []
  const meta: PaginationMeta = payload?.meta ?? {
    current_page: payload?.current_page ?? 1,
    last_page: payload?.last_page ?? 1,
    per_page: payload?.per_page ?? items.length,
    total: payload?.total ?? items.length,
  }
  return { items, meta }
}

/**
 * Déballe l'enveloppe { data: ... } des réponses « success ».
 *
 * On teste la PRÉSENCE de la clé `data`, pas sa valeur : une réponse
 * légitime { data: null } (ex. une mission sans état de paiement) doit
 * renvoyer null, et non l'enveloppe entière. Un `?? payload` renverrait ici
 * l'objet { data: null, message } — un objet truthy — et ferait planter les
 * écrans qui attendent null.
 */
export function unwrap<T>(payload: any): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return payload.data as T
  }
  return payload as T
}

/** Extrait le nom de fichier d'un en-tête Content-Disposition. */
export function filenameFromDisposition(disposition?: string, fallback = 'fichier'): string {
  if (!disposition) return fallback
  const match = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(disposition)
  return match?.[1] ? decodeURIComponent(match[1].replace(/"/g, '')) : fallback
}

/**
 * Vérifie si une réponse attendue en Blob est en réalité un JSON d'erreur ou de tâche asynchrone.
 */
export async function assertValidBlobResponse(res: any): Promise<void> {
  const contentType = res.headers?.['content-type'] || res.data?.type || ''
  if (contentType.includes('application/json')) {
    const text = await (res.data as Blob).text()
    let errMessage = 'Erreur lors de la génération du fichier.'
    try {
      const json = JSON.parse(text)
      if (json.message) errMessage = json.message
    } catch {
      // JSON illisible
    }
    throw new Error(errMessage)
  }
}

/** Déclenche le téléchargement d'un Blob côté navigateur. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}
