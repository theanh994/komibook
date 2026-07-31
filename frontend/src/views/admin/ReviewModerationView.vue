<template>
  <section class="space-y-6">
    <header>
      <div><h2 class="text-2xl font-black text-on-surface">Đánh giá sách</h2><p class="text-sm text-on-surface-variant mt-1">Ưu tiên nội dung có báo cáo và giữ lịch sử quyết định.</p></div>
    </header>
    <form class="grid gap-3 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 md:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
      <label class="text-sm font-bold">Trạng thái<select v-model="filters.status" class="ui-field mt-2"><option value="">Tất cả</option><option value="published">Đang hiển thị</option><option value="hidden">Đã ẩn</option><option value="rejected">Đã từ chối</option></select></label>
      <label class="text-sm font-bold">Số sao<select v-model="filters.rating" class="ui-field mt-2"><option value="">Tất cả</option><option v-for="star in [5,4,3,2,1]" :key="star" :value="star">{{ star }} sao</option></select></label>
      <label class="text-sm font-bold">Gian hàng<select v-model="filters.vendor_id" class="ui-field mt-2"><option value="">Tất cả</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.shop_name }}</option></select></label>
      <label class="text-sm font-bold">Từ ngày<input v-model="filters.from" type="date" class="ui-field mt-2" /></label>
      <label class="text-sm font-bold">Đến ngày<input v-model="filters.to" type="date" class="ui-field mt-2" /></label>
      <div class="flex flex-col justify-end gap-2"><label class="flex min-h-11 items-center gap-2 text-sm font-bold"><input v-model="filters.reported" type="checkbox" class="h-5 w-5" /> Chỉ có báo cáo xấu</label><button type="submit" class="min-h-11 rounded-lg bg-primary px-4 font-bold text-on-primary">Áp dụng</button></div>
    </form>
    <div v-if="loading" role="status" aria-live="polite" class="py-16 text-center text-outline">Đang tải hàng đợi...</div>
    <div v-else-if="error" role="alert" class="py-10 px-5 rounded-3xl bg-error/5 border border-error/20 text-center">
      <p class="font-bold text-error">Không thể tải hàng đợi kiểm duyệt</p>
      <p class="mt-2 text-sm text-on-surface-variant">{{ error }}</p>
      <button type="button" class="mt-4 min-h-11 px-5 rounded-xl bg-error text-white font-bold" @click="loadReviews">Thử lại</button>
    </div>
    <div v-else-if="reviews.length === 0" class="py-16 rounded-3xl bg-surface-container-lowest border border-outline-variant/20 text-center text-outline">Không có đánh giá phù hợp.</div>
    <div v-else class="grid gap-3 2xl:grid-cols-2">
      <article v-for="review in reviews" :key="review.id" class="rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_250px]">
          <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2 text-xs"><span class="font-black text-on-surface">{{ review.book?.title }}</span><span class="px-2 py-1 rounded-full bg-primary/10 text-primary font-bold">{{ review.rating }}/5 sao</span><span v-if="review.verified_purchase" class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">Đã mua</span><span v-if="review.open_reports_count" class="px-2 py-1 rounded-full bg-error/10 text-error font-bold">{{ review.open_reports_count }} báo cáo</span></div>
            <p class="line-clamp-3 text-sm leading-5 text-on-surface">{{ review.comment || 'Không có nội dung chữ.' }}</p><p class="text-xs text-outline">{{ review.book?.vendor?.shop_name || 'Không rõ gian hàng' }} · {{ review.user?.name }} · {{ formatDate(review.created_at) }}</p>
          </div>
          <div class="space-y-2">
            <label :for="`review-reason-${review.id}`" class="sr-only">Lý do khi ẩn hoặc từ chối</label>
            <input :id="`review-reason-${review.id}`" v-model="reasons[review.id]" placeholder="Lý do nếu ẩn hoặc từ chối" class="ui-field text-xs" />
            <div class="grid grid-cols-3 gap-2">
              <button type="button" @click="moderate(review, 'published')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-700 border-none cursor-pointer">Hiện</button>
              <button type="button" @click="moderate(review, 'hidden')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-amber-100 text-amber-700 border-none cursor-pointer">Ẩn</button>
              <button type="button" @click="moderate(review, 'rejected')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-error/10 text-error border-none cursor-pointer">Từ chối</button>
            </div>
          </div>
        </div>
      </article>
    </div>
    <nav v-if="lastPage > 1" class="flex items-center justify-end gap-3" aria-label="Phân trang đánh giá"><button type="button" class="ui-btn ui-btn-secondary" :disabled="page <= 1" @click="changePage(page - 1)">Trước</button><span class="text-sm font-bold">Trang {{ page }}/{{ lastPage }}</span><button type="button" class="ui-btn ui-btn-secondary" :disabled="page >= lastPage" @click="changePage(page + 1)">Sau</button></nav>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()
const loading = ref(false)
const error = ref('')
const reviews = ref([])
const filters = ref({ status: '', rating: '', vendor_id: '', from: '', to: '', reported: false })
const reasons = ref({})
const vendors = ref([])
const page = ref(1)
const lastPage = ref(1)

const formatDate = (value) => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : '—'

const loadReviews = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/reviews/moderation', {
      params: {
        ...filters.value,
        reported: filters.value.reported ? 1 : undefined,
        page: page.value,
        per_page: 10,
      }
    })
    reviews.value = response.data.data?.data || []
    vendors.value = response.data.filters?.vendors || vendors.value
    lastPage.value = response.data.data?.last_page || 1
  } catch (requestError) {
    const message = requestError.response?.data?.message || 'Không thể tải hàng đợi.'
    reviews.value = []
    // Giữ lỗi ngay trong ngữ cảnh trang bên cạnh thông báo tạm thời.
    error.value = message
    toast.add({ severity: 'error', summary: 'Lỗi', detail: message, life: 4000 })
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  page.value = 1
  loadReviews()
}

const changePage = (nextPage) => {
  page.value = nextPage
  loadReviews()
}

const moderate = async (review, nextStatus) => {
  if (nextStatus !== 'published' && !reasons.value[review.id]?.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do', detail: 'Vui lòng ghi lý do kiểm duyệt.', life: 3000 })
    return
  }
  try {
    await apiClient.patch(`/api/admin/reviews/${review.id}/moderate`, {
      status: nextStatus,
      reason: reasons.value[review.id] || null,
      operation_key: crypto.randomUUID()
    })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Quyết định đã được ghi vào lịch sử.', life: 3000 })
    await loadReviews()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể kiểm duyệt', detail: error.response?.data?.message || 'Vui lòng đăng nhập lại rồi thử lại.', life: 4000 })
  }
}

onMounted(loadReviews)
</script>
