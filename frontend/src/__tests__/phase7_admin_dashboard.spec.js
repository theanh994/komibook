import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../views/admin/DashboardView.vue', import.meta.url), 'utf8')

describe('Phase 7 admin dashboard intelligence', () => {
  it('renders every real dashboard contract without runtime mock values', () => {
    expect(source).toContain("apiClient.get('/api/admin/stats')")
    expect(source).toContain('commerce_trend')
    expect(source).toContain('account_growth')
    expect(source).toContain('book_distribution')
    expect(source).toContain('order_status_distribution')
    expect(source).toContain('operational_queues')
    expect(source).not.toContain('Math.random')
    expect(source).not.toContain('mockData')
  })

  it('provides accessible chart descriptions and data-table alternatives', () => {
    expect(source).toContain('role="img"')
    expect(source).toContain('aria-label="Biểu đồ doanh thu và đơn hoàn thành trong sáu tháng"')
    expect(source).toContain('Xem bảng dữ liệu doanh thu')
    expect(source).toContain('Xem bảng dữ liệu tài khoản')
    expect(source).toContain('<table>')
    expect(source).toContain('Không thể tải dữ liệu điều hành. Vui lòng thử lại.')
  })

  it('uses KomiBook semantic tokens and responsive grids', () => {
    expect(source).toContain('ui-panel')
    expect(source).toContain('bg-primary')
    expect(source).toContain('bg-secondary')
    expect(source).toContain('bg-commerce')
    expect(source).toContain('sm:grid-cols-2')
    expect(source).toContain('xl:grid-cols-2')
  })
})
