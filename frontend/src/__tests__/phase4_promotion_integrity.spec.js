import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 4C.3 promotion UI contracts', () => {
  it('creates campaigns with timezone and explicit coupon stacking policy', () => {
    const sourceText = source('../views/admin/PromotionsView.vue')
    expect(sourceText).toContain("timezone: 'Asia/Ho_Chi_Minh'")
    expect(sourceText).toContain('coupon_stacking_policy')
    expect(sourceText).toContain("is_active: false")
    expect(sourceText).toContain('transitionFlashSale')
    expect(sourceText).toContain('operation_key')
  })

  it('sends auditable approval and rejection operations', () => {
    const sourceText = source('../views/admin/FlashSaleDetailView.vue')
    expect(sourceText).toContain('operation_key')
    expect(sourceText).toContain('Nhập lý do từ chối')
    expect(sourceText).toContain('{ reason, operation_key:')
  })
})
