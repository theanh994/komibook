<script setup>
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'

const toast = useToast()

// ─── State ───
const campaigns = ref([])
const loadingCampaigns = ref(false)

// Books options for registration dropdown
const publishedBooks = ref([])
const loadingBooks = ref(false)

// Detail modal state
const detailDialog = ref(false)
const selectedCampaign = ref(null)
const registeredBooks = ref([])
const loadingDetails = ref(false)

// Form state
const form = ref({
  book_id: null,
  discount_percent: 10,
  max_quantity: 10
})
const submitting = ref(false)

// ─── Computed Preview ───
const selectedBookObj = computed(() => {
  if (!form.value.book_id) return null
  return publishedBooks.value.find(b => b.id === form.value.book_id)
})

const discountedPricePreview = computed(() => {
  if (!selectedBookObj.value) return 0
  const original = Number(selectedBookObj.value.price) || 0
  const pct = form.value.discount_percent || 0
  return original * (1 - pct / 100)
})

// ─── Formatting helpers ───
const formatPrice = (val) => {
  if (!val && val !== 0) return '—'
  const num = Number(val)
  if (isNaN(num)) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num)
}

const formatDate = (val) => {
  if (!val) return '—'
  return val
}

// ─── API Fetches ───
const fetchCampaigns = async () => {
  loadingCampaigns.value = true
  try {
    const res = await apiClient.get('/api/vendor/flash-sales')
    campaigns.value = res.data.data || []
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải danh sách chiến dịch Flash Sale.',
      life: 3000
    })
  } finally {
    loadingCampaigns.value = false
  }
}

const fetchPublishedBooks = async () => {
  loadingBooks.value = true
  try {
    const res = await apiClient.get('/api/vendor/books', {
      params: { status: 'published', per_page: 100 }
    })
    publishedBooks.value = res.data.data || []
  } catch (e) {
    console.error('Lỗi khi tải danh sách sách', e)
  } finally {
    loadingBooks.value = false
  }
}

const fetchRegisteredBooks = async (campaignId) => {
  loadingDetails.value = true
  try {
    const res = await apiClient.get(`/api/vendor/flash-sales/${campaignId}/registered-books`)
    registeredBooks.value = res.data.data || []
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải danh sách sách đã đăng ký.',
      life: 3000
    })
  } finally {
    loadingDetails.value = false
  }
}

// ─── Actions ───
const openDetail = async (campaign) => {
  selectedCampaign.value = campaign
  detailDialog.value = true
  // Reset form
  form.value = {
    book_id: null,
    discount_percent: 10,
    max_quantity: 10
  }
  
  await Promise.all([
    fetchRegisteredBooks(campaign.id),
    fetchPublishedBooks()
  ])
}

const handleRegister = async () => {
  if (!form.value.book_id) {
    toast.add({
      severity: 'warn',
      summary: 'Cảnh báo',
      detail: 'Vui lòng chọn một cuốn sách.',
      life: 3000
    })
    return
  }

  submitting.value = true
  try {
    const payload = {
      book_ids: [form.value.book_id],
      discount_percent: form.value.discount_percent,
      max_quantity: form.value.max_quantity
    }

    await apiClient.post(`/api/vendor/flash-sales/${selectedCampaign.value.id}/register`, payload)
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: 'Đã gửi đề xuất sách tham gia Flash Sale thành công!',
      life: 3000
    })

    // Refresh list of registered books and campaign list (to update count)
    await Promise.all([
      fetchRegisteredBooks(selectedCampaign.value.id),
      fetchCampaigns()
    ])

    // Reset form
    form.value.book_id = null
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra khi đăng ký.'
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: msg,
      life: 4000
    })
  } finally {
    submitting.value = false
  }
}

// Quick edit (load proposal details to form for easy adjustment and resubmission)
const loadProposalToForm = (item) => {
  form.value = {
    book_id: item.book_id,
    discount_percent: item.discount_percent,
    max_quantity: item.max_quantity
  }
  toast.add({
    severity: 'info',
    summary: 'Đã nạp dữ liệu',
    detail: 'Thông tin sách đã được tải lên biểu mẫu đăng ký phía trên để chỉnh sửa.',
    life: 3000
  })
}

// Utility styling
const getCampaignStatusSeverity = (status) => {
  return status === 'active' ? 'success' : 'info'
}

const getCampaignStatusLabel = (status) => {
  return status === 'active' ? 'Đang diễn ra' : 'Sắp diễn ra'
}

const getProposalStatusSeverity = (status) => {
  switch (status) {
    case 'approved': return 'success'
    case 'rejected': return 'danger'
    case 'pending':
    default: return 'warn'
  }
}

const getProposalStatusLabel = (status) => {
  switch (status) {
    case 'approved': return 'Đã duyệt'
    case 'rejected': return 'Từ chối'
    case 'pending':
    default: return 'Chờ duyệt'
  }
}

const getCoverImageUrl = (coverImage) => {
  if (!coverImage) return null
  if (coverImage.startsWith('http') || coverImage.startsWith('/')) {
    return coverImage
  }
  return `/storage/${coverImage}`
}

onMounted(() => {
  fetchCampaigns()
})
</script>

<template>
  <div class="vendor-flash-sales">
    <!-- Hero Banner with gradient -->
    <div class="hero-banner">
      <div class="banner-content">
        <div class="banner-badge">
          <i class="pi pi-bolt animated-bolt"></i>
          <span>Chiến dịch Flash Sale</span>
        </div>
        <h1 class="banner-title">Đăng ký & Đề xuất Flash Sale</h1>
        <p class="banner-subtitle">
          Tối ưu hóa doanh số bán hàng bằng cách đăng ký các cuốn sách chất lượng của bạn vào các khung giờ Flash Sale sôi động do Admin khởi tạo.
        </p>
      </div>
      <div class="banner-visual">
        <div class="glow-sphere pink"></div>
        <div class="glow-sphere indigo"></div>
      </div>
    </div>

    <!-- Campaigns List Section -->
    <div class="table-card mt-6">
      <div class="card-header border-b border-slate-100 p-5 flex justify-between items-center bg-slate-50/50">
        <div>
          <h2 class="card-title text-lg font-bold text-slate-800">Danh sách Chiến dịch Đang hoạt động</h2>
          <p class="card-subtitle text-xs text-slate-500 mt-1">Các chương trình Flash Sale hệ thống bạn có thể đăng ký tham gia</p>
        </div>
        <Button 
          icon="pi pi-refresh" 
          outlined 
          severity="secondary" 
          size="small" 
          @click="fetchCampaigns" 
          :loading="loadingCampaigns"
          v-tooltip.top="'Làm mới'"
        />
      </div>

      <DataTable
        :value="campaigns"
        :loading="loadingCampaigns"
        dataKey="id"
        stripedRows
        class="p-datatable-sm"
      >
        <template #empty>
          <div class="empty-state py-12 text-center text-slate-400">
            <i class="pi pi-calendar-times text-5xl mb-3 block"></i>
            <p class="text-sm font-medium">Hiện tại không có chiến dịch Flash Sale nào khả dụng.</p>
            <p class="text-xs text-slate-400 mt-1">Hãy quay lại sau khi Admin tạo chiến dịch mới.</p>
          </div>
        </template>

        <Column header="Tên chiến dịch" style="min-width: 250px">
          <template #body="slotProps">
            <div class="flex items-center gap-3">
              <div class="campaign-icon-wrapper">
                <i class="pi pi-bolt text-amber-500 font-bold"></i>
              </div>
              <span class="font-semibold text-slate-800 text-sm md:text-base">{{ slotProps.data?.title }}</span>
            </div>
          </template>
        </Column>

        <Column header="Thời gian bắt đầu" style="min-width: 160px">
          <template #body="slotProps">
            <div class="flex items-center gap-2 text-slate-600 text-sm">
              <i class="pi pi-clock text-slate-400"></i>
              <span>{{ formatDate(slotProps.data?.start) }}</span>
            </div>
          </template>
        </Column>

        <Column header="Thời gian kết thúc" style="min-width: 160px">
          <template #body="slotProps">
            <div class="flex items-center gap-2 text-slate-600 text-sm">
              <i class="pi pi-clock text-slate-400"></i>
              <span>{{ formatDate(slotProps.data?.end) }}</span>
            </div>
          </template>
        </Column>

        <Column header="Sách đã đăng ký" style="min-width: 130px" class="text-center">
          <template #body="slotProps">
            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
              {{ slotProps.data?.registered_count }} cuốn
            </span>
          </template>
        </Column>

        <Column header="Trạng thái" style="min-width: 130px">
          <template #body="slotProps">
            <Tag 
              :severity="getCampaignStatusSeverity(slotProps.data?.status)" 
              :value="getCampaignStatusLabel(slotProps.data?.status)" 
              rounded
            />
          </template>
        </Column>

        <Column header="Tác vụ" style="min-width: 150px" class="text-right">
          <template #body="slotProps">
            <Button
              label="Đăng ký & Chi tiết"
              icon="pi pi-arrow-right"
              iconPos="right"
              severity="primary"
              size="small"
              class="btn-action-register"
              @click="openDetail(slotProps.data)"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- DETAIL & REGISTRATION DIALOG -->
    <Dialog
      v-model:visible="detailDialog"
      modal
      :draggable="false"
      dismissableMask
      class="flash-sale-detail-dialog"
      :style="{ width: '850px' }"
      :breakpoints="{ '960px': '90vw', '641px': '95vw' }"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <i class="pi pi-bolt text-amber-500 text-xl font-bold animate-pulse"></i>
          <span class="text-lg font-bold text-slate-800">Chi tiết & Đăng ký đề xuất</span>
        </div>
      </template>

      <div v-if="selectedCampaign" class="dialog-content flex flex-col gap-6">
        <!-- Campaign Detail Info Panel -->
        <div class="campaign-info-panel">
          <div class="panel-header-badge">
            <Tag 
              :severity="getCampaignStatusSeverity(selectedCampaign.status)" 
              :value="getCampaignStatusLabel(selectedCampaign.status)"
            />
          </div>
          <h3 class="panel-title text-base md:text-lg font-bold text-slate-800 mb-2">{{ selectedCampaign.title }}</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs md:text-sm text-slate-600">
            <div class="flex items-center gap-2">
              <span class="font-medium text-slate-400">Bắt đầu:</span>
              <span class="font-semibold">{{ formatDate(selectedCampaign.start) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-medium text-slate-400">Kết thúc:</span>
              <span class="font-semibold">{{ formatDate(selectedCampaign.end) }}</span>
            </div>
          </div>
        </div>

        <!-- Section 1: Submit Proposal Form -->
        <div class="proposal-form-section p-4 bg-slate-50/50 rounded-xl border border-slate-200/60">
          <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center gap-2">
            <i class="pi pi-plus-circle text-indigo-500"></i>
            Đề xuất cuốn sách mới tham gia
          </h4>

          <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <!-- Book selection drop down -->
            <div class="col-span-1 md:col-span-5 flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-600">Chọn Sách đã xuất bản <span class="text-red-500">*</span></label>
              <Select
                v-model="form.book_id"
                :options="publishedBooks"
                optionLabel="title"
                optionValue="id"
                placeholder="Chọn sách tham gia..."
                filter
                :loading="loadingBooks"
                class="w-full text-sm"
              >
                <template #option="slotProps">
                  <div class="flex items-center gap-2 py-1">
                    <img 
                      v-if="slotProps.option?.cover_image" 
                      :src="getCoverImageUrl(slotProps.option?.cover_image)" 
                      class="w-6 h-9 rounded object-cover border border-slate-200" 
                    />
                    <div class="flex flex-col">
                      <span class="font-medium text-sm text-slate-800 line-clamp-1">{{ slotProps.option?.title }}</span>
                      <span class="text-xs text-slate-500">{{ formatPrice(slotProps.option?.price) }}</span>
                    </div>
                  </div>
                </template>
              </Select>
            </div>

            <!-- Discount Percent -->
            <div class="col-span-1 md:col-span-3 flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-600">Phần trăm giảm (%) <span class="text-red-500">*</span></label>
              <InputNumber
                v-model="form.discount_percent"
                :min="1"
                :max="99"
                suffix=" %"
                showButtons
                buttonLayout="horizontal"
                class="w-full text-sm"
              />
            </div>

            <!-- Max quantity -->
            <div class="col-span-1 md:col-span-2 flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-600">Số lượng GH <span class="text-slate-400 font-normal">(Limit)</span></label>
              <InputNumber
                v-model="form.max_quantity"
                :min="1"
                placeholder="Nhập..."
                class="w-full text-sm"
              />
            </div>

            <!-- Submit Button -->
            <div class="col-span-1 md:col-span-2">
              <Button
                label="Gửi đề xuất"
                icon="pi pi-send"
                class="btn-primary w-full text-sm py-2"
                :loading="submitting"
                @click="handleRegister"
              />
            </div>
          </div>

          <!-- Realtime Price Preview interaction -->
          <div v-if="selectedBookObj" class="preview-price-box mt-3 p-2.5 bg-indigo-50 border border-indigo-100 rounded-lg flex items-center justify-between animate-fade-in">
            <span class="text-xs text-indigo-700 font-medium">Bản xem trước giá sau chiết khấu:</span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-400 line-through">{{ formatPrice(selectedBookObj.price) }}</span>
              <i class="pi pi-arrow-right text-xs text-indigo-400"></i>
              <span class="text-sm font-bold text-indigo-600">{{ formatPrice(discountedPricePreview) }}</span>
            </div>
          </div>
        </div>

        <!-- Section 2: Registered Books list -->
        <div class="registered-list-section">
          <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-2">
            <i class="pi pi-list text-indigo-500"></i>
            Danh sách sách bạn đã đề xuất trong chiến dịch này
          </h4>

          <DataTable
            :value="registeredBooks"
            :loading="loadingDetails"
            stripedRows
            class="p-datatable-sm"
          >
            <template #empty>
              <div class="text-center py-8 text-slate-400">
                <i class="pi pi-inbox text-3xl mb-2 block"></i>
                <p class="text-xs">Chưa có đề xuất nào cho chiến dịch này. Sử dụng form phía trên để gửi đề xuất.</p>
              </div>
            </template>

            <Column header="Sách" style="min-width: 220px">
              <template #body="slotProps">
                <div class="flex items-center gap-2.5" v-if="slotProps.data?.book">
                  <img 
                    v-if="slotProps.data.book.cover_image" 
                    :src="getCoverImageUrl(slotProps.data.book.cover_image)" 
                    :alt="slotProps.data.book.title"
                    class="w-9 h-12 object-cover rounded border border-slate-200"
                  />
                  <div class="flex flex-col gap-0.5">
                    <span class="font-semibold text-slate-800 text-xs md:text-sm line-clamp-1">{{ slotProps.data.book.title }}</span>
                    <span class="text-xs text-slate-400">{{ formatPrice(slotProps.data.book.price) }}</span>
                  </div>
                </div>
              </template>
            </Column>

            <Column header="Giảm (%)" style="min-width: 90px">
              <template #body="slotProps">
                <span class="font-bold text-slate-800 text-xs md:text-sm whitespace-nowrap">
                  {{ slotProps.data?.discount_percent }}%
                </span>
              </template>
            </Column>

            <Column header="Giá Flash Sale" style="min-width: 120px">
              <template #body="slotProps">
                <span class="font-bold text-red-500 text-xs md:text-sm whitespace-nowrap" v-if="slotProps.data?.book">
                  {{ formatPrice(slotProps.data.book.price * (1 - slotProps.data.discount_percent / 100)) }}
                </span>
              </template>
            </Column>

            <Column header="Giới hạn / Đã bán" style="min-width: 130px">
              <template #body="slotProps">
                <span class="text-xs text-slate-600 whitespace-nowrap">
                  {{ slotProps.data?.max_quantity || 'Không giới hạn' }} / {{ slotProps.data?.sold_quantity }}
                </span>
              </template>
            </Column>

            <Column header="Trạng thái" style="min-width: 120px">
              <template #body="slotProps">
                <Tag 
                  :severity="getProposalStatusSeverity(slotProps.data?.status)" 
                  :value="getProposalStatusLabel(slotProps.data?.status)" 
                  rounded
                  class="whitespace-nowrap"
                />
              </template>
            </Column>

            <Column header="Hành động" style="min-width: 110px" class="text-right">
              <template #body="slotProps">
                <Button 
                  v-if="slotProps.data?.status === 'rejected' || slotProps.data?.status === 'pending'"
                  icon="pi pi-pencil" 
                  severity="secondary" 
                  outlined 
                  rounded 
                  size="small"
                  v-tooltip.top="'Chỉnh sửa & Gửi lại'"
                  @click="loadProposalToForm(slotProps.data)"
                />
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.vendor-flash-sales {
  max-width: 100%;
}

/* Hero Banner Style */
.hero-banner {
  position: relative;
  background: linear-gradient(135deg, #1e1b4b 0%, #311042 50%, #4c0519 100%);
  border-radius: 20px;
  padding: 32px 40px;
  overflow: hidden;
  box-shadow: 0 10px 30px -10px rgba(79, 70, 229, 0.4);
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.banner-content {
  position: relative;
  z-index: 2;
  max-width: 600px;
}

.banner-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(8px);
  padding: 6px 14px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 700;
  color: #fbbf24;
  border: 1px solid rgba(255, 255, 255, 0.05);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 16px;
}

.animated-bolt {
  font-size: 14px;
  animation: pulse-glow 1.5s infinite;
}

.banner-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.2;
  margin: 0 0 12px;
}

.banner-subtitle {
  font-size: 14px;
  color: #cbd5e1;
  line-height: 1.5;
  margin: 0;
}

.banner-visual {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 40%;
  pointer-events: none;
  z-index: 1;
}

.glow-sphere {
  position: absolute;
  width: 150px;
  height: 150px;
  border-radius: 50%;
  filter: blur(60px);
  opacity: 0.5;
}

.glow-sphere.pink {
  background: #ec4899;
  top: -20px;
  right: 40px;
}

.glow-sphere.indigo {
  background: #6366f1;
  bottom: -40px;
  right: -20px;
}

/* Table styling and premium cards */
.table-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
  overflow: hidden;
}

.campaign-icon-wrapper {
  width: 32px;
  height: 32px;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-action-register {
  background: linear-gradient(to bottom, #6366f1, #4f46e5) !important;
  border: none !important;
  color: white !important;
  font-weight: 600 !important;
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2) !important;
  transition: all 0.2s ease !important;
}

.btn-action-register:hover {
  transform: translateX(2px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
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

/* Dialog design customizations */
.campaign-info-panel {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px 20px;
  position: relative;
}

.panel-header-badge {
  position: absolute;
  top: 16px;
  right: 20px;
}

.preview-price-box {
  animation: fadeIn 0.3s ease;
}

@keyframes pulse-glow {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
    filter: drop-shadow(0 0 2px rgba(251, 191, 36, 0.6));
  }
  50% {
    transform: scale(1.1);
    opacity: 0.9;
    filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.9));
  }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease;
}

@media (max-width: 768px) {
  .hero-banner {
    padding: 24px;
    flex-direction: column;
  }
  .banner-visual {
    display: none;
  }
  .panel-header-badge {
    position: static;
    margin-bottom: 8px;
  }
}
</style>
