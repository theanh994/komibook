<script setup>
import { onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const articles = ref([])
const loading = ref(false)
const message = ref('')
const form = reactive({ title: '', excerpt: '', body: '', category: '', tags: '', home_featured: false, seo_title: '', seo_description: '' })

async function load() {
  const response = await apiClient.get('/api/admin/articles')
  articles.value = response.data.data.data
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
      <input v-model="form.title" required maxlength="255" placeholder="Tiêu đề" class="rounded-lg border p-3 md:col-span-2" />
      <input v-model="form.category" placeholder="Chuyên mục" class="rounded-lg border p-3" />
      <input v-model="form.tags" placeholder="Thẻ, cách nhau bằng dấu phẩy" class="rounded-lg border p-3" />
      <textarea v-model="form.excerpt" maxlength="1000" placeholder="Tóm tắt" class="rounded-lg border p-3 md:col-span-2"></textarea>
      <textarea v-model="form.body" required placeholder="Nội dung HTML (sẽ được làm sạch phía máy chủ)" class="min-h-48 rounded-lg border p-3 md:col-span-2"></textarea>
      <input v-model="form.seo_title" placeholder="SEO title" class="rounded-lg border p-3" />
      <input v-model="form.seo_description" maxlength="320" placeholder="SEO description" class="rounded-lg border p-3" />
      <label class="flex items-center gap-2 text-sm"><input v-model="form.home_featured" type="checkbox" /> Nổi bật trang chủ</label>
      <button :disabled="loading" class="rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white">Lưu bản nháp</button>
      <p v-if="message" class="text-sm text-emerald-700 md:col-span-2">{{ message }}</p>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50"><tr><th class="p-4">Bài viết</th><th class="p-4">Trạng thái</th><th class="p-4">Phiên bản</th><th class="p-4">Thao tác</th></tr></thead>
        <tbody><tr v-for="article in articles" :key="article.id" class="border-t"><td class="p-4 font-medium">{{ article.title }}</td><td class="p-4">{{ article.status }}</td><td class="p-4">{{ article.revision }}</td><td class="p-4"><button v-for="action in actions(article.status)" :key="action" class="mr-2 rounded bg-slate-100 px-3 py-2" @click="transition(article, action)">{{ action }}</button></td></tr></tbody>
      </table>
    </div>
  </section>
</template>
