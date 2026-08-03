import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '1', bookId: '1' }, path: '/admin/test' }),
  useRouter: () => ({ push: vi.fn(), resolve: () => ({ href: '#' }) })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

import UserDetailView from '../views/admin/UserDetailView.vue'
import NotificationAnalyticsView from '../views/admin/NotificationAnalyticsView.vue'
import HelpDeskView from '../views/admin/HelpDeskView.vue'
import TicketDetailView from '../views/admin/TicketDetailView.vue'
import VendorApprovalsView from '../views/admin/VendorApprovalsView.vue'
import MembershipTiersView from '../views/admin/MembershipTiersView.vue'

import apiClient from '../services/axios'

function mountAndCapture(Component) {
  let setupState = null
  const Wrapper = {
    setup(props, ctx) {
      setupState = Component.setup(props, ctx)
      return () => null
    }
  }
  const app = createSSRApp(Wrapper)
  app.use(createPinia())
  app.use(PrimeVue)
  renderToString(app)
  return { app, setupState }
}

describe('Phase 3 Operational Truth Behavioral Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('UserDetailView does not contain fake Nguyễn Văn A profile before or after API error', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('User not found'))

    const { setupState } = mountAndCapture(UserDetailView)
    expect(setupState.user.value).toBeNull()

    try {
      await setupState.fetchUser()
    } catch (err) {
      expect(err).toBeDefined()
    }

    expect(setupState.user.value).toBeNull()
    expect(setupState.error.value).toContain('Không thể kết nối API thông tin người dùng.')
  })

  it('the legacy staff ticket desk does not generate fallback tickets on API rejection', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('API rejection'))

    const { setupState: helpState } = mountAndCapture(HelpDeskView)
    try { await helpState.fetchTickets() } catch (err) { expect(err).toBeDefined() }
    expect(helpState.tickets.value).toEqual([])
    expect(helpState.error.value).toContain('Không thể kết nối API danh sách ticket.')
  })

  it('TicketDetailView sets error state and clears messages on API rejection', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Ticket 404'))

    const { setupState } = mountAndCapture(TicketDetailView)
    try { await setupState.fetchTicketDetails() } catch (err) { expect(err).toBeDefined() }

    expect(setupState.ticket.value).toBeNull()
    expect(setupState.messages.value).toEqual([])
    expect(setupState.error.value).toContain('Không thể kết nối API chi tiết ticket.')
  })

  it('VendorApprovalsView clears vendor list on API error', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network error'))

    const { setupState } = mountAndCapture(VendorApprovalsView)
    try { await setupState.load() } catch (err) { expect(err).toBeDefined() }

    expect(setupState.vendors.value).toEqual([])
    expect(setupState.error.value).toContain('Không thể tải danh sách hồ sơ Nhà bán.')
  })

  it('MembershipTiersView sets error state and does not populate fake tiers on rejection', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Server error'))

    const { setupState } = mountAndCapture(MembershipTiersView)
    try { await setupState.fetchTiers() } catch (err) { expect(err).toBeDefined() }

    expect(setupState.tiers.value).toEqual([])
    expect(setupState.error.value).toContain('Không thể kết nối API hạng thành viên.')
  })

  it('NotificationAnalyticsView handles telemetry_available false without fake breakdown', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        campaign: { id: 1, title: 'Campaign Real', target_audience: 'all', sent_count: 50, status: 'sent' },
        analytics: {
          delivery_rate: null,
          open_rate: 20.0,
          click_rate: 10.0,
          telemetry_available: false,
          hourly_opens: [],
          devices: [],
          segments: [],
        }
      }
    })

    const { setupState } = mountAndCapture(NotificationAnalyticsView)
    await setupState.fetchAnalytics()

    expect(setupState.analytics.value.telemetry_available).toBe(false)
    expect(setupState.analytics.value.hourly_opens).toEqual([])
    expect(setupState.analytics.value.devices).toEqual([])
    expect(setupState.analytics.value.delivery_rate).toBeNull()
  })

  it('HelpDeskView and MembershipTiersView render honest empty state when API returns empty array', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { status: 'success', data: [] } })

    const { setupState: helpState } = mountAndCapture(HelpDeskView)
    await helpState.fetchTickets()
    expect(helpState.tickets.value).toEqual([])
    expect(helpState.error.value).toBeNull()

    const { setupState: tierState } = mountAndCapture(MembershipTiersView)
    await tierState.fetchTiers()
    expect(tierState.tiers.value).toEqual([])
    expect(tierState.error.value).toBeNull()
  })

  it('failed status update action does not update local ticket status', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: { status: 'success', data: { id: 1, subject: 'Issue', status: 'open', messages: [] } }
    })
    vi.spyOn(apiClient, 'patch').mockRejectedValue(new Error('Update failed'))

    const { setupState } = mountAndCapture(TicketDetailView)
    await setupState.fetchTicketDetails()
    expect(setupState.ticket.value.status).toBe('open')

    try { await setupState.updateStatus('resolved') } catch (err) { expect(err).toBeDefined() }

    expect(setupState.ticket.value.status).toBe('open')
  })

  it('retry action re-invokes API request on the SAME component instance', async () => {
    const getSpy = vi.spyOn(apiClient, 'get')
      .mockRejectedValueOnce(new Error('Initial failure'))
      .mockResolvedValueOnce({ data: { status: 'success', data: [] } })

    const { setupState } = mountAndCapture(HelpDeskView)

    try { await setupState.fetchTickets() } catch (err) { expect(err).toBeDefined() }
    expect(setupState.error.value).toContain('Không thể kết nối API danh sách ticket.')
    expect(getSpy.mock.calls.length).toBe(1)

    await setupState.fetchTickets()
    expect(getSpy.mock.calls.length).toBe(2)
    expect(setupState.error.value).toBeNull()
    expect(setupState.tickets.value).toEqual([])
  })
})
