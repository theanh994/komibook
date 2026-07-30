import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const source = (relativePath) => fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8')

describe('Phase 7F.2 performance contracts', () => {
  it('loads the PDF renderer asynchronously inside the already lazy reader route', () => {
    const reader = source('src/views/EbookReaderView.vue')
    const router = source('src/router/index.js')

    expect(reader).toContain("defineAsyncComponent(() => import('vue-pdf-embed'))")
    expect(reader).not.toContain("import VuePdfEmbed from 'vue-pdf-embed'")
    expect(router).toContain("component: () => import('@/views/EbookReaderView.vue')")
  })

  it('keeps the PDF component behind a successful PDF URL', () => {
    const reader = source('src/views/EbookReaderView.vue')

    expect(reader).toContain('v-if="pdfUrl"')
    expect(reader).toContain(':source="pdfUrl"')
  })
})
