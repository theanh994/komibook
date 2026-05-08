<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker' // PrimeVue 4 naming
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

const toast = useToast()

// ─── State ───
const coupons = ref([])
const categories = ref([])
const loading = ref(false)
const couponDialog = ref(false)
const deleteCouponDialog = ref(false)
const coupon = ref({})
const submitted = ref(false)

// ─── Options ───
const categoryOptions = ref([])

// ─── API Calls ───
const fetchCoupons = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/coupons')
    coupons.value = res.data.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách mã giảm giá.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const res = await apiClient.get('/api/categories')
    categories.value = res.data.data
    categoryOptions.value = [
      { label: 'Toàn sàn', value: null },
      ...categories.value.map(c => ({ label: c.name, value: c.id }))
    ]
  } catch (e) {
    console.error('Failed to fetch categories', e)
  }
}

const saveCoupon = async () => {
  submitted.value = true

  if (coupon.value.code && coupon.value.discount_percent >= 0) {
    loading.value = true
    try {
      if (coupon.value.id) {
        await apiClient.put(`/api/admin/coupons/${coupon.value.id}`, coupon.value)
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật mã giảm giá', life: 3000 })
      } else {
        await apiClient.post('/api/admin/coupons', coupon.value)
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã tạo mã giảm giá mới', life: 3000 })
      }
      couponDialog.value = false
      coupon.value = {}
      fetchCoupons()
    } catch (e) {
      const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu mã giảm giá.'
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
    } finally {
      loading.value = false
    }
  }
}

const editCoupon = (c) => {
  coupon.value = { 
    ...c, 
    start_time: c.start_time ? new Date(c.start_time) : null,
    end_time: c.end_time ? new Date(c.end_time) : null
  }
  couponDialog.value = true
}

const confirmDeleteCoupon = (c) => {
  coupon.value = c
  deleteCouponDialog.value = true
}

const deleteCoupon = async () => {
  loading.value = true
  try {
    await apiClient.delete(`/api/admin/coupons/${coupon.value.id}`)
    deleteCouponDialog.value = false
    coupon.value = {}
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã xóa mã giảm giá', life: 3000 })
    fetchCoupons()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa mã giảm giá.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openNew = () => {
  coupon.value = {
    code: '',
    discount_percent: 10,
    min_order_value: 0,
    max_discount_amount: null,
    usage_limit: 0,
    category_id: null,
    start_time: new Date(),
    end_time: new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000)
  }
  submitted.value = false
  couponDialog.value = true
}

// ─── Formatters ───
const formatCurrency = (value) => {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

const getStatusLabel = (data) => {
  const now = new Date()
  const start = data.start_time ? new Date(data.start_time) : null
  const end = data.end_time ? new Date(data.end_time) : null

  if (start && now < start) return { label: 'Sắp diễn ra', severity: 'warn' }
  if (end && now > end) return { label: 'Đã kết thúc', severity: 'danger' }
  return { label: 'Đang diễn ra', severity: 'success' }
}

onMounted(() => {
  fetchCoupons()
  fetchCategories()
})
</script>

<template>
  <div class="admin-coupons">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Khuyến mãi & Flash Sale</h1>
        <p class="page-subtitle">Tạo mã giảm giá theo thời gian và danh mục sản phẩm</p>
      </div>
      <Button label="Thêm mã mới" icon="pi pi-plus" severity="primary" @click="openNew" />
    </div>

    <!-- Data Table Card -->
    <div class="table-card">
      <DataTable
        :value="coupons"
        :loading="loading"
        stripedRows
        class="coupons-table"
        paginator :rows="10"
        responsiveLayout="scroll"
      >
        <template #empty>
          <div class="empty-state">
            <i class="pi pi-ticket"></i>
            <p>Chưa có mã giảm giá nào.</p>
          </div>
        </template>

        <Column field="code" header="Mã Code" style="min-width: 120px">
          <template #body="{ data }">
            <span class="coupon-code">{{ data.code }}</span>
          </template>
        </Column>

        <Column header="Giảm giá" style="min-width: 100px">
          <template #body="{ data }">
            <Tag severity="success" :value="data.discount_percent + '%'" />
          </template>
        </Column>

        <Column header="Áp dụng cho" style="min-width: 150px">
          <template #body="{ data }">
            <span v-if="data.category" class="category-tag">
              <i class="pi pi-tag mr-1"></i>
              {{ data.category.name }}
            </span>
            <span v-else class="global-tag">Toàn sàn</span>
          </template>
        </Column>

        <Column header="Thời gian" style="min-width: 250px">
          <template #body="{ data }">
            <div class="time-cell">
              <div class="time-range">
                <span>{{ formatDate(data.start_time) }}</span>
                <i class="pi pi-arrow-right mx-2 text-xs text-gray-400"></i>
                <span>{{ formatDate(data.end_time) }}</span>
              </div>
              <Tag :severity="getStatusLabel(data).severity" :value="getStatusLabel(data).label" class="mt-1" />
            </div>
          </template>
        </Column>

        <Column header="Lượt dùng" style="min-width: 120px">
          <template #body="{ data }">
            <div class="usage-cell">
              <span>{{ data.used_count }} / {{ data.usage_limit || '∞' }}</span>
            </div>
          </template>
        </Column>

        <Column header="Hành động" :exportable="false" style="min-width: 120px">
          <template #body="slotProps">
            <div class="flex gap-2">
              <Button icon="pi pi-pencil" outlined rounded severity="info" @click="editCoupon(slotProps.data)" />
              <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteCoupon(slotProps.data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Edit/Create Dialog -->
    <Dialog v-model:visible="couponDialog" :style="{ width: '500px' }" header="Chi tiết mã giảm giá" :modal="true" class="p-fluid">
      <div class="form-grid">
        <div class="field mb-4">
          <label for="code" class="font-bold">Mã Code</label>
          <InputText id="code" v-model.trim="coupon.code" required="true" autofocus :class="{ 'p-invalid': submitted && !coupon.code }" placeholder="Ví dụ: FLASH10" />
          <small class="p-error" v-if="submitted && !coupon.code">Bắt buộc nhập mã code.</small>
        </div>

        <div class="grid flex">
          <div class="field col-6 mr-2 flex-1">
            <label for="discount" class="font-bold">Phần trăm giảm (%)</label>
            <InputNumber id="discount" v-model="coupon.discount_percent" :min="0" :max="100" suffix="%" />
          </div>
          <div class="field col-6 flex-1">
            <label for="limit" class="font-bold">Giới hạn sử dụng</label>
            <InputNumber id="limit" v-model="coupon.usage_limit" :min="0" placeholder="0 = Không giới hạn" />
          </div>
        </div>

        <div class="field mb-4">
          <label for="category" class="font-bold">Danh mục áp dụng</label>
          <Select id="category" v-model="coupon.category_id" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Chọn danh mục" />
        </div>

        <div class="field mb-4">
          <label class="font-bold">Thời gian bắt đầu</label>
          <DatePicker v-model="coupon.start_time" showTime hourFormat="24" :manualInput="false" />
        </div>

        <div class="field mb-4">
          <label class="font-bold">Thời gian kết thúc</label>
          <DatePicker v-model="coupon.end_time" showTime hourFormat="24" :manualInput="false" />
        </div>

        <div class="grid flex">
          <div class="field col-6 mr-2 flex-1">
            <label for="min_order" class="font-bold">Đơn tối thiểu</label>
            <InputNumber id="min_order" v-model="coupon.min_order_value" mode="currency" currency="VND" locale="vi-VN" />
          </div>
          <div class="field col-6 flex-1">
            <label for="max_discount" class="font-bold">Giảm tối đa</label>
            <InputNumber id="max_discount" v-model="coupon.max_discount_amount" mode="currency" currency="VND" locale="vi-VN" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" icon="pi pi-times" text @click="couponDialog = false" />
        <Button label="Lưu lại" icon="pi pi-check" severity="primary" @click="saveCoupon" :loading="loading" />
      </template>
    </Dialog>

    <!-- Delete Confirm Dialog -->
    <Dialog v-model:visible="deleteCouponDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
      <div class="confirmation-content flex items-center">
        <i class="pi pi-exclamation-triangle mr-3 text-4xl text-red-500" />
        <span v-if="coupon">Bạn có chắc chắn muốn xóa mã <b>{{ coupon.code }}</b>?</span>
      </div>
      <template #footer>
        <Button label="Không" icon="pi pi-times" text @click="deleteCouponDialog = false" />
        <Button label="Xóa ngay" icon="pi pi-check" severity="danger" @click="deleteCoupon" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.admin-coupons {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}
.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}

.table-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.coupon-code {
  font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  color: #4338ca;
  background: #eef2ff;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid #e0e7ff;
}

.category-tag {
  font-size: 13px;
  color: #1d4ed8;
  background: #dbeafe;
  padding: 2px 8px;
  border-radius: 4px;
}

.global-tag {
  font-size: 13px;
  color: #64748b;
}

.time-cell {
  display: flex;
  flex-direction: column;
}
.time-range {
  font-size: 12.5px;
  color: #334155;
}

.usage-cell {
  font-size: 14px;
  font-weight: 500;
  color: #475569;
}

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

.form-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
