<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

const toast = useToast()

// ─── Tabs ───
const activeTab = ref('vouchers')
const tabs = [
  { key: 'vouchers', label: 'Mã giảm giá', icon: 'local_offer' },
  { key: 'flashsale', label: 'Flash Sale', icon: 'flash_on' },
]

// ─── Coupons State ───
const coupons = ref([])
const categories = ref([])
const loading = ref(false)
const couponDialog = ref(false)
const deleteCouponDialog = ref(false)
const coupon = ref({})
const submitted = ref(false)
const categoryOptions = ref([])

// ─── Flash Sale State ───
const flashSales = ref([])
const flashSaleDialog = ref(false)
const deleteFlashSaleDialog = ref(false)
const flashSale = ref({})

// ─── KPI ───
const kpis = computed(() => ({
  totalVouchers: coupons.value.length,
  activeVouchers: coupons.value.filter(c => {
    const now = new Date()
    const start = c.start_time ? new Date(c.start_time) : null
    const end = c.end_time ? new Date(c.end_time) : null
    return (!start || now >= start) && (!end || now <= end)
  }).length,
  totalFlashSales: flashSales.value.length,
  activeFlashSales: flashSales.value.filter(f => f.status === 'active').length,
}))

// ─── API Calls ───
const fetchCoupons = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/admin/coupons')
    coupons.value = res.data.data
  } catch {
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

const fetchFlashSales = async () => {
  try {
    const res = await apiClient.get('/api/admin/flash-sales')
    flashSales.value = res.data.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách Flash Sale.', life: 3000 })
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
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa mã giảm giá.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openNew = () => {
  if (activeTab.value === 'vouchers') {
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
  } else {
    flashSale.value = {
      title: '',
      start_time: new Date(),
      end_time: new Date(new Date().getTime() + 24 * 60 * 60 * 1000),
      is_active: false,
      timezone: 'Asia/Ho_Chi_Minh',
      coupon_stacking_policy: 'deny',
      priority: 0
    }
    submitted.value = false
    flashSaleDialog.value = true
  }
}

const saveFlashSale = async () => {
  submitted.value = true
  if (flashSale.value.title && flashSale.value.start_time && flashSale.value.end_time) {
    loading.value = true
    try {
      if (flashSale.value.id) {
        await apiClient.put(`/api/admin/flash-sales/${flashSale.value.id}`, flashSale.value)
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật Flash Sale', life: 3000 })
      } else {
        await apiClient.post('/api/admin/flash-sales', flashSale.value)
        toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã tạo Flash Sale mới', life: 3000 })
      }
      flashSaleDialog.value = false
      flashSale.value = {}
      fetchFlashSales()
    } catch (e) {
      const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu Flash Sale.'
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 4000 })
    } finally {
      loading.value = false
    }
  }
}

const editFlashSale = (f) => {
  flashSale.value = {
    ...f,
    start_time: f.start ? new Date(f.start) : null,
    end_time: f.end ? new Date(f.end) : null
  }
  flashSaleDialog.value = true
}

const confirmDeleteFlashSale = (f) => {
  flashSale.value = f
  deleteFlashSaleDialog.value = true
}

const deleteFlashSale = async () => {
  loading.value = true
  try {
    await apiClient.delete(`/api/admin/flash-sales/${flashSale.value.id}`)
    deleteFlashSaleDialog.value = false
    flashSale.value = {}
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã xóa Flash Sale', life: 3000 })
    fetchFlashSales()
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa Flash Sale.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const transitionFlashSale = async (sale, toStatus) => {
  const reason = ['ended', 'cancelled'].includes(toStatus) ? window.prompt('Nhập lý do:') : null
  if (['ended', 'cancelled'].includes(toStatus) && !reason) return
  await apiClient.patch(`/api/admin/flash-sales/${sale.id}/transition`, { to_status: toStatus, reason, operation_key: `flash-ui:${sale.id}:${toStatus}:${Date.now()}` })
  await fetchFlashSales()
}

const flashActions = (status) => ({ draft: ['enrollment_open', 'cancelled'], enrollment_open: ['active', 'cancelled'], active: ['ended', 'cancelled'], ended: [], cancelled: [] }[status] || [])

// ─── Formatters ───
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

const flashStatusConfig = {
  draft: { label: 'Bản nháp', bg: 'bg-slate-100', text: 'text-slate-700', icon: 'edit' },
  enrollment_open: { label: 'Mở đăng ký', bg: 'bg-blue-100', text: 'text-blue-800', icon: 'person_add' },
  active: { label: 'Đang diễn ra', bg: 'bg-green-100', text: 'text-green-800', icon: 'flash_on' },
  upcoming: { label: 'Sắp diễn ra', bg: 'bg-blue-100', text: 'text-blue-800', icon: 'schedule' },
  ended: { label: 'Đã kết thúc', bg: 'bg-gray-100', text: 'text-gray-600', icon: 'history' },
  cancelled: { label: 'Đã hủy', bg: 'bg-red-100', text: 'text-red-700', icon: 'cancel' },
}

onMounted(() => {
  fetchCoupons()
  fetchCategories()
  fetchFlashSales()
})
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Quản lý Khuyến mãi</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Tạo và quản lý chương trình Flash Sale, mã giảm giá theo thời gian và danh mục.</p>
      </div>
      <button
        @click="openNew()"
        class="bg-primary text-on-primary font-label-md text-label-md px-lg py-sm rounded-lg hover:opacity-90 transition-opacity flex items-center gap-sm shadow-sm whitespace-nowrap"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        {{ activeTab === 'vouchers' ? 'Thêm mã mới' : 'Tạo Flash Sale' }}
      </button>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-md mb-xl animate-slide-up">
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30 flex flex-col gap-2">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">confirmation_number</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Tổng mã giảm giá</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.totalVouchers }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30 flex flex-col gap-2">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-green-600 bg-green-100 p-1 rounded-md text-[20px]">check_circle</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Đang hoạt động</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.activeVouchers }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30 flex flex-col gap-2">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-orange-600 bg-orange-100 p-1 rounded-md text-[20px]">flash_on</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">Flash Sales</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.totalFlashSales }}</span>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/30 flex flex-col gap-2">
        <div class="flex items-center gap-sm">
          <span class="material-symbols-outlined text-red-600 bg-red-100 p-1 rounded-md text-[20px]">local_fire_department</span>
          <span class="font-label-md text-[13px] text-on-surface-variant">FS đang chạy</span>
        </div>
        <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ kpis.activeFlashSales }}</span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-surface-container-lowest rounded-xl shadow-soft overflow-hidden border border-outline-variant/30 animate-slide-up delay-100">
      <div class="border-b border-outline-variant/30 px-lg bg-surface flex items-center gap-0 overflow-x-auto">
        <button
          v-for="tab in tabs" :key="tab.key"
          @click="activeTab = tab.key"
          class="py-md px-lg font-label-md text-label-md whitespace-nowrap flex items-center gap-xs border-b-2 transition-colors"
          :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
        >
          <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <!-- Voucher Table -->
      <div v-if="activeTab === 'vouchers'">
        <DataTable
          :value="coupons"
          :loading="loading"
          stripedRows
          paginator :rows="10"
          responsiveLayout="scroll"
          class="border-none"
        >
          <template #empty>
            <div class="text-center py-xl text-on-surface-variant">
              <span class="material-symbols-outlined text-[48px] block mb-2 text-outline">confirmation_number</span>
              <p>Chưa có mã giảm giá nào.</p>
            </div>
          </template>

          <Column field="code" header="Mã Code" style="min-width: 120px">
            <template #body="{ data }">
              <span class="font-bold font-mono text-primary bg-primary-container/20 px-2 py-1 rounded-md text-sm border border-primary/10">{{ data.code }}</span>
            </template>
          </Column>

          <Column header="Giảm giá" style="min-width: 100px">
            <template #body="{ data }">
              <Tag severity="success" :value="data.discount_percent + '%'" />
            </template>
          </Column>

          <Column header="Áp dụng cho" style="min-width: 150px">
            <template #body="{ data }">
              <span v-if="data.category" class="text-sm text-blue-700 bg-blue-50 px-2 py-0.5 rounded">
                <i class="pi pi-tag mr-1 text-xs"></i>
                {{ data.category.name }}
              </span>
              <span v-else class="text-sm text-on-surface-variant">Toàn sàn</span>
            </template>
          </Column>

          <Column header="Thời gian" style="min-width: 250px">
            <template #body="{ data }">
              <div class="flex flex-col gap-1">
                <div class="text-xs text-on-surface-variant">
                  {{ formatDate(data.start_time) }} → {{ formatDate(data.end_time) }}
                </div>
                <Tag :severity="getStatusLabel(data).severity" :value="getStatusLabel(data).label" />
              </div>
            </template>
          </Column>

          <Column header="Lượt dùng" style="min-width: 120px">
            <template #body="{ data }">
              <span class="text-sm font-medium text-on-surface">{{ data.used_count }} / {{ data.usage_limit || '∞' }}</span>
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

      <!-- Flash Sale Cards -->
      <div v-else class="p-lg">
        <div v-if="flashSales.length === 0" class="text-center py-xl text-on-surface-variant w-full col-span-full">
          <span class="material-symbols-outlined text-[48px] block mb-2 text-outline">flash_off</span>
          <p>Chưa có Flash Sale nào.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
          <div
            v-for="sale in flashSales" :key="sale.id"
            class="bg-surface rounded-xl border border-outline-variant/30 p-lg hover:shadow-md transition-all group"
          >
            <div class="flex items-center justify-between mb-md">
              <span :class="[flashStatusConfig[sale.status].bg, flashStatusConfig[sale.status].text]" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium">
                <span class="material-symbols-outlined text-[14px]">{{ flashStatusConfig[sale.status].icon }}</span>
                {{ flashStatusConfig[sale.status].label }}
              </span>
              <router-link :to="{ name: 'admin-flash-sale-detail', params: { id: sale.id } }" class="text-outline hover:text-primary transition-colors cursor-pointer p-1" title="Quản lý sản phẩm">
                <span class="material-symbols-outlined text-[20px]">settings</span>
              </router-link>
            </div>
            <h4 class="font-headline-md text-headline-md text-on-surface font-bold mb-2">{{ sale.title }}</h4>
            <div class="space-y-2 text-sm text-on-surface-variant">
              <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-[16px]">schedule</span>
                <span>{{ sale.start }} — {{ sale.end }}</span>
              </div>
              <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                <span>{{ sale.products }} sản phẩm</span>
              </div>
              <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-[16px]">discount</span>
                <span>Giảm tối đa {{ sale.maxDiscount }}%</span>
              </div>
            </div>
            <div class="mt-md pt-md border-t border-outline-variant/30 flex justify-end gap-sm">
              <button v-if="sale.status === 'draft'" @click="editFlashSale(sale)" class="text-sm text-primary hover:underline font-label-md">Chỉnh sửa</button>
              <button v-for="action in flashActions(sale.status)" :key="action" @click="transitionFlashSale(sale, action)" class="text-sm text-primary hover:underline font-label-md">{{ action }}</button>
              <button v-if="sale.status === 'draft' && sale.products === 0" @click="confirmDeleteFlashSale(sale)" class="text-sm text-red-500 hover:underline font-label-md">Xóa</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit/Create Voucher Dialog -->
    <Dialog v-model:visible="couponDialog" :style="{ width: '500px' }" header="Chi tiết mã giảm giá" :modal="true" class="p-fluid">
      <div class="flex flex-col gap-4">
        <div>
          <label for="code" class="font-bold text-sm block mb-1">Mã Code</label>
          <InputText id="code" v-model.trim="coupon.code" required="true" autofocus :class="{ 'p-invalid': submitted && !coupon.code }" placeholder="Ví dụ: FLASH10" />
          <small class="p-error" v-if="submitted && !coupon.code">Bắt buộc nhập mã code.</small>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="discount" class="font-bold text-sm block mb-1">Phần trăm giảm (%)</label>
            <InputNumber id="discount" v-model="coupon.discount_percent" :min="0" :max="100" suffix="%" />
          </div>
          <div>
            <label for="limit" class="font-bold text-sm block mb-1">Giới hạn sử dụng</label>
            <InputNumber id="limit" v-model="coupon.usage_limit" :min="0" placeholder="0 = Không giới hạn" />
          </div>
        </div>

        <div>
          <label for="category" class="font-bold text-sm block mb-1">Danh mục áp dụng</label>
          <Select id="category" v-model="coupon.category_id" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Chọn danh mục" />
        </div>

        <div>
          <label class="font-bold text-sm block mb-1">Thời gian bắt đầu</label>
          <DatePicker v-model="coupon.start_time" showTime hourFormat="24" :manualInput="false" />
        </div>

        <div>
          <label class="font-bold text-sm block mb-1">Thời gian kết thúc</label>
          <DatePicker v-model="coupon.end_time" showTime hourFormat="24" :manualInput="false" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="min_order" class="font-bold text-sm block mb-1">Đơn tối thiểu</label>
            <InputNumber id="min_order" v-model="coupon.min_order_value" mode="currency" currency="VND" locale="vi-VN" />
          </div>
          <div>
            <label for="max_discount" class="font-bold text-sm block mb-1">Giảm tối đa</label>
            <InputNumber id="max_discount" v-model="coupon.max_discount_amount" mode="currency" currency="VND" locale="vi-VN" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" icon="pi pi-times" text @click="couponDialog = false" />
        <Button label="Lưu lại" icon="pi pi-check" severity="primary" @click="saveCoupon" :loading="loading" />
      </template>
    </Dialog>

    <!-- Edit/Create Flash Sale Dialog -->
    <Dialog v-model:visible="flashSaleDialog" :style="{ width: '500px' }" header="Chi tiết Flash Sale" :modal="true" class="p-fluid">
      <div class="flex flex-col gap-4">
        <div>
          <label for="fs_title" class="font-bold text-sm block mb-1">Tên chương trình</label>
          <InputText id="fs_title" v-model.trim="flashSale.title" required="true" autofocus :class="{ 'p-invalid': submitted && !flashSale.title }" placeholder="Ví dụ: Siêu Sale Giữa Tháng" />
          <small class="p-error" v-if="submitted && !flashSale.title">Bắt buộc nhập tên chương trình.</small>
        </div>

        <div>
          <label class="font-bold text-sm block mb-1">Thời gian bắt đầu</label>
          <DatePicker v-model="flashSale.start_time" showTime hourFormat="24" :manualInput="false" />
        </div>

        <div>
          <label class="font-bold text-sm block mb-1">Thời gian kết thúc</label>
          <DatePicker v-model="flashSale.end_time" showTime hourFormat="24" :manualInput="false" />
        </div>
        <div>
          <label class="font-bold text-sm block mb-1">Cho phép cộng dồn coupon</label>
          <select v-model="flashSale.coupon_stacking_policy" class="w-full rounded-lg border border-outline-variant p-3"><option value="deny">Không</option><option value="allow">Có</option></select>
        </div>
        <div>
          <label class="font-bold text-sm block mb-1">Múi giờ</label>
          <input v-model="flashSale.timezone" class="w-full rounded-lg border border-outline-variant p-3" readonly />
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" icon="pi pi-times" text @click="flashSaleDialog = false" />
        <Button label="Lưu lại" icon="pi pi-check" severity="primary" @click="saveFlashSale" :loading="loading" />
      </template>
    </Dialog>

    <!-- Delete Confirm Dialog -->
    <Dialog v-model:visible="deleteCouponDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
      <div class="flex items-center gap-4">
        <span class="material-symbols-outlined text-[40px] text-red-500">warning</span>
        <span v-if="coupon">Bạn có chắc chắn muốn xóa mã <b>{{ coupon.code }}</b>?</span>
      </div>
      <template #footer>
        <Button label="Không" icon="pi pi-times" text @click="deleteCouponDialog = false" />
        <Button label="Xóa ngay" icon="pi pi-check" severity="danger" @click="deleteCoupon" :loading="loading" />
      </template>
    </Dialog>

    <!-- Delete Flash Sale Confirm Dialog -->
    <Dialog v-model:visible="deleteFlashSaleDialog" :style="{ width: '450px' }" header="Xác nhận xóa Flash Sale" :modal="true">
      <div class="flex items-center gap-4">
        <span class="material-symbols-outlined text-[40px] text-red-500">warning</span>
        <span v-if="flashSale">Bạn có chắc chắn muốn xóa Flash Sale <b>{{ flashSale.title }}</b>?</span>
      </div>
      <template #footer>
        <Button label="Không" icon="pi pi-times" text @click="deleteFlashSaleDialog = false" />
        <Button label="Xóa ngay" icon="pi pi-check" severity="danger" @click="deleteFlashSale" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>
