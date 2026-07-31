<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const router = useRouter()
const submissions = ref([])
const loading = ref(true)
const error = ref('')
const status = ref('pending')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/article-submissions', { params: { status: status.value } })
    submissions.value = response.data.data.data || []
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải hàng đợi review.'
  } finally {
    loading.value = false
  }
}

const moderate = async (submission, action) => {
  const reason = action === 'reject' ? window.prompt('Nhập lý do từ chối để thông báo cho độc giả:') : null
  if (action === 'reject' && !reason) return
  const response = await apiClient.patch(`/api/admin/article-submissions/${submission.id}`, { action, reason })
  if (action === 'convert' && response.data.data.converted_article_id) {
    await router.push(`/admin/articles/${response.data.data.converted_article_id}/edit`)
    return
  }
  await load()
}

onMounted(load)
</script>

<template>
  <section class="ui-page space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div><p class="text-sm font-bold uppercase tracking-wider text-secondary">Cộng đồng KomiBook</p><h1 class="mt-2 text-3xl font-bold text-primary">Bài review tiềm năng</h1><p class="mt-2 text-on-surface-variant">Review được duyệt sẽ chuyển thành bản nháp để biên tập, không tự động xuất bản.</p></div>
      <label class="text-sm font-bold">Lọc trạng thái
        <select v-model="status" class="ui-field mt-2 min-w-52" @change="load"><option value="pending">Chờ duyệt</option><option value="converted">Đã chuyển bản nháp</option><option value="rejected">Từ chối</option></select>
      </label>
    </header>
    <div v-if="loading" class="ui-skeleton h-64" role="status" aria-label="Đang tải review"></div>
    <div v-else-if="error" class="ui-empty-state" role="alert"><p class="font-bold">{{ error }}</p><button class="ui-btn ui-btn-secondary" @click="load">Thử lại</button></div>
    <div v-else-if="!submissions.length" class="ui-empty-state"><span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">rate_review</span><p class="font-bold">Không có review ở trạng thái này.</p></div>
    <div v-else class="grid gap-5 lg:grid-cols-2">
      <article v-for="submission in submissions" :key="submission.id" class="ui-panel flex flex-col">
        <div class="flex gap-4 rounded-xl bg-surface-container-low p-4">
          <img v-if="submission.book?.cover_image" :src="`/storage/${submission.book.cover_image}`" :alt="`Bìa ${submission.book.title}`" class="h-28 w-20 object-contain" />
          <div><p class="text-xs font-bold uppercase tracking-wider text-secondary">Đang đánh giá</p><h2 class="mt-2 text-xl font-bold text-primary">{{ submission.book?.title }}</h2><p class="mt-2 text-sm text-on-surface-variant">{{ submission.word_count }} từ · <span v-if="submission.verified_purchase" class="font-bold text-commerce">Đã mua tại KomiBook</span><span v-else>Chưa xác minh mua hàng</span></p></div>
        </div>
        <div class="mt-5"><p class="font-bold">{{ submission.user?.name }}</p><h3 class="mt-3 text-xl font-bold">{{ submission.title }}</h3><p class="mt-3 line-clamp-6 whitespace-pre-wrap font-literata leading-7">{{ submission.body.replace(/<[^>]+>/g, '') }}</p></div>
        <div v-if="status === 'pending'" class="mt-auto flex gap-2 pt-6"><button class="ui-btn ui-btn-commerce flex-1" type="button" @click="moderate(submission, 'convert')">Duyệt để biên tập</button><button class="ui-btn ui-btn-secondary" type="button" @click="moderate(submission, 'reject')">Từ chối</button></div>
      </article>
    </div>
  </section>
</template>
