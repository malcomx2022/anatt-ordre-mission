import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/*
 * start-dev.ps1 / stop-dev.ps1 : Windows PowerShell 5.1 lit un script sans BOM en ANSI.
 * Un caractère non ASCII (tiret cadratin « — », lettre accentuée) y devient illisible dans
 * les messages, voire casse l'analyse. Trois « — » s'y étaient glissés (relevé le 29/10/2026).
 */
describe('Scripts de développement Windows', () => {
  it.each(['start-dev.ps1', 'stop-dev.ps1'])('%s ne contient que de l’ASCII', (fichier) => {
    const contenu = readFileSync(resolve(__dirname, '../../..', fichier), 'utf-8')
    const fautifs = contenu.split('\n').flatMap((l, i) => ([...l].some((c) => c.charCodeAt(0) > 127) ? [`${i + 1}: ${l.trim()}`] : []))
    expect(fautifs).toEqual([])
  })
})
