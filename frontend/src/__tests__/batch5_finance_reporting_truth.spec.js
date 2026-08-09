import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const view = readFileSync(new URL('../views/admin/FinanceReportView.vue', import.meta.url), 'utf8')

describe('Batch 5 finance reporting truth', () => {
  it('renders null financial values as unknown instead of zero', () => {
    expect(view).toContain("const formatAmount = (val) => (val === null || val === undefined ? '—' : formatVND(val))")
    expect(view).toContain('formatAmount(row.gross_revenue)')
    expect(view).toContain('formatAmount(row.refund_amount)')
  })

  it('has an explicit unavailable state and never invents a fixed platform rate', () => {
    expect(view).toContain("reportStatus === 'unavailable'")
    expect(view).toContain('Chưa có dữ liệu')
    expect(view).not.toContain('10.0%')
    expect(view).not.toContain('0.08')
  })

  it('labels the commission card as after reversals instead of received before reversals', () => {
    expect(view).toContain("label: 'Hoa Hồng Sàn Sau Hoàn'")
    expect(view).toContain('value: formatAmount(k.platform_commission_retention)')
    expect(view).not.toContain("label: 'Hoa Hồng Sàn Thu Được'")
  })

  it('maps vendor net cards and monthly detail to the after-reversal amount', () => {
    const afterRefundsLabel = 'Doanh Thu R\u00f2ng Nh\u00e0 B\u00e1n Sau Ho\u00e0n'
    const afterRefundsDetailLabel = 'Doanh thu r\u00f2ng Nh\u00e0 b\u00e1n sau ho\u00e0n thu nh\u1eadp/\u0111i\u1ec1u ch\u1ec9nh:'
    expect(view).toContain(`label: '${afterRefundsLabel}'`)
    expect(view).toContain(`>${afterRefundsLabel}</th>`)
    expect(view).toContain(afterRefundsDetailLabel)
    expect(view).toContain('value: formatAmount(k.vendor_net_after_refunds)')
    expect(view).toContain('formatAmount(row.vendor_net_after_refunds)')
    expect(view).toContain('formatAmount(selectedMonthDetail.vendor_net_after_refunds)')
    expect(view).not.toContain('value: formatAmount(k.total_vendor_net)')
    expect(view).not.toContain('formatAmount(row.vendor_net_amount)')
    expect(view).not.toContain('formatAmount(selectedMonthDetail.vendor_net_amount)')
    expect(view).not.toContain('Doanh Thu Ròng Nhà Bán Thực Nhận')
  })

  it('sends the required refresh reason and idempotency key', () => {
    expect(view).toContain('idempotency_key: idempotencyKey')
    expect(view).toContain("'Idempotency-Key': idempotencyKey")
    expect(view).toContain("window.prompt('Nhập lý do làm mới báo cáo tài chính:')")
    expect(view).toContain('if (!reason) return')
    expect(view).toContain('reason,')
  })
})
