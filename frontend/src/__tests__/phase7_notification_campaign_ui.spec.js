import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const readView = (name) => readFileSync(new URL(`../views/admin/${name}.vue`, import.meta.url), 'utf8')

describe('Phase 7 notification campaign UI', () => {
  it('uses real paginator totals and responsive campaign cards', () => {
    const source = readView('NotificationCampaignsView')

    expect(source).toContain('allResponse.data?.total')
    expect(source).toContain('sentResponse.data?.total')
    expect(source).not.toContain('list.filter')
    expect(source).toContain("lg:grid-cols-[minmax(0,2fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_auto]")
    expect(source).toContain('ui-btn ui-btn-primary')
    expect(source).toContain('ui-panel')
  })

  it('keeps create form labels and audience controls keyboard accessible', () => {
    const source = readView('NotificationCreateView')

    expect(source).toContain('type="radio" name="target_audience"')
    expect(source).toContain('for="campaign-title-input"')
    expect(source).toContain('for="campaign-message"')
    expect(source).toContain('for="campaign-image"')
    expect(source).not.toContain('aria-label="Đường dẫn hình ảnh chiến dịch"\n              class="ui-field"\n            ></textarea>')
  })

  it('uses semantic campaign analytics states and preserves unavailable telemetry', () => {
    const source = readView('NotificationAnalyticsView')

    expect(source).toContain('bg-primary')
    expect(source).toContain('bg-commerce')
    expect(source).toContain('bg-warning')
    expect(source).toContain("analytics?.open_rate != null ? analytics.open_rate + '%' : '—'")
    expect(source).toContain('Chưa có dữ liệu theo dõi chi tiết')
    expect(source).not.toContain('bg-gradient-to-br from-indigo')
  })
})
