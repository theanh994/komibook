import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { calculateCheckoutPreviewTotal } from '../views/CartView.vue'

const cartView = readFileSync(new URL('../views/CartView.vue', import.meta.url), 'utf8')

describe('Batch 5 shipping policy truth', () => {
  it('loads the backend policy and has a closed preview fallback', () => {
    expect(cartView).toContain("/api/commerce/shipping-policy")
    expect(cartView).toContain('shippingPolicy.value = null')
    expect(cartView).toContain('Phí vận chuyển sẽ được tính khi thanh toán.')
  })

  it('does not duplicate compatibility monetary values in CartView', () => {
    expect(cartView).not.toContain('200000')
    expect(cartView).not.toContain('15000')
  })

  it('returns an unknown total without policy for physical carts and never subtracts the coupon', () => {
    expect(calculateCheckoutPreviewTotal({
      subtotal: 100000,
      shippingFee: 0,
      couponDiscount: 90000,
      hasPhysicalItems: true,
      shippingPolicyAvailable: false
    })).toBeNull()
    expect(calculateCheckoutPreviewTotal({
      subtotal: 100000,
      shippingFee: 0,
      couponDiscount: 10000,
      hasPhysicalItems: false,
      shippingPolicyAvailable: false
    })).toBe(90000)
    expect((cartView.match(/finalTotal === null/g) || []).length).toBe(2)
  })
})
