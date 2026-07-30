<script setup>
import { onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const articles = ref([])
const loading = ref(false)
const listLoading = ref(true)
const error = ref('')
const message = ref('')
const form = reactive({ title: '', excerpt: '', body: '', category: '', tags: '', home_featured: false, seo_title: '', seo_description: '' })

async function load() {
  listLoading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/articles')
    articles.value = response.data.data.data
  } catch (requestError) {
    articles.value = []
    error.value = requestError.response?.data?.message || 'Không thể tải danh sách bài viết.'
  } finally {
    listLoading.value = false
  }
}

async function createArticle() {
  loading.value = true
  message.value = ''
  try {
    await apiClient.post('/api/admin/articles', { ...form, tags: form.tags.split(',').map((tag) => tag.trim()).filter(Boolean) })
    Object.assign(form, { title: '', excerpt: '', body: '', category: '', tags: '', home_featured: false, seo_title: '', seo_description: '' })
    message.value = 'Đã lưu bản nháp và tạo phiên bản biên tập.'
    await load()
  } finally {
    loading.value = false
  }
}

async function transition(article, toStatus) {
  let reason = null
  if (['changes_requested', 'rejected', 'unpublished', 'archived'].includes(toStatus)) {
    reason = window.prompt('Nhập lý do bắt buộc:')
    if (!reason) return
  }
  const scheduledAt = toStatus === 'scheduled' ? window.prompt('Thời điểm ISO có múi giờ (ví dụ 2026-08-01T09:00:00+07:00):') : null
  if (toStatus === 'scheduled' && !scheduledAt) return
  await apiClient.patch(`/api/admin/articles/${article.id}/transition`, {
    to_status: toStatus,
    reason,
    scheduled_at: scheduledAt,
    operation_key: `cms-ui:${article.id}:${toStatus}:${Date.now()}`,
  })
  await load()
}

function actions(status) {
  return {
    draft: ['submitted'], submitted: ['under_review'], under_review: ['approved', 'changes_requested', 'rejected'],
    approved: ['scheduled', 'published'], scheduled: ['published', 'archived'], published: ['unpublished', 'archived'],
    unpublished: ['published', 'archived'], changes_requested: ['submitted'], rejected: ['draft', 'archived'], archived: [],
  }[status] || []
}

onMounted(load)
</script>

<template>
  <section class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">CMS bài viết</h1>
      <p class="mt-1 text-sm text-slate-500">Nội dung công khai chỉ xuất hiện sau khi hoàn tất quy trình duyệt.</p>
    </div>
    <form class="grid gap-4 rounded-2xl bg-white p-6 shadow-sm md:grid-cols-2" @submit.prevent="createArticle">
      <input v-model="form.title" required maxlength="255" aria-label="Tiêu đề bài viết" placeholder="Tiêu đề" class="min-h-11 rounded-lg border p-3 md:col-span-2" />
      <input v-model="form.category" aria-label="Chuyên mục bài viết" placeholder="Chuyên mục" class="min-h-11 rounded-lg border p-3" />
      <input v-model="form.tags" aria-label="Thẻ bài viết" placeholder="Thẻ, cách nhau bằng dấu phẩy" class="min-h-11 rounded-lg border p-3" />
      <textarea v-model="form.excerpt" maxlength="1000" aria-label="Tóm tắt bài viết" placeholder="Tóm tắt" class="rounded-lg border p-3 md:col-span-2"></textarea>
      <textarea v-model="form.body" required aria-label="Nội dung bài viết" placeholder="Nội dung HTML (sẽ được làm sạch phía máy chủ)" class="min-h-48 rounded-lg border p-3 md:col-span-2"></textarea>
      <input v-model="form.seo_title" aria-label="Tiêu đề SEO" placeholder="SEO title" class="min-h-11 rounded-lg border p-3" />
      <input v-model="form.seo_description" maxlength="320" aria-label="Mô tả SEO" placeholder="SEO description" class="min-h-11 rounded-lg border p-3" />
      <label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="form.home_featured" type="checkbox" class="h-5 w-5" /> Nổi bật trang chủ</label>
      <button type="submit" :disabled="loading" class="min-h-11 rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50">Lưu bản nháp</button>
      <p v-if="message" role="status" class="text-sm text-emerald-700 md:col-span-2">{{ message }}</p>
    </form>
    <div v-if="listLoading" role="status" aria-live="polite" class="rounded-2xl bg-white p-10 text-center text-slate-500 shadow-sm">Đang tải danh sách bài viết...</div>
    <div v-else-if="error" role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center">
      <p class="font-bold text-rose-800">Không thể tải danh sách bài viết</p>
      <p class="mt-2 text-sm text-rose-700">{{ error }}</p>
      <button type="button" class="mt-4 min-h-11 rounded-lg bg-rose-600 px-5 font-bold text-white" @click="load">Thử lại</button>
    </div>
    <div v-else class="overflow-x-auto rounded-2xl bg-white shadow-sm" role="region" aria-label="Danh sách bài viết CMS" tabindex="0">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50"><tr><th class="p-4">Bài viết</th><th class="p-4">Trạng thái</th><th class="p-4">Phiên bản</th><th class="p-4">Thao tác</th></tr></thead>
        <tbody>
          <tr v-for="article in articles" :key="article.id" class="border-t">
            <td class="p-4 font-medium">{{ article.title }}</td><td class="p-4">{{ article.status }}</td><td class="p-4">{{ article.revision }}</td>
            <td class="p-4"><button v-for="action in actions(article.status)" :key="action" type="button" class="mr-2 min-h-11 rounded bg-slate-100 px-3 py-2" @click="transition(article, action)">{{ action }}</button></td>
          </tr>
          <tr v-if="articles.length === 0"><td colspan="4" class="p-8 text-center text-slate-500">Chưa có bài viết nào.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
