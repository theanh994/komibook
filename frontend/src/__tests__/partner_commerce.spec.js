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
    const finance = read('../views/vendor/FinanceView.vue')
    const wallet = read('../views/WalletView.vue')
    expect(finance).toContain('to="/wallet"')
    expect(wallet).toContain("data.value.payout_account?.status === 'verified'")
    expect(wallet).toContain('/api/wallet/withdrawals')
    expect(wallet).not.toContain('withdrawForm.bank_name')
    expect(wallet).not.toContain('withdrawForm.account_number')
    expect(wallet).not.toContain('withdrawForm.account_name')
  })

  it('exposes agreement moderation and an organization entry point', () => {
    const adminSource = read('../views/admin/OrganizationReviewsView.vue')
    const headerSource = read('../components/layout/AppHeader.vue')
    expect(adminSource).toContain('Thỏa thuận phân phối')
    expect(adminSource).toContain('/api/admin/distribution-agreements/')
    expect(headerSource).toContain('/organization-portal')
  })

  it('keeps demo-accepted relationships available for book supply-chain assignment', () => {
    const workflowSource = read('../views/vendor/PublishingWorkflowView.vue')
    const detailSource = read('../views/BookDetailView.vue')
    expect(workflowSource).toContain("['verified', 'demo_accepted'].includes(item.status)")
    expect(detailSource).toContain('Chưa khai báo chuỗi cung ứng')
    expect(detailSource).toContain('Dữ liệu mô phỏng – không xác minh pháp lý')
  })

  it('guides accepted partner profiles to the books that still need links', () => {
    const organizationSource = read('../views/vendor/OrganizationPartnersView.vue')
    const workflowSource = read('../views/vendor/PublishingWorkflowView.vue')
    expect(organizationSource).toContain('supply_chain')
    expect(organizationSource).toContain('unlinked_books')
    expect(organizationSource).toContain('vendor-book-publishing')
    expect(organizationSource).toContain('updateOpen')
    expect(workflowSource).toContain('hasDemoRelationship')
    expect(workflowSource).toContain('relationshipStatusLabel')
  })

  it('provides a filterable and accessible relationship management dashboard', () => {
    const adminSource = read('../views/admin/OrganizationReviewsView.vue')
    expect(adminSource).toContain('sectionOptions')
    expect(adminSource).toContain('role="tablist"')
    expect(adminSource).toContain('Sách thiếu liên kết')
    expect(adminSource).toContain('Lý do quyết định')
    expect(adminSource).not.toContain('window.prompt')
  })
})
