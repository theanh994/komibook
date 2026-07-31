import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

describe('partner commerce UI contracts', () => {
  it('provides a separate organization portal with accessible onboarding forms', () => {
    const source = read('../views/OrganizationPortalView.vue')
    expect(source).toContain('Tổ chức và quyền phân phối sách')
    expect(source).toContain('Đăng ký mở gian hàng')
    expect(source).toContain('Bước 1/2')
    expect(source).toContain('Bước 2/2')
    expect(source).toContain('aria-labelledby="organization-form-title"')
    expect(source).toContain('min-h-11')
  })

  it('does not allow payout bank details to be entered at withdrawal time', () => {
    const source = read('../views/vendor/FinanceView.vue')
    expect(source).toContain('Tài khoản nhận doanh thu')
    expect(source).toContain('payoutAccount.status !== \'verified\'')
    expect(source).not.toContain('v-model="withdrawForm.bank_name"')
    expect(source).not.toContain('v-model="withdrawForm.account_number"')
    expect(source).not.toContain('v-model="withdrawForm.account_name"')
  })

  it('exposes agreement moderation and an organization entry point', () => {
    const adminSource = read('../views/admin/OrganizationReviewsView.vue')
    const headerSource = read('../components/layout/AppHeader.vue')
    expect(adminSource).toContain('Thỏa thuận NXB – Nhà phân phối')
    expect(adminSource).toContain('/api/admin/distribution-agreements/')
    expect(headerSource).toContain('/organization-portal')
  })
})
