<template>
  <section class="space-y-6">
    <header class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div><h1 class="text-2xl font-black text-on-surface">Kiểm duyệt đánh giá</h1><p class="text-sm text-on-surface-variant mt-1">Ưu tiên nội dung có báo cáo và giữ lịch sử quyết định.</p></div>
      <label for="review-status" class="sr-only">Lọc đánh giá theo trạng thái</label>
      <select id="review-status" v-model="status" @change="loadReviews" class="h-11 px-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest text-sm">
        <option value="">Tất cả trạng thái</option><option value="published">Đang hiển thị</option><option value="hidden">Đã ẩn</option><option value="rejected">Đã từ chối</option>
      </select>
    </header>
    <div v-if="loading" role="status" aria-live="polite" class="py-16 text-center text-outline">Đang tải hàng đợi...</div>
    <div v-else-if="error" role="alert" class="py-10 px-5 rounded-3xl bg-error/5 border border-error/20 text-center">
      <p class="font-bold text-error">Không thể tải hàng đợi kiểm duyệt</p>
      <p class="mt-2 text-sm text-on-surface-variant">{{ error }}</p>
      <button type="button" class="mt-4 min-h-11 px-5 rounded-xl bg-error text-white font-bold" @click="loadReviews">Thử lại</button>
    </div>
    <div v-else-if="reviews.length === 0" class="py-16 rounded-3xl bg-surface-container-lowest border border-outline-variant/20 text-center text-outline">Không có đánh giá phù hợp.</div>
    <div v-else class="space-y-4">
      <article v-for="review in reviews" :key="review.id" class="p-5 rounded-3xl bg-surface-container-lowest border border-outline-variant/20 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
          <div class="min-w-0 space-y-3">
            <div class="flex flex-wrap items-center gap-2 text-xs"><span class="font-black text-on-surface">{{ review.book?.title }}</span><span class="px-2 py-1 rounded-full bg-primary/10 text-primary font-bold">{{ review.rating }}/5 sao</span><span v-if="review.verified_purchase" class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">Đã mua</span><span v-if="review.open_reports_count" class="px-2 py-1 rounded-full bg-error/10 text-error font-bold">{{ review.open_reports_count }} báo cáo</span></div>
            <p class="text-sm text-on-surface leading-relaxed">{{ review.comment || 'Không có nội dung chữ.' }}</p><p class="text-xs text-outline">Người viết: {{ review.user?.name }} · Trạng thái: {{ review.moderation_status }}</p>
          </div>
          <div class="w-full lg:w-72 space-y-3 shrink-0">
            <label :for="`review-reason-${review.id}`" class="block text-xs font-bold text-on-surface-variant">Lý do khi ẩn hoặc từ chối</label>
            <textarea :id="`review-reason-${review.id}`" v-model="reasons[review.id]" rows="2" placeholder="Nhập lý do kiểm duyệt" class="w-full p-3 rounded-xl border border-outline-variant/40 bg-transparent text-xs resize-none"></textarea>
            <div class="grid grid-cols-3 gap-2">
              <button type="button" @click="moderate(review, 'published')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-700 border-none cursor-pointer">Hiện</button>
              <button type="button" @click="moderate(review, 'hidden')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-amber-100 text-amber-700 border-none cursor-pointer">Ẩn</button>
              <button type="button" @click="moderate(review, 'rejected')" class="min-h-11 px-2 py-2 rounded-xl text-xs font-bold bg-error/10 text-error border-none cursor-pointer">Từ chối</button>
            </div>
          </div>
        </div>
      </article>
    </div>
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
const status = ref('')
const reasons = ref({})

const loadReviews = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/reviews/moderation', {
      params: status.value ? { status: status.value } : {}
    })
    reviews.value = response.data.data?.data || []
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
