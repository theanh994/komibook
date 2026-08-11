import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const source = async (relativePath) => readFile(fileURLToPath(new URL(relativePath, import.meta.url)), 'utf8')

describe('Phase 7A public feed and catalog contracts', () => {
  it('keeps quick view API-backed and preserves cart event contracts', async () => {
    const dialog = await source('../components/BookQuickViewDialog.vue')

    expect(dialog).toContain("import Dialog from 'primevue/dialog'")
    expect(dialog).toContain('commercial_parties?.supplier?.display_name')
    expect(dialog).not.toContain('vendor?.name')
    expect(dialog).not.toContain('SKU:')
    expect(dialog).not.toContain("'2026'")
    expect(dialog).toContain("emit('add-to-cart', props.book, quantity.value)")
    expect(dialog).toContain("emit('buy-now', props.book, quantity.value)")
    expect(dialog).toContain("props.book?.type === 'ebook' ? 1")
    expect(dialog).toContain('props.book?.is_purchasable === true')
    expect(dialog).toContain('commercial_parties?.supplier?.is_demo === true')
    expect(dialog).toContain('aria-label="Số lượng"')
    expect(dialog).toContain('watch(() => props.visible, (isVisible) => {')
    expect(dialog).toContain('if (isVisible) quantity.value = 1')
  })

  it('uses the shared dialog, route CTA, and a consistent catalog page size', async () => {
    const home = await source('../views/HomeView.vue')
    const catalog = await source('../views/CatalogView.vue')

    expect(home).toContain("import BookQuickViewDialog from '@/components/BookQuickViewDialog.vue'")
    expect(home).toContain('<RouterLink\n              :to="activeHero.to || \'/catalog\'"')
    expect(home).toContain("{{ activeHero.cta || 'Khám phá ngay' }}")
    expect(home).not.toContain('absolute inset-0 z-10')
    expect(home).toContain('to="/flash-sale" class="min-h-11')
    expect(home).toContain('Đọc bài viết <span')
    expect(catalog).toContain("import BookQuickViewDialog from '@/components/BookQuickViewDialog.vue'")
    expect(catalog).toContain('const PAGE_SIZE = 16')
    expect(catalog).toContain('per_page: PAGE_SIZE')
    expect(catalog).toContain(':rows="PAGE_SIZE"')
    expect(catalog).toContain('totalRecords > PAGE_SIZE')
    expect(catalog).toContain('booksError')
    expect(catalog).toContain('categoryError')

    for (const view of [home, catalog]) {
      expect(view).not.toContain('v-if="false"')
      expect(view).not.toContain('vendor?.name')
      expect(view).not.toContain("'2026'")
      expect(view).not.toContain(':modal=')
      expect(view).not.toContain(':show-header=')
      expect(view).not.toContain('contentClass=')
      expect(view).not.toContain('</BookQuickViewDialog>')

      for (const orphan of ['quickViewQty', 'quickViewVersion', 'quickViewAddToCart', 'decrementQty', 'incrementQty', 'cleanDescriptionText', 'getBookTags']) {
        expect(view).not.toContain(orphan)
      }
    }

    for (const deadHomeIdentifier of ['brokenCoverIds', 'markCoverBroken', 'topSellingBooks', 'loadingTopSelling']) {
      expect(home).not.toContain(deadHomeIdentifier)
    }

    expect(home.indexOf('aria-labelledby="vendor-feed-title"')).toBeLessThan(home.indexOf('aria-labelledby="commerce-feed-title"'))
  })
})
