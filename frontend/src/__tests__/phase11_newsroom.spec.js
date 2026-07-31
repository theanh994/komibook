import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const source = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8')

describe('Phase 11 Newsroom contracts', () => {
  it('separates management, editor and moderation pages', () => {
    const router = source('../router/index.js')
    expect(router).toContain("name: 'admin-article-create'")
    expect(router).toContain("name: 'vendor-articles'")
    expect(router).toContain("name: 'admin-article-comments'")
    expect(router).toContain("name: 'admin-article-submissions'")
  })

  it('provides a rich editor with autosave, preview and revision history', () => {
    const editor = source('../views/shared/ArticleEditorView.vue')
    expect(editor).toContain("from 'quill'")
    expect(editor).toContain('localStorage.setItem')
    expect(editor).toContain('Xem trước bài viết')
    expect(editor).toContain('Lịch sử phiên bản')
  })

  it('keeps public article HTML sanitized and comments moderated', () => {
    const article = source('../views/ArticleView.vue')
    expect(article).toContain('DOMPurify.sanitize')
    expect(article).toContain('/comments')
    expect(article).toContain('được kiểm duyệt trước')
    expect(article).toContain('Bài viết liên quan')
  })

  it('does not publish community reviews directly', () => {
    const queue = source('../views/admin/ArticleSubmissionsView.vue')
    const contribute = source('../views/ReviewSubmissionView.vue')
    expect(queue).toContain('chuyển thành bản nháp')
    expect(contribute).toContain('/api/article-submissions')
    expect(contribute).toContain("from 'quill'")
    expect(contribute).toContain('filteredBooks')
  })

  it('combines product reviews and article comments in one moderation workspace', () => {
    const moderation = source('../views/admin/ContentModerationView.vue')
    const layout = source('../layouts/AdminLayout.vue')
    expect(moderation).toContain('ReviewModerationView')
    expect(moderation).toContain('ArticleCommentsView')
    expect(layout).toContain('/admin/moderation')
  })

  it('keeps management navigation unique and exposes dense review filters', () => {
    const layout = source('../layouts/AdminLayout.vue')
    const reviews = source('../views/admin/ReviewModerationView.vue')
    expect(layout.match(/route: '\/admin\/approvals'/g)).toHaveLength(1)
    expect(reviews).toContain('filters.rating')
    expect(reviews).toContain('filters.vendor_id')
    expect(reviews).toContain('filters.reported')
    expect(reviews).toContain('per_page: 10')
  })

  it('uses searchable product selection, inline product links and the revised home feed', () => {
    const editor = source('../views/shared/ArticleEditorView.vue')
    const home = source('../views/HomeView.vue')
    expect(editor).toContain('filteredBooks')
    expect(editor).toContain('applyProductLink')
    expect(editor).toContain('selectSeries')
    expect(home).toContain('upcomingFlashSale')
    expect(home).toContain('vendor_spotlights')
    expect(home).not.toContain("key: 'ebook-samples'")
  })

  it('keeps warehouse navigation native and the collapsed control clear of the logo', () => {
    const layout = source('../layouts/AdminLayout.vue')
    const warehouseManagers = source('../views/vendor/WarehouseManagersView.vue')
    expect(layout).not.toContain('@click.prevent="navigateTo')
    expect(layout).toContain('.sidebar-collapsed .sidebar-header')
    expect(layout).toContain('flex-direction: column')
    expect(warehouseManagers).toContain('onBeforeUnmount')
    expect(warehouseManagers).toContain('loadController?.abort()')
  })

  it('exposes seller registration and a complete storefront follow journey', () => {
    const header = source('../components/layout/AppHeader.vue')
    const registration = source('../views/auth/VendorRegisterView.vue')
    const storefront = source('../views/VendorStorefrontView.vue')
    const router = source('../router/index.js')
    expect(header).toContain('Đăng ký Nhà bán')
    expect(header).toContain('vendorRegistrationTarget')
    expect(registration).toContain('Đăng ký trở thành Nhà bán')
    expect(router).toContain("name: 'vendor-storefront'")
    expect(storefront).toContain('Theo dõi gian hàng')
    expect(storefront).toContain('/follow')
    expect(storefront).toContain('followers_count')
  })
})
