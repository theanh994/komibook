import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'

globalThis.localStorage = {
  getItem: () => null,
  setItem: () => {},
  removeItem: () => {},
  clear: () => {}
}

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: { order_id: '10' }, params: { orderId: '10' }, path: '/test' }),
  useRouter: () => ({ push: vi.fn(), resolve: () => ({ href: '#' }) })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

import HelpCenterView from '../views/HelpCenterView.vue'
import FlashSaleView from '../views/FlashSaleView.vue'
import CheckoutSuccessView from '../views/CheckoutSuccessView.vue'
import OrderTrackingView from '../views/OrderTrackingView.vue'
import LoginView from '../views/auth/LoginView.vue'
import RegisterView from '../views/auth/RegisterView.vue'

import apiClient from '../services/axios'
import { useAuthStore } from '../stores/auth'

const pinia = createPinia()

function mountAndCapture(Component) {
  let setupState = null
  const Wrapper = {
    setup(props, ctx) {
      setupState = Component.setup(props, ctx)
      return () => null
    }
  }
  const app = createSSRApp(Wrapper)
  app.use(pinia)
  app.use(PrimeVue)
  renderToString(app)
  return { app, setupState }
}

describe('Phase 3C.2 Public Journey Truth Behavioral Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('HelpCenterView handles API rejection without generating fallback FAQs, and retry succeeds', async () => {
    const getSpy = vi.spyOn(apiClient, 'get')
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce({
        data: {
          status: 'success',
          data: [{ id: 1, title: 'Real Article', content: 'Real Content', helpful_count: 5 }]
        }
      })

    const { setupState } = mountAndCapture(HelpCenterView)

    try { await setupState.fetchArticles() } catch (err) { expect(err).toBeDefined() }
    expect(setupState.articles.value).toEqual([])
    expect(setupState.error.value).toContain('Không thể tải danh sách bài viết trợ giúp.')

    await setupState.fetchArticles()
    expect(getSpy.mock.calls.length).toBe(2)
    expect(setupState.error.value).toBeNull()
    expect(setupState.articles.value.length).toBe(1)
    expect(setupState.articles.value[0].title).toBe('Real Article')
  })

  it('HelpCenterView helpful rating only increments counter on success', async () => {
    const article = { id: 1, title: 'Test', helpful_count: 10 }

    vi.spyOn(apiClient, 'post').mockRejectedValueOnce(new Error('Failed'))
    const { setupState } = mountAndCapture(HelpCenterView)

    await setupState.rateHelpful(article)
    expect(article.helpful_count).toBe(10)
    expect(setupState.helpfulError.value).toContain('Không thể gửi đánh giá lúc này.')

    vi.spyOn(apiClient, 'post').mockResolvedValueOnce({ data: { status: 'success' } })
    await setupState.rateHelpful(article)
    expect(article.helpful_count).toBe(11)
    expect(setupState.helpfulMessage.value).toContain('Cảm ơn bạn')
  })

  it('FlashSaleView displays 0% when sold_quantity = 0 and distinguishes error from success-empty', async () => {
    const { setupState } = mountAndCapture(FlashSaleView)

    const percent = setupState.getSoldPercent({ sold_quantity: 0, max_quantity: 50, book: { stock: 10 } })
    expect(percent).toBe(0)

    vi.spyOn(apiClient, 'get').mockRejectedValueOnce(new Error('Flash sale server error'))
    try { await setupState.fetchActiveSale() } catch (err) { expect(err).toBeDefined() }
    expect(setupState.activeSale.value).toBeNull()
    expect(setupState.error.value).toContain('Không thể kết nối chương trình Flash Sale.')

    vi.spyOn(apiClient, 'get').mockResolvedValueOnce({ data: { status: 'success', data: null } })
    await setupState.fetchActiveSale()
    expect(setupState.activeSale.value).toBeNull()
    expect(setupState.error.value).toBeNull()
  })

  it('CheckoutSuccessView calls detail endpoint by ID and does not mark unpaid orders as paid success', async () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        status: 'success',
        data: { id: 10, order_code: 'ORD-10', status: 'pending', payment_status: 'unpaid', grand_total: 150000, items: [] }
      }
    })

    const { setupState } = mountAndCapture(CheckoutSuccessView)
    await setupState.fetchOrderDetails()

    expect(getSpy).toHaveBeenCalledWith('/api/my-orders/10')
    expect(setupState.order.value.id).toBe(10)
    expect(setupState.isConfirmedPaid.value).toBe(false)
    expect(setupState.hasReadableEbook.value).toBe(false)

    setupState.order.value = { status: 'cancelled', payment_status: 'paid', items: [{ book: { type: 'ebook' } }] }
    expect(setupState.isCancelledOrRefunded.value).toBe(true)
    expect(setupState.isConfirmedPaid.value).toBe(false)
    expect(setupState.hasReadableEbook.value).toBe(false)
  })

  it('CheckoutSuccessView handles 404/error state and permits retry', async () => {
    const getSpy = vi.spyOn(apiClient, 'get')
      .mockRejectedValueOnce(new Error('404 Not Found'))
      .mockResolvedValueOnce({
        data: { status: 'success', data: { id: 10, status: 'completed', payment_status: 'paid' } }
      })

    const { setupState } = mountAndCapture(CheckoutSuccessView)
    try { await setupState.fetchOrderDetails() } catch (err) { expect(err).toBeDefined() }

    expect(setupState.order.value).toBeNull()
    expect(setupState.error.value).toContain('Không thể tải thông tin chi tiết đơn hàng.')

    await setupState.fetchOrderDetails()
    expect(getSpy.mock.calls.length).toBe(2)
    expect(setupState.error.value).toBeNull()
    expect(setupState.isConfirmedPaid.value).toBe(true)
  })

  it('OrderTrackingView fetches order by ID and does not generate fake timeline, ETA, carrier or tracking code', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        status: 'success',
        data: {
          id: 10,
          order_code: 'ORD-10',
          status: 'pending',
          created_at: '2026-07-26T10:00:00Z',
          shipping_address: '123 Test St',
          shipping_carrier: null,
          shipping_tracking_code: null,
          user: { name: null, phone: null },
          items: []
        }
      }
    })

    const { setupState } = mountAndCapture(OrderTrackingView)
    await setupState.fetchOrder()

    expect(setupState.order.value.id).toBe(10)
    expect(setupState.estimatedDelivery.value).toBe('Chưa có dự kiến giao hàng')
    expect(setupState.trackingEvents.value).toEqual([])
    expect(setupState.order.value.shipping_carrier).toBeNull()
    expect(setupState.order.value.shipping_tracking_code).toBeNull()
  })

  it('OrderTrackingView shipping stepper maps status steps correctly', async () => {
    const { setupState } = mountAndCapture(OrderTrackingView)

    setupState.order.value = { status: 'pending', shipping_status: null }
    expect(setupState.currentStepIndex.value).toBe(0)

    setupState.order.value = { status: 'processing', shipping_status: 'pending_pickup' }
    expect(setupState.currentStepIndex.value).toBe(1)

    setupState.order.value = { status: 'processing', shipping_status: 'picked_up' }
    expect(setupState.currentStepIndex.value).toBe(2)

    setupState.order.value = { status: 'processing', shipping_status: 'delivering' }
    expect(setupState.currentStepIndex.value).toBe(3)

    setupState.order.value = { status: 'completed', shipping_status: 'delivered' }
    expect(setupState.currentStepIndex.value).toBe(4)

    setupState.order.value = { status: 'processing', shipping_status: 'failed' }
    expect(setupState.currentStepIndex.value).toBe(0)
    expect(setupState.journeyException.value).toContain('thất bại')

    setupState.order.value = { status: 'cancelled', shipping_status: null }
    expect(setupState.currentStepIndex.value).toBe(0)
    expect(setupState.journeyException.value).toContain('đã bị hủy')
  })

  it('LoginView and RegisterView keep otpSent false on sendPhoneOtp rejection and set otpSent true on resolve', async () => {
    const { setupState: loginState } = mountAndCapture(LoginView)
    const { setupState: regState } = mountAndCapture(RegisterView)
    const authStore = useAuthStore()

    loginState.phoneInput.value = '0989999999'
    regState.phoneInput.value = '0989999999'

    // 1. Failure case for login
    vi.spyOn(authStore, 'sendPhoneOtp').mockRejectedValueOnce(new Error('OTP Send Failed'))
    try { await loginState.handleSendOtp() } catch (err) { expect(err).toBeDefined() }
    expect(loginState.otpSent.value).toBe(false)

    // 2. Failure case for register
    vi.spyOn(authStore, 'sendPhoneOtp').mockRejectedValueOnce(new Error('OTP Send Failed'))
    try { await regState.handleSendOtp() } catch (err) { expect(err).toBeDefined() }
    expect(regState.otpSent.value).toBe(false)

    // 3. Success case for login
    vi.spyOn(authStore, 'sendPhoneOtp').mockResolvedValueOnce({ status: 'success' })
    await loginState.handleSendOtp()
    expect(loginState.otpSent.value).toBe(true)

    // 4. Success case for register
    vi.spyOn(authStore, 'sendPhoneOtp').mockResolvedValueOnce({ status: 'success' })
    await regState.handleSendOtp()
    expect(regState.otpSent.value).toBe(true)
  })
})
