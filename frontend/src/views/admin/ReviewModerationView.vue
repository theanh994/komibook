<template>
  <section class="space-y-6">
    <header class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div><h1 class="text-2xl font-black text-on-surface">Kiểm duyệt đánh giá</h1><p class="text-sm text-on-surface-variant mt-1">Ưu tiên nội dung có báo cáo và giữ lịch sử quyết định.</p></div>
      <select v-model="status" @change="loadReviews" class="h-11 px-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest text-sm">
        <option value="">Tất cả trạng thái</option><option value="published">Đang hiển thị</option><option value="hidden">Đã ẩn</option><option value="rejected">Đã từ chối</option>
      </select>
    </header>
    <div v-if="loading" class="py-16 text-center text-outline">Đang tải hàng đợi...</div>
    <div v-else-if="reviews.length === 0" class="py-16 rounded-3xl bg-surface-container-lowest border border-outline-variant/20 text-center text-outline">Không có đánh giá phù hợp.</div>
    <div v-else class="space-y-4">
      <article v-for="review in reviews" :key="review.id" class="p-5 rounded-3xl bg-surface-container-lowest border border-outline-variant/20 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
          <div class="min-w-0 space-y-3">
            <div class="flex flex-wrap items-center gap-2 text-xs"><span class="font-black text-on-surface">{{ review.book?.title }}</span><span class="px-2 py-1 rounded-full bg-primary/10 text-primary font-bold">{{ review.rating }}/5 sao</span><span v-if="review.verified_purchase" class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">Đã mua</span><span v-if="review.open_reports_count" class="px-2 py-1 rounded-full bg-error/10 text-error font-bold">{{ review.open_reports_count }} báo cáo</span></div>
            <p class="text-sm text-on-surface leading-relaxed">{{ review.comment || 'Không có nội dung chữ.' }}</p><p class="text-xs text-outline">Người viết: {{ review.user?.name }} · Trạng thái: {{ review.moderation_status }}</p>
          </div>
          <div class="w-full lg:w-72 space-y-3 shrink-0"><textarea v-model="reasons[review.id]" rows="2" placeholder="Lý do khi ẩn/từ chối" class="w-full p-3 rounded-xl border border-outline-variant/40 bg-transparent text-xs resize-none"></textarea><div class="grid grid-cols-3 gap-2"><button @click="moderate(review, 'published')" class="px-2 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-700 border-none cursor-pointer">Hiện</button><button @click="moderate(review, 'hidden')" class="px-2 py-2 rounded-xl text-xs font-bold bg-amber-100 text-amber-700 border-none cursor-pointer">Ẩn</button><button @click="moderate(review, 'rejected')" class="px-2 py-2 rounded-xl text-xs font-bold bg-error/10 text-error border-none cursor-pointer">Từ chối</button></div></div>
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
const reviews = ref([])
const status = ref('')
const reasons = ref({})

const loadReviews = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/admin/reviews/moderation', {
      params: status.value ? { status: status.value } : {}
    })
    reviews.value = response.data.data?.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: error.response?.data?.message || 'Không thể tải hàng đợi.', life: 4000 })
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
