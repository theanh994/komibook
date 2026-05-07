<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Divider from 'primevue/divider'
import Menu from 'primevue/menu'

const toast = useToast()

// ─── State ───
const orders = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyParams = ref({ first: 0, rows: 15, page: 1 })
const selectedOrders = ref([])

// Filter Status
const filterStatus = ref('all')
const filterStatusOptions = [
  { label: 'Tất cả trạng thái', value: 'all' },
  { label: 'Chờ xử lý', value: 'pending' },
  { label: 'Đang xử lý', value: 'processing' },
  { label: 'Đang giao hàng', value: 'shipped' },
  { label: 'Hoàn thành', value: 'completed' },
  { label: 'Đã hủy', value: 'cancelled' }
]

// Column toggler
const availableColumns = [
  { field: 'order_code', header: 'Mã đơn' },
  { field: 'created_at', header: 'Ngày đặt' },
  { field: 'user', header: 'Khách hàng' },
  { field: 'total_amount', header: 'Tổng tiền' },
  { field: 'status', header: 'Trạng thái' }
]
const selectedColumns = ref([...availableColumns])
const showColumn = (field) => selectedColumns.value.some(c => c.field === field)

// Detail dialog
const detailDialog = ref(false)
const selectedOrder = ref(null)
const detailLoading = ref(false)
const updatingStatus = ref(false)
const newStatus = ref(null)

// ─── Status Config ───
const statusMap = {
  pending:    { label: 'Chờ xử lý',    severity: 'warn',      icon: 'pi pi-clock' },
  processing: { label: 'Đang xử lý',   severity: 'info',      icon: 'pi pi-spin pi-spinner' },
  shipped:    { label: 'Đang giao',     severity: 'secondary', icon: 'pi pi-truck' },
  completed:  { label: 'Hoàn thành',    severity: 'success',   icon: 'pi pi-check-circle' },
  cancelled:  { label: 'Đã hủy',       severity: 'danger',    icon: 'pi pi-times-circle' },
}

const statusOptions = [
  { label: 'Đang xử lý', value: 'processing' },
  { label: 'Đang giao hàng', value: 'shipped' },
  { label: 'Hoàn thành', value: 'completed' },
  { label: 'Hủy đơn', value: 'cancelled' },
]

const getStatus = (status) => statusMap[status] || statusMap.pending
const isTerminal = computed(() => {
  if (!selectedOrder.value) return true
  return ['completed', 'cancelled'].includes(selectedOrder.value.status)
})

// ─── Formatters ───
const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

// ─── API Calls ───
const fetchOrders = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/vendor/orders', {
      params: { 
        page: lazyParams.value.page, 
        per_page: lazyParams.value.rows,
        status: filterStatus.value
      },
    })
    orders.value = res.data.data
    totalRecords.value = res.data.meta?.total || res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách đơn hàng.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDetail = async (order) => {
  detailDialog.value = true
  detailLoading.value = true
  newStatus.value = null
  try {
    const res = await apiClient.get(`/api/vendor/orders/${order.id}`)
    selectedOrder.value = res.data.data
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải chi tiết đơn hàng.', life: 3000 })
    detailDialog.value = false
  } finally {
    detailLoading.value = false
  }
}

const updateStatus = async () => {
  if (!newStatus.value || !selectedOrder.value) return
  updatingStatus.value = true
  try {
    await apiClient.patch(`/api/vendor/orders/${selectedOrder.value.id}/status`, {
      status: newStatus.value,
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật trạng thái đơn hàng!', life: 3000 })
    selectedOrder.value.status = newStatus.value
    newStatus.value = null
    fetchOrders() // Reload danh sách ngoài
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể cập nhật trạng thái.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    updatingStatus.value = false
  }
}

// ─── Bulk Actions ───
const bulkUpdatingStatus = ref(false)
const bulkNewStatus = ref(null)

const bulkUpdateStatus = async () => {
  if (!bulkNewStatus.value || selectedOrders.value.length === 0) return
  
  // Lọc ra các đơn hàng hợp lệ (không ở trạng thái cuối)
  const validOrderIds = selectedOrders.value
    .filter(o => !['completed', 'cancelled'].includes(o.status))
    .map(o => o.id)
    
  if (validOrderIds.length === 0) {
    toast.add({ severity: 'warn', summary: 'Lưu ý', detail: 'Các đơn hàng đã chọn đều không thể cập nhật (đã hủy/hoàn thành).', life: 4000 })
    return
  }

  bulkUpdatingStatus.value = true
  try {
    const res = await apiClient.patch('/api/vendor/orders/bulk-status', {
      order_ids: validOrderIds,
      status: bulkNewStatus.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: res.data.message, life: 3000 })
    
    // Cập nhật state nội bộ để UI phản ứng ngay
    selectedOrders.value.forEach(o => {
      if (validOrderIds.includes(o.id)) o.status = bulkNewStatus.value
    })
    selectedOrders.value = []
    bulkNewStatus.value = null
  } catch (e) {
    const msg = e.response?.data?.message || 'Không thể cập nhật hàng loạt.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
  } finally {
    bulkUpdatingStatus.value = false
  }
}

const onPage = (event) => {
  lazyParams.value = { ...event, page: event.page + 1 }
  fetchOrders()
}

onMounted(fetchOrders)

// Action Menu
const actionMenuRef = ref()
const activeOrderRow = ref(null)

const orderActionItems = [
  {
    label: 'Xem chi tiết',
    icon: 'pi pi-eye',
    command: () => {
      if (activeOrderRow.value) openDetail(activeOrderRow.value)
    }
  }
]

const toggleOrderMenu = (event, data) => {
  activeOrderRow.value = data
  actionMenuRef.value.toggle(event)
}
</script>

<template>
  <div class="vendor-orders">
    <!-- Page Header -->
    <div class="page-header flex justify-between items-end">
      <div>
        <h1 class="page-title">Quản lý Đơn hàng</h1>
        <p class="page-subtitle">Theo dõi và cập nhật trạng thái các đơn hàng của gian hàng</p>
      </div>
    </div>

    <!-- Toolbar Filters & Bulk Actions -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex flex-wrap items-center gap-4">
        <Select 
          v-model="filterStatus" 
          :options="filterStatusOptions" 
          optionLabel="label" 
          optionValue="value" 
          @change="() => { lazyParams.page = 1; lazyParams.first = 0; fetchOrders() }"
          class="w-full md:w-56"
        />
        <MultiSelect 
          v-model="selectedColumns" 
          :options="availableColumns" 
          optionLabel="header" 
          placeholder="Chọn thông tin hiển thị"
          :maxSelectedLabels="3" 
          class="w-full md:w-64"
        />
      </div>

      <!-- Bulk Action Panel -->
      <div v-if="selectedOrders.length > 0" class="flex items-center gap-3 bg-indigo-50 px-4 py-2 rounded-xl border border-indigo-100 shadow-sm transition-all">
        <span class="text-indigo-800 text-sm font-semibold">Đã chọn {{ selectedOrders.length }}</span>
        <Select
          v-model="bulkNewStatus"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chuyển trạng thái..."
          class="w-48"
          size="small"
        />
        <Button
          label="Áp dụng"
          icon="pi pi-check"
          size="small"
          :loading="bulkUpdatingStatus"
          :disabled="!bulkNewStatus"
          @click="bulkUpdateStatus"
          class="btn-primary"
        />
      </div>
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
      <DataTable
        :value="orders"
        v-model:selection="selectedOrders"
        :loading="loading"
        :paginator="true"
        :rows="lazyParams.rows"
        :totalRecords="totalRecords"
        :lazy="true"
        :rowsPerPageOptions="[15, 30, 50]"
        @page="onPage"
        dataKey="id"
        stripedRows
        class="order-table"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-inbox"></i>
            <p>Chưa có đơn hàng nào.</p>
          </div>
        </template>

        <!-- Cột Selection Checkbox -->
        <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

        <!-- Mã đơn -->
        <Column v-if="showColumn('order_code')" header="Mã đơn" style="min-width: 180px">
          <template #body="{ data }">
            <span class="order-code">{{ data.order_code }}</span>
          </template>
        </Column>

        <!-- Ngày đặt -->
        <Column v-if="showColumn('created_at')" header="Ngày đặt" style="min-width: 160px" sortable field="created_at">
          <template #body="{ data }">
            <span class="date-text">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Khách hàng -->
        <Column v-if="showColumn('user')" header="Khách hàng" style="min-width: 160px">
          <template #body="{ data }">
            <div class="customer-cell">
              <div class="customer-avatar">
                <i class="pi pi-user"></i>
              </div>
              <span class="customer-name">{{ data.user?.name || '—' }}</span>
            </div>
          </template>
        </Column>

        <!-- Tổng tiền -->
        <Column v-if="showColumn('total_amount')" header="Tổng tiền" style="min-width: 130px" sortable field="total_amount">
          <template #body="{ data }">
            <span class="amount-text">{{ formatPrice(data.total_amount) }}</span>
          </template>
        </Column>

        <!-- Trạng thái -->
        <Column v-if="showColumn('status')" header="Trạng thái" style="min-width: 140px">
          <template #body="{ data }">
            <Tag
              :severity="getStatus(data.status).severity"
              :value="getStatus(data.status).label"
              :icon="getStatus(data.status).icon"
              rounded
            />
          </template>
        </Column>

        <!-- Hành động -->
        <Column header="" style="min-width: 70px; text-align: right">
          <template #body="{ data }">
            <Button
              icon="pi pi-ellipsis-v"
              text
              rounded
              severity="secondary"
              @click="(e) => toggleOrderMenu(e, data)"
              v-tooltip.top="'Tác vụ'"
            />
          </template>
        </Column>
      </DataTable>

      <!-- Action Menu -->
      <Menu ref="actionMenuRef" :model="orderActionItems" :popup="true" />
    </div>

    <!-- ═══ ORDER DETAIL DIALOG ═══ -->
    <Dialog
      v-model:visible="detailDialog"
      header="Chi tiết đơn hàng"
      modal
      :draggable="false"
      class="order-detail-dialog"
      :style="{ width: '680px' }"
    >
      <!-- Loading skeleton -->
      <div v-if="detailLoading" class="detail-loading">
        <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: #6366f1;"></i>
        <p>Đang tải...</p>
      </div>

      <template v-else-if="selectedOrder">
        <!-- Order Code Badge -->
        <div class="detail-header-badge">
          <span class="detail-order-code">{{ selectedOrder.order_code }}</span>
          <Tag
            :severity="getStatus(selectedOrder.status).severity"
            :value="getStatus(selectedOrder.status).label"
            :icon="getStatus(selectedOrder.status).icon"
            rounded
          />
        </div>

        <Divider />

        <!-- Customer Info -->
        <div class="detail-section">
          <h3 class="section-title">
            <i class="pi pi-user"></i> Thông tin người mua
          </h3>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Họ tên</span>
              <span class="info-value">{{ selectedOrder.user?.name || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email</span>
              <span class="info-value">{{ selectedOrder.user?.email || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Điện thoại</span>
              <span class="info-value">{{ selectedOrder.phone || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Địa chỉ</span>
              <span class="info-value">{{ selectedOrder.shipping_address || '—' }}</span>
            </div>
          </div>
        </div>

        <Divider />

        <!-- Order Items -->
        <div class="detail-section">
          <h3 class="section-title">
            <i class="pi pi-list"></i> Sản phẩm ({{ selectedOrder.items?.length || 0 }})
          </h3>
          <div class="items-list">
            <div v-for="item in selectedOrder.items" :key="item.id" class="order-item">
              <div class="item-cover">
                <img v-if="item.book?.cover_image" :src="item.book.cover_image" :alt="item.book?.title" />
                <div v-else class="item-cover-placeholder">
                  <i class="pi pi-image"></i>
                </div>
              </div>
              <div class="item-info">
                <span class="item-title">{{ item.book?.title || 'Sách không xác định' }}</span>
                <span class="item-meta">
                  {{ formatPrice(item.price) }} × {{ item.quantity }}
                </span>
              </div>
              <div class="item-subtotal">
                {{ formatPrice(item.price * item.quantity) }}
              </div>
            </div>
          </div>

          <!-- Total -->
          <div class="order-total">
            <span>Tổng cộng</span>
            <span class="total-amount">{{ formatPrice(selectedOrder.total_amount) }}</span>
          </div>
        </div>

        <Divider />

        <!-- Status Update -->
        <div class="detail-section">
          <h3 class="section-title">
            <i class="pi pi-sync"></i> Cập nhật trạng thái
          </h3>

          <div v-if="isTerminal" class="terminal-notice">
            <i class="pi pi-info-circle"></i>
            Đơn hàng đã ở trạng thái cuối, không thể cập nhật thêm.
          </div>

          <div v-else class="status-update-row">
            <Select
              v-model="newStatus"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Chọn trạng thái mới..."
              class="status-select"
            />
            <Button
              label="Cập nhật"
              icon="pi pi-check"
              :loading="updatingStatus"
              :disabled="!newStatus"
              class="btn-primary"
              @click="updateStatus"
            />
          </div>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.vendor-orders {
  max-width: 1200px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  margin-bottom: 24px;
}
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin: 0;
}
.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}

/* Table Card */
.table-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* Order code */
.order-code {
  font-weight: 700;
  font-size: 13px;
  color: #0f172a;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  background: #f1f5f9;
  padding: 4px 10px;
  border-radius: 6px;
  letter-spacing: 0.02em;
}

.date-text {
  font-size: 13px;
  color: #475569;
}

/* Customer cell */
.customer-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.customer-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6366f1;
  font-size: 12px;
}
.customer-name {
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
}

.amount-text {
  font-weight: 600;
  color: #0f172a;
  font-size: 13px;
}

/* Empty state */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: #94a3b8;
}
.empty-state i {
  font-size: 48px;
  margin-bottom: 12px;
  display: block;
}

/* ═══ DETAIL DIALOG ═══ */
.detail-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px;
  color: #64748b;
}

.detail-header-badge {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.detail-order-code {
  font-weight: 700;
  font-size: 16px;
  color: #0f172a;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  letter-spacing: 0.02em;
}

/* Sections */
.detail-section {
  padding: 4px 0;
}
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  margin: 0 0 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-title i {
  color: #6366f1;
  font-size: 14px;
}

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.info-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
}
.info-value {
  font-size: 14px;
  color: #1e293b;
  font-weight: 500;
}

/* Items list */
.items-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.order-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}
.item-cover {
  width: 40px;
  height: 54px;
  min-width: 40px;
  border-radius: 6px;
  overflow: hidden;
  background: #e2e8f0;
}
.item-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.item-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 16px;
}
.item-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.item-title {
  font-weight: 600;
  font-size: 13px;
  color: #1e293b;
}
.item-meta {
  font-size: 12px;
  color: #94a3b8;
}
.item-subtotal {
  font-weight: 600;
  font-size: 14px;
  color: #0f172a;
  white-space: nowrap;
}

/* Order total */
.order-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  margin-top: 12px;
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  border-radius: 10px;
  font-weight: 600;
  color: #3730a3;
}
.total-amount {
  font-size: 18px;
  font-weight: 700;
}

/* Status update */
.status-update-row {
  display: flex;
  gap: 12px;
  align-items: center;
}
.status-select {
  flex: 1;
}

.terminal-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  color: #64748b;
  font-size: 13px;
}

.btn-primary {
  background: linear-gradient(to bottom, #6366f1, #4f46e5) !important;
  border: none !important;
  color: white !important;
  font-weight: 600 !important;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3) !important;
  transition: all 0.2s ease !important;
}
.btn-primary:hover {
  box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4) !important;
  transform: translateY(-1px);
}

@media (max-width: 640px) {
  .info-grid {
    grid-template-columns: 1fr;
  }
  .status-update-row {
    flex-direction: column;
  }
  .detail-header-badge {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
