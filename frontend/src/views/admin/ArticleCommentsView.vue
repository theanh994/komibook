<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'

const comments = ref([])
const loading = ref(true)
const error = ref('')
const status = ref('pending')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/article-comments', { params: { status: status.value } })
    comments.value = response.data.data.data || []
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải bình luận.'
  } finally {
    loading.value = false
  }
}

const moderate = async (comment, nextStatus) => {
  const reasonRequired = ['rejected', 'spam', 'hidden'].includes(nextStatus)
  const reason = reasonRequired ? window.prompt('Nhập lý do kiểm duyệt:') : null
  if (reasonRequired && !reason) return
  await apiClient.patch(`/api/admin/article-comments/${comment.id}`, {
    status: nextStatus,
    reason,
    operation_key: `comment:${comment.id}:${nextStatus}:${Date.now()}`,
  })
  await load()
}

onMounted(load)
</script>

<template>
  <section class="ui-page space-y-6">
    <header><p class="text-sm font-bold uppercase tracking-wider text-secondary">Newsroom</p><h2 class="mt-2 text-2xl font-bold text-primary">Bình luận bài viết</h2><p class="mt-2 text-on-surface-variant">Email khách không bao giờ được hiển thị; mọi quyết định đều có lịch sử kiểm duyệt.</p></header>
    <div class="ui-panel flex flex-wrap items-end gap-4">
      <label class="text-sm font-bold">Trạng thái
        <select v-model="status" class="ui-field mt-2 min-w-56" @change="load">
          <option value="pending">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="rejected">Từ chối</option><option value="spam">Spam</option><option value="hidden">Đã ẩn</option>
        </select>
      </label>
    </div>
    <div v-if="loading" class="ui-skeleton h-64" role="status" aria-label="Đang tải bình luận"></div>
    <div v-else-if="error" class="ui-empty-state" role="alert"><p class="font-bold">{{ error }}</p><button class="ui-btn ui-btn-secondary" @click="load">Thử lại</button></div>
    <div v-else-if="!comments.length" class="ui-empty-state"><span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">forum</span><p class="font-bold">Không có bình luận ở trạng thái này.</p></div>
    <div v-else class="grid gap-4">
      <article v-for="comment in comments" :key="comment.id" class="ui-panel">
        <div class="flex flex-wrap justify-between gap-3">
          <div><p class="font-bold">{{ comment.user?.name || comment.guest_name || 'Khách' }}</p><RouterLink :to="`/blog/${comment.article.slug}`" class="text-sm font-semibold text-primary hover:underline">{{ comment.article.title }}</RouterLink></div>
          <time class="text-sm text-on-surface-variant">{{ new Date(comment.created_at).toLocaleString('vi-VN') }}</time>
        </div>
        <p class="mt-4 whitespace-pre-wrap leading-7">{{ comment.body }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
          <button class="ui-btn ui-btn-commerce" type="button" @click="moderate(comment, 'approved')">Duyệt</button>
          <button class="ui-btn ui-btn-secondary" type="button" @click="moderate(comment, 'rejected')">Từ chối</button>
          <button class="ui-btn ui-btn-secondary" type="button" @click="moderate(comment, 'spam')">Đánh dấu spam</button>
          <button class="ui-btn ui-btn-secondary" type="button" @click="moderate(comment, 'hidden')">Ẩn</button>
        </div>
      </article>
    </div>
  </section>
</template>
