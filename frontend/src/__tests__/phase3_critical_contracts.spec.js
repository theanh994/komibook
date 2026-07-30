import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import PrimeVue from 'primevue/config'

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { bookId: '1' } }),
  useRouter: () => ({ push: vi.fn(), resolve: () => ({ href: '#' }) })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

import InventoryAuditView from '../views/vendor/InventoryAuditView.vue'
import StockTransferView from '../views/vendor/StockTransferView.vue'
import LiveEditorView from '../views/vendor/LiveEditorView.vue'
import MultiDevicePreviewView from '../views/vendor/MultiDevicePreviewView.vue'
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
  app.use(PrimeVue)
  renderToString(app)
  return { app, setupState }
}

describe('Phase 3 Vendor Views Final Runtime Correction Tests', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // 1. Proof of Synchronous setup() without top-level await / Suspense
  it('proves setup() is synchronous (Function, not AsyncFunction) for all 4 vendor views', () => {
    expect(typeof InventoryAuditView.setup).toBe('function')
    expect(InventoryAuditView.setup.constructor.name).toBe('Function')
    expect(InventoryAuditView.setup.constructor.name).not.toBe('AsyncFunction')

    expect(typeof StockTransferView.setup).toBe('function')
    expect(StockTransferView.setup.constructor.name).toBe('Function')
    expect(StockTransferView.setup.constructor.name).not.toBe('AsyncFunction')

    expect(typeof LiveEditorView.setup).toBe('function')
    expect(LiveEditorView.setup.constructor.name).toBe('Function')
    expect(LiveEditorView.setup.constructor.name).not.toBe('AsyncFunction')

    expect(typeof MultiDevicePreviewView.setup).toBe('function')
    expect(MultiDevicePreviewView.setup.constructor.name).toBe('Function')
    expect(MultiDevicePreviewView.setup.constructor.name).not.toBe('AsyncFunction')
  })

  // 2. Proof of per_page: 100 selector requirement
  it('InventoryAuditView and StockTransferView fetch vendor books with per_page: 100', async () => {
    const getSpy = vi.spyOn(apiClient, 'get').mockImplementation((url) => {
      if (url === '/api/vendor/inventory/audits') return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url === '/api/vendor/inventory/transfers') return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url === '/api/vendor/warehouses') return Promise.resolve({ data: [] })
      if (url === '/api/vendor/books') return Promise.resolve({ data: { data: [] } })
      return Promise.reject(new Error('Unknown endpoint'))
    })

    const { setupState: auditState } = mountAndCapture(InventoryAuditView)
    await auditState.fetchAudits()
    expect(getSpy).toHaveBeenCalledWith('/api/vendor/books', { params: { per_page: 100 } })

    getSpy.mockClear()

    const { setupState: transferState } = mountAndCapture(StockTransferView)
    await transferState.fetchTransfers()
    expect(getSpy).toHaveBeenCalledWith('/api/vendor/books', { params: { per_page: 100 } })
  })

  // 3. Error state & complete data cleanup (main list, warehouses, books) on failure
  it('InventoryAuditView sets error state and clears all list, warehouses, and books on API error', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network failure'))

    const { setupState } = mountAndCapture(InventoryAuditView)
    await setupState.fetchAudits()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toContain('Không thể kết nối API kiểm kê kho.')
    expect(setupState.audits.value).toEqual([])
    expect(setupState.warehouses.value).toEqual([])
    expect(setupState.books.value).toEqual([])
  })

  it('StockTransferView sets error state and clears all list, warehouses, and books on API error', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network failure'))

    const { setupState } = mountAndCapture(StockTransferView)
    await setupState.fetchTransfers()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toContain('Không thể kết nối API điều chuyển kho.')
    expect(setupState.transfers.value).toEqual([])
    expect(setupState.warehouses.value).toEqual([])
    expect(setupState.books.value).toEqual([])
  })

  it('LiveEditorView sets error state on API rejection and does not populate draft chapter', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network failure'))

    const { setupState } = mountAndCapture(LiveEditorView)
    await setupState.fetchChapters()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toContain('Không thể kết nối API chương sách.')
    expect(setupState.chapters.value).toEqual([])
  })

  it('MultiDevicePreviewView sets error state on API rejection', async () => {
    vi.spyOn(apiClient, 'get').mockRejectedValue(new Error('Network failure'))

    const { setupState } = mountAndCapture(MultiDevicePreviewView)
    await setupState.fetchBookData()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toContain('Không thể kết nối API thông tin sách.')
    expect(setupState.chapters.value).toEqual([])
  })

  // 4. Honest Empty state (distinct from Error state)
  it('InventoryAuditView sets honest empty state when audits array is empty', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation((url) => {
      if (url === '/api/vendor/inventory/audits') return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url === '/api/vendor/warehouses') return Promise.resolve({ data: [] })
      if (url.includes('/api/vendor/books')) return Promise.resolve({ data: { data: [] } })
      return Promise.reject(new Error('Unknown endpoint'))
    })

    const { setupState } = mountAndCapture(InventoryAuditView)
    await setupState.fetchAudits()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toBeNull()
    expect(setupState.audits.value).toEqual([])
  })

  it('StockTransferView sets honest empty state when transfers array is empty', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation((url) => {
      if (url === '/api/vendor/inventory/transfers') return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url === '/api/vendor/warehouses') return Promise.resolve({ data: [] })
      if (url.includes('/api/vendor/books')) return Promise.resolve({ data: { data: [] } })
      return Promise.reject(new Error('Unknown endpoint'))
    })

    const { setupState } = mountAndCapture(StockTransferView)
    await setupState.fetchTransfers()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toBeNull()
    expect(setupState.transfers.value).toEqual([])
  })

  it('LiveEditorView turns an empty chapter response into one honest unsaved draft', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation((url) => {
      if (url.includes('/chapters')) return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url.includes('/api/vendor/books/')) return Promise.resolve({ data: { data: { title: 'Sách mới' } } })
      return Promise.reject(new Error('Unknown endpoint'))
    })

    const { setupState } = mountAndCapture(LiveEditorView)
    await setupState.fetchChapters()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toBeNull()
    expect(setupState.chapters.value).toHaveLength(1)
    expect(setupState.chapters.value[0]).toMatchObject({
      id: null,
      order: 1,
      is_free: false,
    })
    expect(setupState.activeChapterIndex.value).toBe(0)
  })

  it('MultiDevicePreviewView sets honest empty state when chapters array is empty', async () => {
    vi.spyOn(apiClient, 'get').mockImplementation((url) => {
      if (url.includes('/chapters')) return Promise.resolve({ data: { status: 'success', data: [] } })
      if (url.includes('/api/vendor/books/')) return Promise.resolve({ data: { data: { title: 'Sách mới' } } })
      return Promise.reject(new Error('Unknown endpoint'))
    })

    const { setupState } = mountAndCapture(MultiDevicePreviewView)
    await setupState.fetchBookData()

    expect(setupState.loading.value).toBe(false)
    expect(setupState.error.value).toBeNull()
    expect(setupState.chapters.value).toEqual([])
  })

  // 5. Real Retry handler on the SAME component instance
  it('retry button handler (fetchAudits) re-invokes API request on the SAME component instance without remounting', async () => {
    const getSpy = vi.spyOn(apiClient, 'get')
      // Mount call fails
      .mockRejectedValueOnce(new Error('Initial connection failure'))
      // Retry call succeeds
      .mockResolvedValueOnce({ data: { status: 'success', data: [{ id: 99 }] } })
      .mockResolvedValueOnce({ data: [{ id: 1 }] })
      .mockResolvedValueOnce({ data: { data: [{ id: 5, type: 'physical' }] } })

    const { setupState } = mountAndCapture(InventoryAuditView)

    // Initial fetch fails
    await setupState.fetchAudits()
    expect(setupState.error.value).toContain('Không thể kết nối API kiểm kê kho.')
    expect(setupState.audits.value).toEqual([])
    const initialCallsCount = getSpy.mock.calls.length
    expect(initialCallsCount).toBe(1)

    // Trigger retry handler directly on the SAME setupState instance
    await setupState.fetchAudits()

    // Verify retry succeeded on the same instance without remounting
    expect(getSpy.mock.calls.length).toBeGreaterThan(initialCallsCount)
    expect(setupState.error.value).toBeNull()
    expect(setupState.audits.value).toHaveLength(1)
    expect(setupState.audits.value[0].id).toBe(99)
  })

  it('retry button handler (fetchTransfers) re-invokes API request on the SAME component instance without remounting', async () => {
    const getSpy = vi.spyOn(apiClient, 'get')
      .mockRejectedValueOnce(new Error('Initial connection failure'))
      .mockResolvedValueOnce({ data: { status: 'success', data: [{ id: 88 }] } })
      .mockResolvedValueOnce({ data: [{ id: 1 }, { id: 2 }] })
      .mockResolvedValueOnce({ data: { data: [{ id: 5, type: 'physical' }] } })

    const { setupState } = mountAndCapture(StockTransferView)

    await setupState.fetchTransfers()
    expect(setupState.error.value).toContain('Không thể kết nối API điều chuyển kho.')
    expect(setupState.transfers.value).toEqual([])
    const initialCallsCount = getSpy.mock.calls.length
    expect(initialCallsCount).toBe(1)

    await setupState.fetchTransfers()

    expect(getSpy.mock.calls.length).toBeGreaterThan(initialCallsCount)
    expect(setupState.error.value).toBeNull()
    expect(setupState.transfers.value).toHaveLength(1)
    expect(setupState.transfers.value[0].id).toBe(88)
  })
})
