import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import process from 'node:process'

const source = (relativePath) => fs.readFileSync(path.resolve(process.cwd(), relativePath), 'utf8')

describe('Phase 8 warehouse and commercial-party surfaces', () => {
  it('uses the distinct Vendor and Warehouse Manager document endpoints', () => {
    const documents = source('src/views/warehouse-manager/DocumentsView.vue')

    expect(documents).toContain("'/api/vendor/warehouse-document-scope'")
    expect(documents).toContain("'/api/vendor/warehouse-documents'")
    expect(documents).toContain("'/api/warehouse-manager/document-scope'")
    expect(documents).toContain("'/api/warehouse-manager/documents'")
    expect(documents).not.toContain('`${base.value}/documents`')
  })

  it('surfaces verified commercial parties on book detail without crowding book cards', () => {
    const detail = source('src/views/BookDetailView.vue')
    const card = source('src/components/BookCard.vue')

    expect(detail).toContain('Thông tin xuất bản và cung ứng')
    expect(detail).toContain('commercial_parties')
    expect(detail).toContain('organization-public')
    expect(card).not.toContain('commercial_parties')
  })

  it('prioritizes Warehouse Manager and has no retired actor entry point', () => {
    const header = source('src/components/layout/AppHeader.vue')
    const sidebar = source('src/components/profile/UserSidebar.vue')
    const guard = source('src/router/guard.js')

    expect(header).toContain('Không gian Quản kho')
    expect(header).not.toContain('Kênh sáng tác Tác giả')
    expect(sidebar).toContain('Mở trang Quản kho')
    expect(sidebar).not.toContain('Chuyển Sang Kênh Tác Giả')
    expect(guard).not.toContain('isAuthor')
  })

  it('explains the public organization path and submits public and private files separately', () => {
    const organizations = source('src/views/vendor/OrganizationPartnersView.vue')

    expect(organizations).toContain('Đường dẫn hồ sơ công khai')
    expect(organizations).toContain('Website chính thức')
    expect(organizations).toContain("payload.append('logo', logoFile.value)")
    expect(organizations).toContain("payload.append('verification_document', verificationDocument.value)")
    expect(organizations).toContain('khách hàng không thể xem hoặc tải xuống')
  })
})
