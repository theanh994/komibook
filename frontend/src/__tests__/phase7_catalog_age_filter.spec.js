import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

describe('Phase 7 catalog age filter restoration', () => {
  it('uses the five approved age groups in catalog and vendor book entry', () => {
    const catalog = readSource('../views/CatalogView.vue')
    const bookForm = readSource('../views/vendor/BookFormView.vue')
    const approvedGroups = [
      'Nhà trẻ - mẫu giáo (0 - 6)',
      'Nhi đồng (6 - 11)',
      'Thiếu niên (11 - 15)',
      'Tuổi mới lớn (15 - 18)',
      'Tuổi trưởng thành (Trên 18 tuổi)',
    ]

    for (const group of approvedGroups) {
      expect(catalog).toContain(`value: '${group}', label: '${group}'`)
      expect(bookForm).toContain(`label: '${group}', value: '${group}'`)
    }

    expect(catalog).not.toContain("{ value: '12-17', label:")
  })

  it('keeps catalog wishlist wired to the icon-only shared BookCard', () => {
    const catalog = readSource('../views/CatalogView.vue')
    const card = readSource('../components/BookCard.vue')

    expect(catalog).toContain('show-wishlist')
    expect(catalog).toContain('@toggle-wishlist="toggleWishlist"')
    expect(card).toContain('h-11 w-11')
    expect(card).toContain('aria-hidden="true">favorite</span>')
    expect(card).not.toContain('>Yêu thích</button>')
  })
})
