import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import PrimeVue from 'primevue/config'

const toastAdd = vi.fn()
vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd }),
}))

import apiClient from '../services/axios'
import ReconciliationView from '../views/admin/ReconciliationView.vue'

function mountAndCapture() {
  let setupState = null
  const Wrapper = {
    setup(props, context) {
      setupState = ReconciliationView.setup(props, context)
      return () => null
    },
  }
  const app = createSSRApp(Wrapper)
  app.use(PrimeVue)
  renderToString(app)

  return setupState
}

describe('Phase 7 reconciliation truth contract', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('binds payout integrity and pagination from the backend response', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        status: 'success',
        data: {
          kpi: { pending_payout: 100000, approved_payout: 0, total_settled: 0, unreconciled: 1 },
          payout_requests: [{
            id: 9,
            status: 'pending',
            reconciliation: {
              is_consistent: false,
              issues: ['missing_transition'],
              ledger_entry_count: 1,
              latest_transition: null,
            },
          }],
          meta: { current_page: 1, last_page: 3, per_page: 15, total: 31 },
        },
      },
    })

    const state = mountAndCapture()
    await state.fetchReconciliations()

    expect(apiClient.get).toHaveBeenCalledWith('/api/admin/reconciliation', {
      params: { status: 'all', page: 1 },
    })
    expect(state.kpis.value.unreconciled).toBe(1)
    expect(state.payoutRequests.value[0].reconciliation.issues).toEqual(['missing_transition'])
    expect(state.pagination.value).toMatchObject({ current_page: 1, last_page: 3, total: 31 })
    expect(state.reconciliationConfig(state.payoutRequests.value[0]).label).toBe('Cần kiểm tra')
  })

  it('does not fabricate rows when the API returns an empty page', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: {
        status: 'success',
        data: {
          kpi: { pending_payout: 0, approved_payout: 0, total_settled: 0, unreconciled: 0 },
          payout_requests: [],
          meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        },
      },
    })

    const state = mountAndCapture()
    await state.fetchReconciliations()

    expect(state.payoutRequests.value).toEqual([])
    expect(state.kpis.value.unreconciled).toBe(0)
    expect(toastAdd).not.toHaveBeenCalled()
  })
})
