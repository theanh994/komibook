import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 4C.2 fee history UI contracts', () => {
  it('uses history and preview APIs with explicit effective time and reason', () => {
    const view = source('../views/admin/FeeSchedulesView.vue')
    expect(view).toContain('/api/admin/fee-schedules')
    expect(view).toContain('/preview')
    expect(view).toContain('effective_at')
    expect(view).toContain('reason')
    expect(view).toContain('operation_key')
  })

  it('removes mutable JSON financial inputs from general system configuration', () => {
    const view = source('../views/admin/SystemConfigView.vue')
    expect(view).not.toContain('commission_rate:')
    expect(view).not.toContain('service_fee:')
    expect(view).toContain('/admin/fee-schedules')
    expect(view).toContain('FeeSchedulesView')
    expect(view).toContain("section === 'fees'")
  })

  it('keeps the old deep link and removes the separate admin menu item', () => {
    const router = source('../router/index.js')
    const layout = source('../layouts/AdminLayout.vue')
    expect(router).toContain("path: 'fee-schedules'")
    expect(router).toContain("name: 'admin-system-config'")
    expect(router).toContain("section: 'fees'")
    expect(layout).not.toContain("route: '/admin/fee-schedules'")
  })

  it('explains every approved money-flow value and the tax boundary', () => {
    const view = source('../views/admin/FeeSchedulesView.vue')
    expect(view).toContain('Doanh thu gộp người bán')
    expect(view).toContain('Khách hàng thanh toán')
    expect(view).toContain('Người bán nhận ròng')
    expect(view).toContain('Nền tảng nhận trước thuế')
    expect(view).toContain('Thuế chưa được cấu hình')
    expect(view).toContain('seller_gross')
    expect(view).toContain('customer_pays')
    expect(view).toContain('seller_net')
    expect(view).toContain('platform_net_before_tax')
    expect(view).toContain('result.seller_gross ?? result.base_amount')
    expect(view).toContain('result.customer_pays ?? result.total_amount')
  })
})
