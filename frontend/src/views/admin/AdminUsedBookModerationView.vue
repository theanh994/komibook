<script setup>
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'

const listings = ref([])
const loading = ref(true)
const loadError = ref('')
const notice = ref('')
const activeFilter = ref('pending') // 'pending' | 'active' | 'rejected' | 'all'
const actionBusy = ref(null)

// Reject Modal State
const rejectModalOpen = ref(false)
const selectedListing = ref(null)
const rejectionReason = ref('')
const rejectError = ref('')

const conditionOptions = [
  { value: 'like_new', label: 'Như mới (95 - 99%)' },
  { value: 'good', label: 'Tốt (80 - 90%)' },
  { value: 'fair', label: 'Khá (60 - 79%)' },
]

const conditionLabel = value => conditionOptions.find(o => o.value === value)?.label || value
const money = value => `${Number(value || 0).toLocaleString('vi-VN')} đ`

const fetchListings = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await apiClient.get('/api/admin/used-book-listings', {
      params: { status: activeFilter.value },
    })
    listings.value = response.data?.data || []
  } catch (error) {
    loadError.value = error.response?.data?.message || 'Không thể tải danh sách kiểm duyệt sách cũ.'
  } finally {
    loading.value = false
  }
}

const setFilter = filter => {
  activeFilter.value = filter
  fetchListings()
}

const approveListing = async listing => {
  if (!confirm(`Xác nhận duyệt đăng bán cuốn sách "${listing.book?.title || listing.id}"?`)) return
  actionBusy.value = listing.id
  notice.value = ''
  try {
    const response = await apiClient.patch(`/api/admin/used-book-listings/${listing.id}/approve`)
    notice.value = response.data?.message || 'Đã duyệt sách thành công!'
    fetchListings()
  } catch (error) {
    alert(error.response?.data?.message || 'Không thể duyệt sách này.')
  } finally {
    actionBusy.value = null
  }
}

const openRejectModal = listing => {
  selectedListing.value = listing
  rejectionReason.value = ''
  rejectError.value = ''
  rejectModalOpen.value = true
}

const closeRejectModal = () => {
  rejectModalOpen.value = false
  selectedListing.value = null
  rejectionReason.value = ''
  rejectError.value = ''
}

const confirmReject = async () => {
  if (!rejectionReason.value.trim()) {
    rejectError.value = 'Vui lòng nhập lý do từ chối.'
    return
  }

  actionBusy.value = selectedListing.value.id
  rejectError.value = ''
  try {
    const response = await apiClient.patch(`/api/admin/used-book-listings/${selectedListing.value.id}/reject`, {
      rejection_reason: rejectionReason.value.trim(),
    })
    notice.value = response.data?.message || 'Đã từ chối đăng bán cuốn sách.'
    closeRejectModal()
    fetchListings()
  } catch (error) {
    rejectError.value = error.response?.data?.message || 'Không thể từ chối sách này.'
  } finally {
    actionBusy.value = null
  }
}

const statusBadgeClass = status => {
  switch (status) {
    case 'active':
      return 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20'
    case 'pending':
      return 'bg-amber-500/10 text-amber-700 border-amber-500/20'
    case 'rejected':
      return 'bg-red-500/10 text-red-700 border-red-500/20'
    default:
      return 'bg-slate-500/10 text-slate-700 border-slate-500/20'
  }
}

const statusLabel = status => {
  switch (status) {
    case 'active': return 'Đã duyệt (Đang bán)'
    case 'pending': return 'Chờ xét duyệt'
    case 'rejected': return 'Bị từ chối'
    default: return status
  }
}

onMounted(fetchListings)
</script>

<template>
  <div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-soft">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-black text-on-surface tracking-tight">Kiểm Duyệt Sách Cũ</h1>
          <span class="rounded-full bg-amber-500/10 px-3 py-0.5 text-xs font-bold text-amber-700 border border-amber-500/20">
            C2C Moderation
          </span>
        </div>
        <p class="text-xs text-on-surface-variant mt-1">Xem xét ảnh thật, tình trạng thực tế và duyệt sách cũ do độc giả/người bán cá nhân đăng bán.</p>
      </div>

      <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface px-4 py-2 text-xs font-bold text-on-surface hover:bg-surface-container-low cursor-pointer" @click="fetchListings">
        <span class="material-symbols-outlined text-base">refresh</span>
        <span>Làm mới</span>
      </button>
    </div>

    <!-- Alert Messages -->
    <div v-if="notice" role="status" class="flex items-center gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
      <span class="material-symbols-outlined text-emerald-700 text-lg">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <div v-if="loadError" role="alert" class="flex items-center justify-between gap-3 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm text-red-800">
      <span>{{ loadError }}</span>
      <button type="button" class="font-bold underline cursor-pointer" @click="fetchListings">Thử lại</button>
    </div>

    <!-- Filter Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-outline-variant/20 pb-3">
      <button
        v-for="filter in [
          { key: 'pending', label: 'Chờ xét duyệt ⏳' },
          { key: 'active', label: 'Đã duyệt (Đang bán) ✓' },
          { key: 'rejected', label: 'Bị từ chối ❌' },
          { key: 'all', label: 'Tất cả' }
        ]"
        :key="filter.key"
        type="button"
        class="rounded-xl px-4 py-2 text-xs font-bold transition-all cursor-pointer"
        :class="activeFilter === filter.key ? 'bg-primary text-on-primary shadow-xs' : 'bg-surface-container-lowest border border-outline-variant/30 text-on-surface-variant hover:bg-surface-container-low'"
        @click="setFilter(filter.key)"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center text-on-surface-variant space-y-3">
      <span class="material-symbols-outlined text-4xl animate-spin text-primary">progress_activity</span>
      <p class="text-xs font-semibold">Đang tải danh sách sách cũ...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!listings.length" class="rounded-3xl border border-dashed border-outline-variant/40 bg-surface-container-lowest p-12 text-center space-y-2">
      <span class="material-symbols-outlined text-4xl text-outline">verified_user</span>
      <p class="text-sm font-bold text-on-surface">Không có sách cũ nào trong danh mục này.</p>
    </div>

    <!-- Listings Cards Grid -->
    <div v-else class="space-y-6">
      <article
        v-for="listing in listings"
        :key="listing.id"
        class="rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-soft space-y-5"
      >
        <!-- Card Header: Title, Seller & Status Badge -->
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-outline-variant/15 pb-4">
          <div class="space-y-1">
            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider border" :class="statusBadgeClass(listing.status)">
              {{ statusLabel(listing.status) }}
            </span>
            <h2 class="text-lg font-black text-on-surface mt-1">{{ listing.book?.title || `Sách cũ #${listing.id}` }}</h2>
            <p class="text-xs text-on-surface-variant">
              Tác giả: <strong class="text-on-surface font-semibold">{{ listing.book?.author }}</strong> · 
              Danh mục: <strong class="text-on-surface font-semibold">{{ listing.book?.category?.name || 'Chưa rõ' }}</strong> · 
              Giá bán: <strong class="text-primary font-bold text-sm">{{ money(listing.book?.price) }}</strong>
            </p>
          </div>

          <div class="text-right text-xs space-y-0.5">
            <span class="text-on-surface-variant block">Người đăng bán:</span>
            <strong class="text-on-surface block font-bold">{{ listing.seller?.name || 'Khách bán' }}</strong>
            <span class="text-[11px] text-outline font-mono block">{{ listing.seller?.email }}</span>
          </div>
        </div>

        <!-- Details Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-xs">
          <div class="rounded-2xl bg-surface-container-low p-3.5 space-y-1">
            <span class="text-on-surface-variant font-semibold block text-[11px]">Tình trạng công bố:</span>
            <span class="font-bold text-emerald-700 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">verified</span>
              {{ conditionLabel(listing.condition) }}
            </span>
          </div>

          <div class="rounded-2xl bg-surface-container-low p-3.5 space-y-1">
            <span class="text-on-surface-variant font-semibold block text-[11px]">Số lượng kho:</span>
            <span class="font-bold text-on-surface">{{ listing.quantity_available }} cuốn</span>
          </div>

          <div class="rounded-2xl bg-surface-container-low p-3.5 space-y-1 sm:col-span-2 lg:col-span-1">
            <span class="text-on-surface-variant font-semibold block text-[11px]">Cam kết sách thật:</span>
            <span class="font-semibold text-emerald-800 flex items-center gap-1">
              <span class="material-symbols-outlined text-sm text-emerald-600">gavel</span>
              Đã xác nhận lúc {{ new Date(listing.authenticity_attested_at || listing.created_at).toLocaleDateString('vi-VN') }}
            </span>
          </div>
        </div>

        <!-- Defects / Note -->
        <div v-if="listing.defects" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-xs space-y-1">
          <strong class="font-bold text-amber-950 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm text-amber-700">warning</span>
            Mô tả vết sờn / Khuyết điểm chi tiết của người bán:
          </strong>
          <p class="text-amber-900 leading-relaxed">{{ listing.defects }}</p>
        </div>

        <!-- Rejection Reason if Rejected -->
        <div v-if="listing.status === 'rejected' && listing.rejection_reason" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-xs space-y-1">
          <strong class="font-bold text-red-950 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm text-red-700">cancel</span>
            Lý do từ chối:
          </strong>
          <p class="text-red-900 leading-relaxed">{{ listing.rejection_reason }}</p>
        </div>

        <!-- Actual Uploaded Photos Preview -->
        <div class="space-y-2">
          <h3 class="text-xs font-bold text-on-surface">Ảnh chụp thực tế của cuốn sách ({{ listing.actual_photos?.length || 0 }} ảnh):</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <a
              v-for="(photoUrl, pIdx) in listing.actual_photos"
              :key="pIdx"
              :href="photoUrl"
              target="_blank"
              class="group relative aspect-square overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-low"
            >
              <img :src="photoUrl" :alt="`Ảnh chụp thực tế ${pIdx + 1}`" class="h-full w-full object-cover transition-transform group-hover:scale-105" />
              <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
                <span class="material-symbols-outlined text-lg">zoom_in</span>
              </div>
            </a>
          </div>
        </div>

        <!-- Action Buttons -->
        <div v-if="listing.status === 'pending'" class="flex justify-end gap-3 pt-3 border-t border-outline-variant/15">
          <button
            type="button"
            :disabled="actionBusy === listing.id"
            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-red-300 bg-red-50 px-4 text-xs font-bold text-red-700 hover:bg-red-100 transition-colors disabled:opacity-50 cursor-pointer"
            @click="openRejectModal(listing)"
          >
            <span class="material-symbols-outlined text-base">close</span>
            <span>Từ chối</span>
          </button>

          <button
            type="button"
            :disabled="actionBusy === listing.id"
            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-6 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition-all disabled:opacity-50 cursor-pointer"
            @click="approveListing(listing)"
          >
            <span class="material-symbols-outlined text-base">check_circle</span>
            <span>{{ actionBusy === listing.id ? 'Đang duyệt...' : 'Duyệt đăng bán' }}</span>
          </button>
        </div>
      </article>
    </div>

    <!-- Reject Modal Dialog -->
    <div v-if="rejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
      <div class="w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-elevated space-y-4">
        <div class="flex items-center justify-between border-b border-outline-variant/15 pb-3">
          <h3 class="text-base font-black text-on-surface flex items-center gap-2">
            <span class="material-symbols-outlined text-red-600">cancel</span>
            Từ chối duyệt đăng sách cũ
          </h3>
          <button type="button" class="text-outline hover:text-on-surface cursor-pointer" @click="closeRejectModal">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <p class="text-xs text-on-surface-variant">
          Nhập lý do từ chối cho cuốn <strong>"{{ selectedListing?.book?.title }}"</strong>. Lý do này sẽ hiển thị trực tiếp cho người bán để họ biết và khắc phục.
        </p>

        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-on-surface">Lý do từ chối <span class="text-red-500">*</span></label>
          <textarea
            v-model="rejectionReason"
            rows="3"
            maxlength="2000"
            placeholder="Ví dụ: Ảnh chụp thực tế quá mờ không thể xác định tình trạng sách, hoặc thiếu thông tin tác giả..."
            class="w-full rounded-xl border border-outline-variant/40 bg-surface p-3 text-xs text-on-surface focus:border-primary focus:outline-none"
          ></textarea>
          <small v-if="rejectError" class="block font-bold text-red-600 text-xs">{{ rejectError }}</small>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="rounded-xl border border-outline-variant/30 px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container-low cursor-pointer" @click="closeRejectModal">
            Hủy
          </button>
          <button
            type="button"
            :disabled="actionBusy === selectedListing?.id"
            class="rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-red-700 disabled:opacity-50 cursor-pointer"
            @click="confirmReject"
          >
            {{ actionBusy === selectedListing?.id ? 'Đang gửi...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
