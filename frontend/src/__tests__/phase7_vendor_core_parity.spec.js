import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const source = (relativePath) => fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8')

describe('Phase 7E.1 Vendor core parity', () => {
  it('renders truthful dashboard and analytics states', () => {
    const dashboard = source('src/views/vendor/DashboardView.vue')
    const analytics = source('src/views/vendor/AnalyticsView.vue')

    expect(dashboard).toContain('Dữ liệu minh họa không được sử dụng thay thế')
    expect(dashboard).toContain('stats.recent_orders')
    expect(dashboard).toContain('/vendor/analytics')
    expect(analytics).toContain('book.cover_image')
    expect(analytics).toContain('errorMessage')
  })

  it('does not expose unbacked order-list actions', () => {
    const orders = source('src/views/vendor/OrdersView.vue')

    expect(orders).not.toContain('In hóa đơn')
    expect(orders).not.toContain('Xử lý nhanh')
    expect(orders).toContain('order.items[0].book.cover_image')
  })
})
