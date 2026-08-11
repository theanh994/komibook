import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { createSelectedCartQuoteFingerprint, normalizeCartCategoryIds, shouldAcceptCouponPreview } from '../views/CartView.vue'

const cartView = readFileSync(new URL('../views/CartView.vue', import.meta.url), 'utf8')
const bookDetailView = readFileSync(new URL('../views/BookDetailView.vue', import.meta.url), 'utf8')
const flashSaleView = readFileSync(new URL('../views/FlashSaleView.vue', import.meta.url), 'utf8')

const physicalBook = {
  id: 10,
  price: 100000,
  sale_price: 80000,
  vendor_id: 1,
  type: 'paper',
  category_id: 7
}

describe('Batch 7B.1 cart and checkout truth', () => {
  it('does not trust raw VNPAY return parameters or mutate a cart from them', () => {
    expect(cartView).not.toContain('vnp_ResponseCode')
    expect(cartView).not.toContain('vnp_TxnRef')
  })

  it('redirects guests to login before entering checkout and keeps stale-session redirects returnable', () => {
    const guestGuard = cartView.indexOf("if (!authStore.isAuthenticated)")
    const checkoutStep = cartView.indexOf('step.value = 2')
    const checkoutPost = cartView.indexOf('cartStore.checkout(payload)')

    expect(guestGuard).toBeGreaterThan(-1)
    expect(guestGuard).toBeLessThan(checkoutStep)
    expect(cartView).toContain("query: { redirect: '/cart' }")
    expect(checkoutPost).toBeGreaterThan(guestGuard)
  })

  it('binds a coupon to the deterministic selected-cart quote inputs', () => {
    const selected = [{ book: physicalBook, quantity: 1 }]
    const original = createSelectedCartQuoteFingerprint(selected)

    expect(createSelectedCartQuoteFingerprint([{ book: physicalBook, quantity: 2 }])).not.toBe(original)
    expect(createSelectedCartQuoteFingerprint([{ book: { ...physicalBook, sale_price: 70000 }, quantity: 1 }])).not.toBe(original)
    expect(createSelectedCartQuoteFingerprint([{ book: { ...physicalBook, vendor_id: 2 }, quantity: 1 }])).not.toBe(original)
    expect(createSelectedCartQuoteFingerprint([{ book: { ...physicalBook, type: 'ebook' }, quantity: 1 }])).not.toBe(original)
    expect(createSelectedCartQuoteFingerprint([{ book: { ...physicalBook, category_id: 8 }, quantity: 1 }])).not.toBe(original)

    const bookResource = {
      ...physicalBook,
      category_id: undefined,
      category: { id: 7 },
      categories: [{ id: 7 }, { id: 9 }, { id: 7 }]
    }
    const resourceFingerprint = createSelectedCartQuoteFingerprint([{ book: bookResource, quantity: 1 }])
    expect(normalizeCartCategoryIds(bookResource)).toEqual(['7', '9'])
    expect(createSelectedCartQuoteFingerprint([{
      book: { ...bookResource, category: { id: 8 } }, quantity: 1
    }])).not.toBe(resourceFingerprint)
    expect(createSelectedCartQuoteFingerprint([{
      book: { ...bookResource, categories: [{ id: 7 }, { id: 10 }] }, quantity: 1
    }])).not.toBe(resourceFingerprint)
    expect(cartView).toContain('quoteFingerprint: requestedQuoteFingerprint')
    expect(cartView).toContain("couponStatus.value = 'Giỏ hàng đã thay đổi. Hãy áp dụng lại mã ưu đãi.'")
  })

  it('discards an in-flight coupon response when the selected quote has changed', () => {
    expect(shouldAcceptCouponPreview('quote-before-request', 'quote-after-change')).toBe(false)
    expect(shouldAcceptCouponPreview('stable-quote', 'stable-quote')).toBe(true)
    expect(cartView).toContain('const requestedQuoteFingerprint = selectedCartQuoteFingerprint.value')
    expect(cartView).toContain('shouldAcceptCouponPreview(requestedQuoteFingerprint, selectedCartQuoteFingerprint.value)')
  })

  it('preserves BookResource category data in manual cart producers', () => {
    expect(bookDetailView).toContain('category: book.value.category, categories: book.value.categories')
    expect(bookDetailView).toContain("Object.prototype.hasOwnProperty.call(book.value, 'category_id')")
    expect(flashSaleView).toContain('category: book.category')
    expect(flashSaleView).toContain('categories: book.categories')
    expect(flashSaleView).toContain("Object.prototype.hasOwnProperty.call(book, 'category_id')")
  })

  it('keeps the item selector, coupon control, and demo dialog accessible', () => {
    expect(cartView).toContain(':aria-label="`Chọn ${item.book.display_title || item.book.title}`"')
    expect(cartView).toContain('min-h-11 min-w-11')
    expect(cartView).toContain('<label for="coupon-code"')
    expect(cartView).toContain('id="coupon-code"')
    expect(cartView).toContain('id="coupon-status"')
    expect(cartView).toContain('aria-live="polite"')
    expect(cartView).toContain('role="dialog"')
    expect(cartView).toContain('@keydown="trapPaymentFocus"')
    expect(cartView).toContain("if (event.key === 'Escape')")
    expect(cartView).toContain('paymentCloseButton.value?.focus()')
    expect(cartView).toContain('returnFocusElement.value')
  })
})
