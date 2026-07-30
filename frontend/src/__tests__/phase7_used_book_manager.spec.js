import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = path => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 7D.1 neutral used-book seller surface', () => {
  it('routes the neutral page independently without an actor workspace', () => {
    const router = source('../router/index.js')
    const sidebar = source('../components/profile/UserSidebar.vue')

    expect(router).toContain("path: '/used-books/manage'")
    expect(router).toContain("name: 'used-book-seller'")
    expect(router).not.toContain("path: '/author")
    expect(sidebar).toContain("{ label: 'Sách cũ của tôi', to: '/used-books/manage' }")
  })

  it('keeps a client-side queue and submits each row independently', () => {
    const view = source('../views/used-books/UsedBookManagerView.vue')

    expect(view).toContain('const addDraft')
    expect(view).toContain('const removeDraft')
    expect(view).toContain('for (const draft of drafts.value)')
    expect(view).toContain('await submitDraft(draft)')
    expect(view).toContain("draft.state = 'error'")
    expect(view).toContain('các dữ liệu đã nhập vẫn được giữ nguyên')
    expect(view).toContain('/api/used-book-seller/listings')
    expect(view).toContain('/api/used-book-seller/fulfillment-address')
  })

  it('covers photos, authenticity, inline errors, inventory and responsive cards', () => {
    const view = source('../views/used-books/UsedBookManagerView.vue')

    expect(view).toContain('actual_photos[]')
    expect(view).toContain('photoPreviews')
    expect(view).toContain('authenticity_attested')
    expect(view).toContain('aria-describedby')
    expect(view).toContain('quantity_reserved')
    expect(view).toContain('quantity_sold')
    expect(view).toContain('quantity_returned')
    expect(view).toContain('xl:grid-cols-2')
    expect(view).toContain('min-h-11')
  })
})
