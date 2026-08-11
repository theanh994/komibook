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

  it('integrates warehouse and supply-chain selection into new-book creation', () => {
    const form = source('src/views/vendor/BookFormView.vue')

    expect(form).toContain('Kho & Chuỗi Cung Ứng')
    expect(form).toContain("apiClient.get('/api/vendor/books/create-scope')")
    expect(form).toContain('Bản in')
    expect(form).toContain('Tái bản lần')
    expect(form).toContain('primaryWarehouse')
    expect(form).toContain('Bản in đầu')
    expect(form).toContain('Bản in lần thứ mười')
    expect(form).toContain('customPrintEdition')
    expect(form).toContain("supplyChainMode === 'self_supplied'")
    expect(form).toContain("formData.append(key, value)")
    expect(form).toContain("await apiClient.post('/api/vendor/books', formData)")
    expect(form).toContain("name: 'vendor-warehouse-documents'")
    expect(form).toContain('Lưu thay đổi')
    expect(form).not.toContain("router.push(`/vendor/books/${bookId}/publishing`)")
    expect(form).not.toContain("headers: { 'Content-Type': 'multipart/form-data' }")
  })

  it('lets the targeted warehouse manager respond inside a notification', () => {
    const notifications = source('src/views/NotificationsView.vue')

    expect(notifications).toContain('warehouse_assignment_invitation')
    expect(notifications).toContain("respondToWarehouseInvitation(noti, 'accept')")
    expect(notifications).toContain("respondToWarehouseInvitation(noti, 'decline')")
    expect(notifications).toContain('/respond`')
  })

  it('localizes warehouse staff permissions and supports resending stuck invitations', () => {
    const managers = source('src/views/vendor/WarehouseManagersView.vue')

    expect(managers).toContain("{ label: 'Xem tồn kho', value: 'view_inventory' }")
    expect(managers).toContain("{ label: 'Nhập kho', value: 'receive_stock' }")
    expect(managers).toContain('capabilityLabels[capability]')
    expect(managers).toContain('Gửi lại lời mời')
    expect(managers).toContain('/resend`')
    expect(managers).toContain('Chờ phản hồi')
  })

  it('links warehouse adjustments and transfers to auditable documents', () => {
    const warehouses = source('src/views/vendor/WarehousesView.vue')
    const documents = source('src/views/warehouse-manager/DocumentsView.vue')

    expect(warehouses).toContain("openWarehouseDocument('transfer')")
    expect(warehouses).toContain("openWarehouseDocument('count')")
    expect(warehouses).toContain("name: 'vendor-warehouse-documents'")
    expect(documents).toContain('Đã liên kết từ trang Quản lý kho')
    expect(documents).toContain('route.query.warehouse_id')
    expect(documents).toContain('scope.can_transfer')
    expect(documents).toContain("downloadDocument(document, 'pdf')")
    expect(documents).toContain("downloadDocument(document, 'excel')")
    expect(documents).toContain('Xét & duyệt phiếu')
    expect(documents).toContain('Kiểm tra & ghi sổ')
    expect(documents).toContain('reviewDialogVisible')
    expect(documents).not.toContain('window.prompt')
  })

  it('moves simulation and compact helper explanations into accessible info tooltips', () => {
    const detail = source('src/views/BookDetailView.vue')
    const warehouses = source('src/views/vendor/WarehousesView.vue')
    const documents = source('src/views/warehouse-manager/DocumentsView.vue')
    const infoTip = source('src/components/InfoTip.vue')

    expect(detail).toContain('<InfoTip v-if="party.is_demo"')
    expect(detail).toContain('không phải xác minh quan hệ pháp lý')
    expect(detail).toContain('handleGalleryImageError')
    expect(detail).toContain('failedGalleryImagePaths')
    expect(detail).toContain('Ảnh đang được cập nhật')
    expect(detail).toContain('markRelatedCoverBroken')
    expect(warehouses).toContain('<InfoTip')
    expect(documents).toContain('<InfoTip')
    expect(infoTip).toContain('h-5 w-5')
    expect(infoTip).toContain('!text-[16px]')
    expect(infoTip).not.toContain('h-11 w-11')
  })

  it('integrates document cancellation and draft document editing into warehouse documents view', () => {
    const documents = source('src/views/warehouse-manager/DocumentsView.vue')

    expect(documents).toContain('Sửa phiếu nháp')
    expect(documents).toContain('Hủy phiếu')
    expect(documents).toContain('openEditDrawer')
    expect(documents).toContain("requestTransition(selectedDocument, 'cancelled')")
    expect(documents).toContain("pendingTransition?.toStatus === 'cancelled'")
    expect(documents).toContain('Thao tác')
  })
})
