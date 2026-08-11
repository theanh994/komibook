import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { canPayOrder, getPaymentResultHint } from '../views/OrdersView.vue'
import { canCancelOrder, isValidCancellationScope } from '../utils/buyerCancellation'

const ordersView = readFileSync(new URL('../views/OrdersView.vue', import.meta.url), 'utf8')
const trackingView = readFileSync(new URL('../views/OrderTrackingView.vue', import.meta.url), 'utf8')

describe('Batch 7B.2 orders truth and accessibility', () => {
  it('fails cancellation closed unless the API explicitly permits it', () => {
    expect(canCancelOrder({ status: 'pending' })).toBe(false)
    expect(canCancelOrder({ can_cancel: false, status: 'pending' })).toBe(false)
    expect(canCancelOrder({ status: 'processing', payment_status: 'paid' })).toBe(false)
    expect(canCancelOrder({ status: 'unknown', payment_status: 'unknown' })).toBe(false)
    expect(canCancelOrder({ can_cancel: true, id: 8 })).toBe(false)
    const validScopeOrder = {
      id: 8,
      can_cancel: true,
      cancellation_scope: { type: 'checkout_session', count: 2, order_ids: [8, 9], order_codes: ['ORD-8', 'ORD-9'] }
    }
    expect(isValidCancellationScope(validScopeOrder)).toBe(true)
    expect(canCancelOrder(validScopeOrder)).toBe(true)
    expect(canCancelOrder({ ...validScopeOrder, status: 'cancelled' })).toBe(true)
    expect(isValidCancellationScope({ ...validScopeOrder, cancellation_scope: { ...validScopeOrder.cancellation_scope, order_ids: [9, 10] } })).toBe(false)
    expect(isValidCancellationScope({ ...validScopeOrder, cancellation_scope: { ...validScopeOrder.cancellation_scope, order_codes: ['ORD-8'] } })).toBe(false)
    expect(ordersView).toContain("import { canCancelOrder, formatCancellationScope, isValidCancellationScope } from '@/utils/buyerCancellation'")
  })

  it('rejects malformed authoritative cancellation scopes without coercing API identifiers', () => {
    const validPartialSession = {
      id: 8,
      status: 'cancelled',
      can_cancel: true,
      cancellation_scope: { type: 'checkout_session', count: 2, order_ids: [8, 9], order_codes: ['ORD-8', 'ORD-9'] }
    }

    expect(isValidCancellationScope(validPartialSession)).toBe(true)
    expect(isValidCancellationScope({ ...validPartialSession, cancellation_scope: { ...validPartialSession.cancellation_scope, order_ids: [8, 8] } })).toBe(false)
    expect(isValidCancellationScope({ ...validPartialSession, cancellation_scope: { ...validPartialSession.cancellation_scope, order_codes: [' ORD-8 ', 'ord-8'] } })).toBe(false)
    expect(isValidCancellationScope({ ...validPartialSession, cancellation_scope: { ...validPartialSession.cancellation_scope, order_ids: [8, '9'] } })).toBe(false)
    expect(isValidCancellationScope({ ...validPartialSession, cancellation_scope: { ...validPartialSession.cancellation_scope, order_ids: [8, 0] } })).toBe(false)
    expect(isValidCancellationScope({ ...validPartialSession, id: '8' })).toBe(false)
    expect(isValidCancellationScope({ ...validPartialSession, cancellation_scope: { type: 'single_order', count: 2, order_ids: [8, 9], order_codes: ['ORD-8', 'ORD-9'] } })).toBe(false)
  })

  it('allows payment only for canonical pending, unpaid online VNPAY orders', () => {
    expect(canPayOrder({ status: ' PENDING ', payment_status: 'UnPaId', payment_method: 'VNPAY' })).toBe(true)
    expect(canPayOrder({ status: 'pending', payment_status: 'unpaid', payment_method: 'online' })).toBe(true)
    expect(canPayOrder({ status: 'processing', payment_status: 'unpaid', payment_method: 'vnpay' })).toBe(false)
    expect(canPayOrder({ status: 'pending', payment_status: 'paid', payment_method: 'vnpay' })).toBe(false)
    expect(canPayOrder({ status: 'pending', payment_status: 'unpaid', payment_method: 'cod' })).toBe(false)
    expect(canPayOrder({})).toBe(false)
    expect(ordersView).toContain('if (!canPayOrder(order) || payingOrderId.value !== null) return')
    expect(ordersView).toContain(':disabled="payingOrderId !== null"')
  })

  it('keeps the pending filter aligned with its processing-only label', () => {
    const pendingFilter = ordersView.match(/if \(currentFilter\.value === 'pending'\) \{\s+return orders\.value\.filter\(o => \[(.*?)\]\.includes\(o\.status\)\)\s+\}/s)

    expect(pendingFilter?.[1]).toContain("'pending'")
    expect(pendingFilter?.[1]).toContain("'confirmed'")
    expect(pendingFilter?.[1]).toContain("'processing'")
    expect(pendingFilter?.[1]).not.toContain("'shipped'")
  })

  it('treats the payment query as an allowlisted, reconciling navigation hint only', () => {
    const successHint = getPaymentResultHint('success')

    expect(successHint).toMatchObject({ severity: 'info', summary: 'Đã nhận phản hồi thanh toán' })
    expect(successHint.detail).toContain('đối chiếu')
    expect(successHint.detail).not.toContain('thành công')
    expect(getPaymentResultHint('failed')).not.toBeNull()
    expect(getPaymentResultHint('unknown')).toBeNull()
    expect(getPaymentResultHint('SUCCESS')).toBeNull()
    expect(getPaymentResultHint(['success'])).toBeNull()

    const fetchIndex = ordersView.indexOf('const reconciled = await fetchOrders()')
    const toastIndex = ordersView.indexOf('toast.add({ ...paymentHint, life: 5000 })')
    expect(fetchIndex).toBeGreaterThan(-1)
    expect(toastIndex).toBeGreaterThan(fetchIndex)
    expect(ordersView).toContain('const preservedQuery = { ...route.query }')
    expect(ordersView).toContain('delete preservedQuery.payment')
    expect(ordersView).toContain('router.replace({ query: preservedQuery })')
    expect(ordersView).not.toContain('router.replace({ query: {} })')
    expect(ordersView).not.toMatch(/order\.status\s*=(?!=)/)
    expect(ordersView).not.toMatch(/order\.payment_status\s*=(?!=)/)
  })

  it('uses the shared validated scope for tracking disclosure and cancellation POSTs', () => {
    const trackingScope = {
      id: 41,
      status: 'cancelled',
      can_cancel: true,
      cancellation_scope: { type: 'checkout_session', count: 2, order_ids: [41, 42], order_codes: ['ORD-41', 'ORD-42'] }
    }

    expect(canCancelOrder(trackingScope)).toBe(true)
    expect(canCancelOrder({ ...trackingScope, cancellation_scope: { ...trackingScope.cancellation_scope, order_ids: [41, '42'] } })).toBe(false)
    expect(trackingView).toContain("import { canCancelOrder, formatCancellationScope } from '@/utils/buyerCancellation'")
    expect(trackingView).toContain('v-if="canCancelOrder(order)"')
    expect(trackingView).toContain('@click="openCancelModal"')
    expect(trackingView).toContain('v-if="showCancelModal && canCancelOrder(order)"')
    expect(trackingView).toContain('if (!canCancelOrder(order.value) || cancelling.value) return')
    expect(trackingView).toContain('formatCancellationScope(order.cancellation_scope)')
    expect(trackingView).toContain("order.cancellation_scope?.type === 'checkout_session'")
    expect(trackingView).not.toContain("order.payment_status === 'paid'")
    expect(trackingView).not.toContain('hoàn 100%')
    expect(trackingView).not.toContain('ngay lập tức')
  })

  it('blocks Orders modal rendering and cancellation POSTs when a scope becomes stale or malformed', () => {
    const staleScopeOrder = {
      id: 73,
      can_cancel: true,
      cancellation_scope: { type: 'checkout_session', count: 2, order_ids: [74, 75], order_codes: ['ORD-74', 'ORD-75'] }
    }

    expect(canCancelOrder(staleScopeOrder)).toBe(false)
    expect(ordersView).toContain('v-if="showCancelModal && canCancelOrder(selectedOrderToCancel)"')
    expect(ordersView).toContain('if (!canCancelOrder(selectedOrderToCancel.value) || cancelling.value) return')
  })

  it('keeps the destructive cancellation dialog keyboard-accessible and honest', () => {
    expect(ordersView).toContain('role="dialog"')
    expect(ordersView).toContain('aria-modal="true"')
    expect(ordersView).toContain('aria-labelledby="cancel-order-title"')
    expect(ordersView).toContain('aria-describedby="cancel-order-description"')
    expect(ordersView).toContain('@keydown="trapCancelFocus"')
    expect(ordersView).toContain("if (event.key === 'Escape')")
    expect(ordersView).toContain("if (event.key !== 'Tab') return")
    expect(ordersView).toContain('cancelCloseButton.value?.focus()')
    expect(ordersView).toContain('returnFocusElement.value')
    expect(ordersView).toContain('if (cancelling.value && !force) return false')
    expect(ordersView).toContain('onBeforeUnmount(() => closeCancelModal({ force: true, restoreFocus: false }))')
    expect(ordersView).toContain('ref="ordersHeading"')
    expect(ordersView).toContain('id="orders-title" tabindex="-1"')
    expect(ordersView).toContain('const focusOrdersHeading = () => nextTick(() => ordersHeading.value?.focus())')
    const successCloseIndex = ordersView.indexOf('closeCancelModal({ restoreFocus: false })')
    const successRefreshIndex = ordersView.indexOf('await fetchOrders()', successCloseIndex)
    const successFocusIndex = ordersView.indexOf('focusOrdersHeading()', successRefreshIndex)
    expect(successCloseIndex).toBeGreaterThan(-1)
    expect(successRefreshIndex).toBeGreaterThan(successCloseIndex)
    expect(successFocusIndex).toBeGreaterThan(successRefreshIndex)
    expect(ordersView).not.toContain('hoàn 100%')
    expect(ordersView).not.toContain('ngay lập tức')
    expect(ordersView).toContain('button:focus-visible')
    expect(ordersView).toContain('min-height: 44px')
    expect(ordersView).toContain('Phạm vi hủy gồm')
    expect(ordersView).toContain('formatCancellationScope(selectedOrderToCancel.cancellation_scope)')
    expect(ordersView).toContain('Thao tác này áp dụng cho toàn bộ đơn hàng trong cùng phiên thanh toán.')
  })
})
