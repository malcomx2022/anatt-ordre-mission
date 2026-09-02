import { useApi } from '@/composables/useApi'
import type { PaginationMeta } from '@/types'

/** Instance axios partagée (intercepteur Sanctum dans useApi). */
export const api = useApi()

export interface Page<T> {
  items: T[]
  meta: PaginationMeta
}

/** Normalise une réponse paginée (collection de Resource OU paginateur brut Laravel). */
export function toPage<T>(payload: unknown): Page<T> {
  const p = (payload ?? {}) as {
    data?: T[]
    meta?: PaginationMeta
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
  }
  const items: T[] = p.data ?? []
  const meta: PaginationMeta = p.meta ?? {
    current_page: p.current_page ?? 1,
    last_page: p.last_page ?? 1,
    per_page: p.per_page ?? items.length,
    total: p.total ?? items.length,
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
export function unwrap<T>(payload: unknown): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data: T }).data
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
export async function assertValidBlobResponse(res: { headers?: Record<string, unknown>; data?: unknown }): Promise<void> {
  const contentType = String(res.headers?.['content-type'] ?? (res.data as Blob | undefined)?.type ?? '')
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
