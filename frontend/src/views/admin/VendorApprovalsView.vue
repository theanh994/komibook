<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'

const toast = useToast()
const vendors = ref([])
const loading = ref(true)
const error = ref('')

// Filters & Search
const activeTab = ref('pending') // 'pending', 'changes_requested', 'approved', 'rejected', 'all'
const searchQuery = ref('')

// Modals
const selectedVendor = ref(null)
const detailVisible = ref(false)

const feedbackAction = ref('rejected')
const feedbackReason = ref('')
const feedbackDialogVisible = ref(false)

// Document Preview Modal
const docPreviewVisible = ref(false)
const docPreviewTitle = ref('')
const docPreviewUrl = ref('')
const docPreviewLoading = ref(false)
const docPreviewIsPdf = ref(false)

const stats = ref({ pending: 0, changes_requested: 0, approved: 0, rejected: 0, total: 0 })

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const statusParam = activeTab.value === 'pending' ? 'pending' : activeTab.value
    const response = await apiClient.get('/api/admin/approvals/vendors', {
      params: { status: statusParam }
    })
    vendors.value = response.data?.data?.vendors || []
    if (response.data?.data?.stats) {
      stats.value = response.data.data.stats
    }
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải danh sách hồ sơ Nhà bán.'
    vendors.value = []
  } finally {
    loading.value = false
  }
}

const setTab = (tab) => {
  activeTab.value = tab
  load()
}

const transition = async (vendor, toStatus, reason = null) => {
  try {
    const response = await apiClient.patch(`/api/admin/approvals/vendors/${vendor.id}/transition`, {
      to_status: toStatus,
      reason,
    })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Trạng thái hồ sơ Nhà bán đã được cập nhật.', life: 3000 })
    feedbackDialogVisible.value = false
    
    // Update vendor detail in place if open
    if (selectedVendor.value && selectedVendor.value.id === vendor.id && response.data?.data) {
      selectedVendor.value = response.data.data
    }
    await load()
  } catch (requestError) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: requestError.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  }
}

const openDetail = (vendor) => {
  selectedVendor.value = vendor
  detailVisible.value = true
}

const openFeedback = (vendor, action) => {
  selectedVendor.value = vendor
  feedbackAction.value = action
  feedbackReason.value = ''
  feedbackDialogVisible.value = true
}

const submitFeedback = () => {
  if (!feedbackReason.value.trim()) return
  transition(selectedVendor.value, feedbackAction.value, feedbackReason.value.trim())
}

const previewDocument = async (vendorId, type, title) => {
  docPreviewTitle.value = title
  docPreviewLoading.value = true
  docPreviewUrl.value = ''
  docPreviewIsPdf.value = false
  docPreviewVisible.value = true
  try {
    const response = await apiClient.get(`/api/vendors/${vendorId}/documents/${type}`, {
      responseType: 'blob'
    })
    const contentType = response.headers['content-type'] || 'image/jpeg'
    const blob = new Blob([response.data], { type: contentType })
    docPreviewUrl.value = URL.createObjectURL(blob)
    docPreviewIsPdf.value = contentType.includes('pdf')
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể mở tài liệu', detail: 'Tài liệu không tồn tại hoặc không thể tải xuống.', life: 3500 })
    docPreviewVisible.value = false
  } finally {
    docPreviewLoading.value = false
  }
}

// Stats computed from global stats
const pendingCount = computed(() => stats.value.pending)
const changesRequestedCount = computed(() => stats.value.changes_requested)
const approvedCount = computed(() => stats.value.approved)
const rejectedCount = computed(() => stats.value.rejected)

// Filtered Vendors based on search query
const filteredVendors = computed(() => {
  if (!searchQuery.value.trim()) return vendors.value
  const q = searchQuery.value.trim().toLowerCase()
  return vendors.value.filter(v => 
    v.shop_name?.toLowerCase().includes(q) ||
    v.legal_name?.toLowerCase().includes(q) ||
    v.tax_code?.toLowerCase().includes(q) ||
    v.user?.name?.toLowerCase().includes(q) ||
    v.user?.email?.toLowerCase().includes(q)
  )
})

// Helpers
const formatDate = (value) => {
  if (!value) return 'Chưa cập nhật'
  try {
    return new Intl.DateTimeFormat('vi-VN', {
      dateStyle: 'medium',
      timeStyle: 'short'
    }).format(new Date(value))
  } catch {
    return value
  }
}

const businessModelLabel = (model) => {
  const map = {
    direct_publisher: 'Nhà xuất bản trực tiếp',
    bookstore: 'Nhà sách / Đại lý',
    distributor: 'Nhà phân phối tổng hợp',
    mixed: 'Mô hình hỗn hợp'
  }
  return map[model] || model || 'Chưa phân loại'
}

const statusBadgeClass = (status) => {
  switch (status) {
    case 'submitted':
      return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300'
    case 'resubmitted':
      return 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300'
    case 'under_review':
      return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300'
    case 'approved':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300'
    case 'changes_requested':
      return 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/60 dark:text-orange-300'
    case 'rejected':
      return 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300'
    case 'suspended':
      return 'bg-slate-200 text-slate-800 border-slate-400 dark:bg-slate-800 dark:text-slate-300'
    default:
      return 'bg-surface-variant text-on-surface-variant'
  }
}

const statusText = (status) => {
  const map = {
    submitted: 'Mới nộp đơn',
    resubmitted: 'Đã nộp lại',
    under_review: 'Đang xét duyệt',
    approved: 'Đã phê duyệt',
    changes_requested: 'Yêu cầu bổ sung',
    rejected: 'Đã từ chối',
    suspended: 'Tạm dừng hoạt động',
    revoked: 'Đã thu hồi'
  }
  return map[status] || status
}

onMounted(load)
</script>

<template>
  <main class="min-h-screen bg-surface-container-low p-4 sm:p-6 lg:p-8" aria-labelledby="vendor-approval-heading">
    <Toast />

    <!-- Header Section -->
    <header class="mx-auto mb-6 max-w-7xl">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="font-label-md font-bold text-secondary">Đối tác thương mại</p>
          <h1 id="vendor-approval-heading" class="mt-1 text-3xl font-extrabold text-primary tracking-tight">Hồ sơ & Kiểm duyệt Nhà bán</h1>
          <p class="mt-1 max-w-3xl text-sm leading-6 text-on-surface-variant">Xác minh đầy đủ thông tin pháp lý, mã số thuế, tài khoản ngân hàng và giấy tờ đại diện trước khi kích hoạt gian hàng.</p>
        </div>
        <Button label="Làm mới" icon="pi pi-refresh" outlined class="min-h-11 self-start sm:self-auto" @click="load" :loading="loading" />
      </div>
    </header>

    <section class="mx-auto max-w-7xl space-y-6">
      <!-- Summary Counter Cards -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div 
          class="cursor-pointer rounded-2xl border p-4 transition-all shadow-sm hover:shadow"
          :class="activeTab === 'pending' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant/30 bg-surface'"
          @click="setTab('pending')"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Chờ xét duyệt</span>
            <span class="material-symbols-outlined text-amber-500">pending_actions</span>
          </div>
          <p class="mt-2 text-2xl font-extrabold text-on-surface">{{ pendingCount }}</p>
        </div>

        <div 
          class="cursor-pointer rounded-2xl border p-4 transition-all shadow-sm hover:shadow"
          :class="activeTab === 'changes_requested' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant/30 bg-surface'"
          @click="setTab('changes_requested')"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Cần bổ sung</span>
            <span class="material-symbols-outlined text-orange-500">edit_note</span>
          </div>
          <p class="mt-2 text-2xl font-extrabold text-on-surface">{{ changesRequestedCount }}</p>
        </div>

        <div 
          class="cursor-pointer rounded-2xl border p-4 transition-all shadow-sm hover:shadow"
          :class="activeTab === 'approved' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant/30 bg-surface'"
          @click="setTab('approved')"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Đã phê duyệt</span>
            <span class="material-symbols-outlined text-emerald-500">verified</span>
          </div>
          <p class="mt-2 text-2xl font-extrabold text-on-surface">{{ approvedCount }}</p>
        </div>

        <div 
          class="cursor-pointer rounded-2xl border p-4 transition-all shadow-sm hover:shadow"
          :class="activeTab === 'rejected' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-outline-variant/30 bg-surface'"
          @click="setTab('rejected')"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Đã từ chối</span>
            <span class="material-symbols-outlined text-rose-500">block</span>
          </div>
          <p class="mt-2 text-2xl font-extrabold text-on-surface">{{ rejectedCount }}</p>
        </div>
      </div>

      <!-- Controls Header: Tabs & Search Filter -->
      <div class="flex flex-col gap-4 rounded-2xl border border-outline-variant/30 bg-surface p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <!-- Status Filter Tabs -->
        <nav class="flex flex-wrap items-center gap-1.5" aria-label="Bộ lọc trạng thái">
          <button
            type="button"
            class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
            :class="activeTab === 'pending' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest'"
            @click="setTab('pending')"
          >
            Chờ xử lý
          </button>
          <button
            type="button"
            class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
            :class="activeTab === 'changes_requested' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest'"
            @click="setTab('changes_requested')"
          >
            Yêu cầu bổ sung
          </button>
          <button
            type="button"
            class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
            :class="activeTab === 'approved' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest'"
            @click="setTab('approved')"
          >
            Đã duyệt
          </button>
          <button
            type="button"
            class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
            :class="activeTab === 'rejected' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest'"
            @click="setTab('rejected')"
          >
            Từ chối
          </button>
          <button
            type="button"
            class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
            :class="activeTab === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest'"
            @click="setTab('all')"
          >
            Tất cả
          </button>
        </nav>

        <!-- Search Bar -->
        <div class="relative w-full sm:w-72">
          <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-lg text-on-surface-variant">
            search
          </span>
          <InputText 
            v-model="searchQuery" 
            placeholder="Tìm theo tên, MST, email..." 
            class="min-h-10 w-full !pl-10 text-xs rounded-xl" 
          />
        </div>
      </div>

      <!-- Alerts / Loading / Empty States -->
      <div v-if="error" role="alert" class="rounded-2xl border border-error/30 bg-error/5 p-5 text-error">
        <p class="font-bold">{{ error }}</p>
        <Button label="Thử lại" class="mt-3 min-h-11" @click="load" />
      </div>

      <div v-else-if="loading" role="status" aria-live="polite" class="rounded-2xl bg-surface p-12 text-center text-on-surface-variant shadow-sm">
        <span class="material-symbols-outlined animate-spin text-3xl text-primary mb-2">progress_activity</span>
        <p class="font-medium text-sm">Đang tải danh sách hồ sơ Nhà bán…</p>
      </div>

      <div v-else-if="!filteredVendors.length" class="rounded-2xl border border-dashed border-outline-variant p-12 text-center bg-surface">
        <span class="material-symbols-outlined text-4xl text-on-surface-variant/50 mb-2">storefront</span>
        <p class="text-base font-semibold text-on-surface">Không tìm thấy hồ sơ Nhà bán nào.</p>
        <p class="mt-1 text-xs text-on-surface-variant">Thử chọn danh mục khác hoặc thay đổi từ khóa tìm kiếm.</p>
      </div>

      <!-- Vendor List Cards -->
      <div v-else class="space-y-4">
        <article 
          v-for="vendor in filteredVendors" 
          :key="vendor.id" 
          class="rounded-2xl border border-outline-variant/30 bg-surface p-5 shadow-sm transition-all hover:shadow-md"
        >
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <!-- Left Info Section -->
            <div class="min-w-0 flex-1 space-y-3">
              <div class="flex flex-wrap items-center gap-2">
                <div v-if="vendor.logo" class="h-10 w-10 overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-container">
                  <img :src="vendor.logo" :alt="vendor.shop_name" class="h-full w-full object-cover" />
                </div>
                <div v-else class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-base">
                  {{ vendor.shop_name?.charAt(0) || 'V' }}
                </div>
                <div>
                  <h2 class="text-lg font-extrabold text-on-surface leading-tight">{{ vendor.shop_name }}</h2>
                  <p class="text-xs text-on-surface-variant font-mono">ID: #{{ vendor.id }} · Slug: /store/{{ vendor.slug }}</p>
                </div>

                <span 
                  class="ml-auto sm:ml-2 inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-bold"
                  :class="statusBadgeClass(vendor.onboarding_status)"
                >
                  {{ statusText(vendor.onboarding_status) }}
                </span>
              </div>

              <!-- Highlights Grid -->
              <div class="grid grid-cols-1 gap-2 rounded-xl bg-surface-container-low p-3.5 text-xs sm:grid-cols-3">
                <div>
                  <span class="font-medium text-on-surface-variant">Tên pháp lý:</span>
                  <p class="font-bold text-on-surface truncate">{{ vendor.legal_name || 'Chưa cung cấp' }}</p>
                </div>
                <div>
                  <span class="font-medium text-on-surface-variant">Mã số thuế:</span>
                  <p class="font-bold text-on-surface font-mono">{{ vendor.tax_code || 'Chưa cung cấp' }}</p>
                </div>
                <div>
                  <span class="font-medium text-on-surface-variant">Mô hình:</span>
                  <p class="font-bold text-on-surface">{{ businessModelLabel(vendor.business_model) }}</p>
                </div>
              </div>

              <!-- Representative & Contact -->
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-on-surface-variant">
                <span><strong class="text-on-surface">Đại diện:</strong> {{ vendor.user?.name || 'N/A' }}</span>
                <span>•</span>
                <span><strong class="text-on-surface">Email:</strong> {{ vendor.user?.email || 'N/A' }}</span>
                <template v-if="vendor.user?.phone">
                  <span>•</span>
                  <span><strong class="text-on-surface">SĐT:</strong> {{ vendor.user.phone }}</span>
                </template>
                <span>•</span>
                <span><strong class="text-on-surface">Nộp đơn:</strong> {{ formatDate(vendor.submitted_at) }}</span>
              </div>

              <!-- Documents badges -->
              <div class="flex flex-wrap gap-2 text-xs">
                <span 
                  class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-semibold"
                  :class="vendor.has_business_registration_document ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                >
                  <span class="material-symbols-outlined text-sm">{{ vendor.has_business_registration_document ? 'task_alt' : 'cancel' }}</span>
                  Giấy ĐKKD
                </span>
                <span 
                  class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 font-semibold"
                  :class="vendor.has_representative_identity_document ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                >
                  <span class="material-symbols-outlined text-sm">{{ vendor.has_representative_identity_document ? 'task_alt' : 'cancel' }}</span>
                  CCCD/CMND Đại diện
                </span>
              </div>
            </div>

            <!-- Right Actions Buttons -->
            <div class="flex flex-wrap gap-2 lg:flex-col lg:items-end">
              <Button 
                label="Xem hồ sơ chi tiết" 
                icon="pi pi-id-card" 
                severity="primary" 
                class="min-h-11 font-bold text-xs" 
                @click="openDetail(vendor)" 
              />

              <Button 
                v-if="['submitted', 'resubmitted'].includes(vendor.onboarding_status)" 
                label="Bắt đầu duyệt" 
                icon="pi pi-search" 
                severity="info" 
                outlined 
                class="min-h-11 text-xs" 
                @click="transition(vendor, 'under_review')" 
              />

              <template v-if="vendor.onboarding_status === 'under_review'">
                <Button 
                  label="Phê duyệt" 
                  icon="pi pi-check" 
                  severity="success" 
                  class="min-h-11 text-xs" 
                  @click="transition(vendor, 'approved')" 
                />
                <Button 
                  label="Yêu cầu bổ sung" 
                  icon="pi pi-pencil" 
                  severity="warn" 
                  outlined 
                  class="min-h-11 text-xs" 
                  @click="openFeedback(vendor, 'changes_requested')" 
                />
                <Button 
                  label="Từ chối" 
                  icon="pi pi-times" 
                  severity="danger" 
                  outlined 
                  class="min-h-11 text-xs" 
                  @click="openFeedback(vendor, 'rejected')" 
                />
              </template>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- FULL VENDOR PROFILE DETAIL DIALOG -->
    <Dialog 
      v-model:visible="detailVisible" 
      modal 
      :header="selectedVendor ? `Hồ sơ chi tiết: ${selectedVendor.shop_name}` : 'Chi tiết hồ sơ'" 
      :style="{ width: 'min(94vw, 850px)' }"
      class="rounded-2xl"
    >
      <div v-if="selectedVendor" class="space-y-6 py-2">
        <!-- Dialog Header Banner -->
        <div class="flex flex-col gap-4 rounded-2xl border border-outline-variant/30 bg-surface-container-low p-5 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-4">
            <div class="h-14 w-14 overflow-hidden rounded-2xl border border-outline-variant/40 bg-surface flex items-center justify-center">
              <img v-if="selectedVendor.logo" :src="selectedVendor.logo" :alt="selectedVendor.shop_name" class="h-full w-full object-cover" />
              <span v-else class="text-2xl font-extrabold text-primary">{{ selectedVendor.shop_name?.charAt(0) }}</span>
            </div>
            <div>
              <h3 class="text-xl font-extrabold text-on-surface">{{ selectedVendor.shop_name }}</h3>
              <p class="text-xs text-on-surface-variant">Thành viên: {{ selectedVendor.user?.name }} ({{ selectedVendor.user?.email }})</p>
            </div>
          </div>
          <div class="flex flex-col items-start sm:items-end gap-1">
            <span class="rounded-full border px-3 py-1 text-xs font-extrabold" :class="statusBadgeClass(selectedVendor.onboarding_status)">
              {{ statusText(selectedVendor.onboarding_status) }}
            </span>
            <span class="text-xs text-on-surface-variant">Nộp lúc: {{ formatDate(selectedVendor.submitted_at) }}</span>
          </div>
        </div>

        <!-- Section 1: Thông tin Đăng ký & Gian hàng -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface p-5 space-y-4 shadow-sm">
          <div class="flex items-center gap-2 border-b border-outline-variant/30 pb-3">
            <span class="material-symbols-outlined text-primary text-xl">storefront</span>
            <h4 class="font-bold text-on-surface text-base">1. Thông tin Gian hàng</h4>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs leading-relaxed">
            <div>
              <span class="text-on-surface-variant font-medium">Tên gian hàng:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5">{{ selectedVendor.shop_name }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Đường dẫn Shop (Slug):</span>
              <p class="text-sm font-bold text-on-surface font-mono mt-0.5">/store/{{ selectedVendor.slug }}</p>
            </div>
            <div class="sm:col-span-2">
              <span class="text-on-surface-variant font-medium">Mô tả gian hàng:</span>
              <p class="text-xs text-on-surface bg-surface-container-low p-3 rounded-xl mt-1 leading-relaxed whitespace-pre-line">
                {{ selectedVendor.description || 'Chưa cung cấp mô tả gian hàng.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Section 2: Thông tin Pháp lý & Thuế -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface p-5 space-y-4 shadow-sm">
          <div class="flex items-center gap-2 border-b border-outline-variant/30 pb-3">
            <span class="material-symbols-outlined text-primary text-xl">gavel</span>
            <h4 class="font-bold text-on-surface text-base">2. Thông tin Pháp lý & Doanh nghiệp</h4>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
            <div>
              <span class="text-on-surface-variant font-medium">Tên đăng ký pháp lý / Công ty:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5">{{ selectedVendor.legal_name || 'Chưa cập nhật' }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Mã số thuế (Tax Code):</span>
              <p class="text-sm font-mono font-bold text-on-surface mt-0.5">{{ selectedVendor.tax_code || 'Chưa cập nhật' }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Mô hình kinh doanh:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5">{{ businessModelLabel(selectedVendor.business_model) }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Ngày đồng ý điều khoản:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5">{{ formatDate(selectedVendor.terms_accepted_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Section 3: Ngân hàng Thanh toán -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface p-5 space-y-4 shadow-sm">
          <div class="flex items-center gap-2 border-b border-outline-variant/30 pb-3">
            <span class="material-symbols-outlined text-primary text-xl">account_balance</span>
            <h4 class="font-bold text-on-surface text-base">3. Tài khoản Ngân hàng Nhận Đối soát</h4>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs">
            <div>
              <span class="text-on-surface-variant font-medium">Tên ngân hàng:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5">{{ selectedVendor.payout_bank_name || 'Chưa cập nhật' }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Số tài khoản:</span>
              <p class="text-sm font-mono font-bold text-on-surface mt-0.5">{{ selectedVendor.payout_bank_account || 'Chưa cập nhật' }}</p>
            </div>
            <div>
              <span class="text-on-surface-variant font-medium">Tên chủ tài khoản:</span>
              <p class="text-sm font-bold text-on-surface mt-0.5 uppercase">{{ selectedVendor.payout_bank_holder || 'Chưa cập nhật' }}</p>
            </div>
            <div class="sm:col-span-3">
              <span class="text-on-surface-variant font-medium">Trạng thái xác minh ngân hàng:</span>
              <p class="mt-1 inline-flex items-center gap-1 font-bold text-xs px-2.5 py-1 rounded-lg bg-surface-container-high">
                <span class="material-symbols-outlined text-sm text-primary">verified_user</span>
                {{ selectedVendor.payout_bank_status || 'Chưa xác minh' }} 
                <span v-if="selectedVendor.payout_bank_verified_at" class="text-on-surface-variant font-normal">({{ formatDate(selectedVendor.payout_bank_verified_at) }})</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Section 4: Giấy tờ & Tài liệu chứng thực -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface p-5 space-y-4 shadow-sm">
          <div class="flex items-center gap-2 border-b border-outline-variant/30 pb-3">
            <span class="material-symbols-outlined text-primary text-xl">folder_shared</span>
            <h4 class="font-bold text-on-surface text-base">4. Hồ sơ & Giấy tờ Chứng thực</h4>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Business Registration Document -->
            <div class="rounded-xl border border-outline-variant/30 p-4 bg-surface-container-low flex flex-col justify-between space-y-3">
              <div>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-on-surface">Giấy Đăng ký kinh doanh</span>
                  <span 
                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                    :class="selectedVendor.has_business_registration_document ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    {{ selectedVendor.has_business_registration_document ? 'Đã nộp' : 'Chưa có' }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-on-surface-variant">Bản scan giấy chứng nhận đăng ký kinh doanh hoặc quyết định thành lập.</p>
              </div>

              <div v-if="selectedVendor.has_business_registration_document" class="flex gap-2 pt-2">
                <Button 
                  label="Xem trực tiếp" 
                  icon="pi pi-eye" 
                  severity="secondary" 
                  class="min-h-9 text-xs flex-1" 
                  @click="previewDocument(selectedVendor.id, 'business', 'Giấy Đăng ký Kinh doanh')" 
                />
                <a 
                  :href="selectedVendor.business_registration_document_url" 
                  target="_blank" 
                  class="inline-flex items-center justify-center rounded-xl border border-outline-variant px-3 text-xs font-bold text-primary hover:bg-surface-container"
                >
                  <span class="material-symbols-outlined text-base">open_in_new</span>
                </a>
              </div>
            </div>

            <!-- Representative Identity Document -->
            <div class="rounded-xl border border-outline-variant/30 p-4 bg-surface-container-low flex flex-col justify-between space-y-3">
              <div>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-on-surface">CCCD / Hộ chiếu Đại diện</span>
                  <span 
                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                    :class="selectedVendor.has_representative_identity_document ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    {{ selectedVendor.has_representative_identity_document ? 'Đã nộp' : 'Chưa có' }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-on-surface-variant">Bản chụp 2 mặt Căn cước công dân hoặc hộ chiếu người đại diện pháp luật.</p>
              </div>

              <div v-if="selectedVendor.has_representative_identity_document" class="flex gap-2 pt-2">
                <Button 
                  label="Xem trực tiếp" 
                  icon="pi pi-eye" 
                  severity="secondary" 
                  class="min-h-9 text-xs flex-1" 
                  @click="previewDocument(selectedVendor.id, 'representative', 'CCCD/Hộ chiếu Người Đại diện')" 
                />
                <a 
                  :href="selectedVendor.representative_identity_document_url" 
                  target="_blank" 
                  class="inline-flex items-center justify-center rounded-xl border border-outline-variant px-3 text-xs font-bold text-primary hover:bg-surface-container"
                >
                  <span class="material-symbols-outlined text-base">open_in_new</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 5: Lịch sử Review & Lý do gần nhất -->
        <div v-if="selectedVendor.last_review_reason" class="rounded-2xl border border-amber-300/60 bg-amber-50/50 p-5 space-y-2 dark:bg-amber-950/30">
          <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
            <span class="material-symbols-outlined text-lg">history_edu</span>
            <h4 class="font-bold text-sm">Ghi chú xét duyệt gần nhất</h4>
          </div>
          <p class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed font-medium bg-amber-100/60 dark:bg-amber-900/40 p-3 rounded-xl">
            {{ selectedVendor.last_review_reason }}
          </p>
        </div>
      </div>

      <template #footer>
        <div class="flex flex-wrap items-center justify-between gap-3 w-full border-t border-outline-variant/30 pt-3">
          <Button label="Đóng" severity="secondary" text class="min-h-11" @click="detailVisible = false" />

          <div v-if="selectedVendor" class="flex flex-wrap gap-2">
            <Button 
              v-if="['submitted', 'resubmitted'].includes(selectedVendor.onboarding_status)" 
              label="Bắt đầu xét duyệt" 
              icon="pi pi-search" 
              severity="info" 
              class="min-h-11 text-xs font-bold" 
              @click="transition(selectedVendor, 'under_review')" 
            />

            <template v-if="selectedVendor.onboarding_status === 'under_review'">
              <Button 
                label="Phê duyệt mở gian hàng" 
                icon="pi pi-check" 
                severity="success" 
                class="min-h-11 text-xs font-bold" 
                @click="transition(selectedVendor, 'approved')" 
              />
              <Button 
                label="Yêu cầu bổ sung" 
                icon="pi pi-pencil" 
                severity="warn" 
                outlined 
                class="min-h-11 text-xs font-bold" 
                @click="openFeedback(selectedVendor, 'changes_requested')" 
              />
              <Button 
                label="Từ chối đơn" 
                icon="pi pi-times" 
                severity="danger" 
                outlined 
                class="min-h-11 text-xs font-bold" 
                @click="openFeedback(selectedVendor, 'rejected')" 
              />
            </template>
          </div>
        </div>
      </template>
    </Dialog>

    <!-- FEEDBACK REASON DIALOG -->
    <Dialog 
      v-model:visible="feedbackDialogVisible" 
      modal 
      :header="feedbackAction === 'changes_requested' ? 'Yêu cầu bổ sung thông tin hồ sơ' : 'Từ chối hồ sơ đăng ký'" 
      :style="{ width: 'min(92vw, 500px)' }"
    >
      <div class="space-y-4 py-2">
        <p class="text-xs text-on-surface-variant leading-relaxed">
          Ghi chú lý do rõ ràng để gửi phản hồi cho gian hàng <strong>{{ selectedVendor?.shop_name }}</strong>. 
          Nhà bán sẽ nhận được thông báo về nội dung này.
        </p>
        <label class="block space-y-2 text-sm font-bold text-on-surface">
          <span>Nội dung phản hồi / Lý do</span>
          <InputText 
            v-model="feedbackReason" 
            placeholder="Ví dụ: Mã số thuế không trùng khớp, thiếu ảnh scan CCCD rõ nét..." 
            class="min-h-11 w-full text-xs" 
          />
        </label>
      </div>

      <template #footer>
        <Button label="Hủy" text class="min-h-11" @click="feedbackDialogVisible = false" />
        <Button 
          :label="feedbackAction === 'changes_requested' ? 'Gửi yêu cầu' : 'Xác nhận từ chối'" 
          :severity="feedbackAction === 'changes_requested' ? 'warn' : 'danger'" 
          class="min-h-11 font-bold" 
          :disabled="!feedbackReason.trim()" 
          @click="submitFeedback" 
        />
      </template>
    </Dialog>

    <!-- DOCUMENT PREVIEW MODAL -->
    <Dialog
      v-model:visible="docPreviewVisible"
      modal
      :header="docPreviewTitle"
      :style="{ width: 'min(94vw, 900px)' }"
    >
      <div class="flex flex-col items-center justify-center min-h-[400px] p-2 bg-surface-container-low rounded-xl">
        <div v-if="docPreviewLoading" class="flex flex-col items-center gap-2 text-on-surface-variant">
          <span class="material-symbols-outlined animate-spin text-3xl text-primary">progress_activity</span>
          <p class="text-xs">Đang tải bản xem trước tài liệu…</p>
        </div>

        <template v-else-if="docPreviewUrl">
          <iframe 
            v-if="docPreviewIsPdf" 
            :src="docPreviewUrl" 
            class="w-full h-[550px] rounded-xl border border-outline-variant/30"
          ></iframe>
          <div v-else class="max-h-[600px] overflow-auto flex items-center justify-center">
            <img :src="docPreviewUrl" :alt="docPreviewTitle" class="max-w-full rounded-xl object-contain shadow-md" />
          </div>
        </template>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <a 
            v-if="docPreviewUrl" 
            :href="docPreviewUrl" 
            download 
            target="_blank"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline"
          >
            <span class="material-symbols-outlined text-base">download</span>
            Tải về máy
          </a>
          <Button label="Đóng" severity="secondary" class="min-h-10 text-xs" @click="docPreviewVisible = false" />
        </div>
      </template>
    </Dialog>
  </main>
</template>
