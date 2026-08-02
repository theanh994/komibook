<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/axios'

const props = defineProps({
  role: { type: String, required: true },
})

const router = useRouter()
const articles = ref([])
const metrics = ref({})
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const transitionPanel = reactive({ article: null, status: '', reason: '', scheduled_at: '', saving: false })
const filters = reactive({ search: '', status: '', type: '' })
const page = ref(1)
const lastPage = ref(1)
const baseUrl = computed(() => `/api/${props.role}/articles`)
const routeBase = computed(() => `/${props.role}/articles`)
const isAdmin = computed(() => props.role === 'admin')

const statuses = [
  ['draft', 'Bản nháp'], ['submitted', 'Chờ tiếp nhận'], ['under_review', 'Đang duyệt'],
  ['changes_requested', 'Yêu cầu sửa'], ['approved', 'Đã phê duyệt'], ['scheduled', 'Đã lên lịch'],
  ['published', 'Đã xuất bản'], ['unpublished', 'Đã gỡ'], ['rejected', 'Từ chối'], ['archived', 'Lưu trữ'],
]
const types = [
  ['news', 'Tin tức'], ['review', 'Review sách'], ['book_introduction', 'Giới thiệu sách'],
  ['event', 'Sự kiện'], ['vendor_announcement', 'Thông báo nhà bán'],
]
const statusLabel = (value) => statuses.find(([key]) => key === value)?.[1] || value
const typeLabel = (value) => types.find(([key]) => key === value)?.[1] || 'Tin tức'
const statusTone = (value) => ({
  published: 'bg-emerald-100 text-emerald-800',
  approved: 'bg-blue-100 text-blue-800',
  scheduled: 'bg-violet-100 text-violet-800',
  submitted: 'bg-amber-100 text-amber-900',
  under_review: 'bg-amber-100 text-amber-900',
  changes_requested: 'bg-rose-100 text-rose-800',
  rejected: 'bg-rose-100 text-rose-800',
}[value] || 'bg-surface-container text-on-surface-variant')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const [listResponse, metricResponse] = await Promise.all([
      apiClient.get(baseUrl.value, { params: { ...filters, page: page.value } }),
      apiClient.get(`${baseUrl.value}/analytics`),
    ])
    const paginator = listResponse.data.data
    articles.value = paginator.data || []
    lastPage.value = paginator.last_page || 1
    metrics.value = metricResponse.data.data || {}
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải dữ liệu Newsroom.'
  } finally {
    loading.value = false
  }
}

const reset = () => {
  Object.assign(filters, { search: '', status: '', type: '' })
  page.value = 1
  load()
}

const edit = (article) => router.push(`${routeBase.value}/${article.id}/edit`)

const openTransition = (article, status) => {
  actionError.value = ''
  Object.assign(transitionPanel, { article, status, reason: '', scheduled_at: '', saving: false })
  window.requestAnimationFrame(() => document.querySelector('#article-transition-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
}

const closeTransition = () => Object.assign(transitionPanel, { article: null, status: '', reason: '', scheduled_at: '', saving: false })

const submitTransition = async () => {
  const article = transitionPanel.article
  const status = transitionPanel.status
  if (!article || !status) return
  const reasonRequired = ['changes_requested', 'rejected', 'unpublished', 'archived'].includes(status)
  if (reasonRequired && !transitionPanel.reason.trim()) {
    actionError.value = 'Vui lòng nhập lý do rõ ràng để người viết biết cần xử lý gì.'
    return
  }
  if (status === 'scheduled' && !transitionPanel.scheduled_at) {
    actionError.value = 'Vui lòng chọn thời điểm xuất bản.'
    return
  }
  transitionPanel.saving = true
  try {
    await apiClient.patch(`${baseUrl.value}/${article.id}/transition`, {
      to_status: status,
      reason: transitionPanel.reason || null,
      scheduled_at: transitionPanel.scheduled_at || null,
      operation_key: `newsroom:${article.id}:${status}:${Date.now()}`,
    })
    closeTransition()
    await load()
  } catch (requestError) {
    actionError.value = requestError.response?.data?.message || 'Không thể chuyển trạng thái bài viết.'
    transitionPanel.saving = false
  }
}

const actions = (status) => {
  if (!isAdmin.value) return []
  return {
    submitted: [['under_review', 'Tiếp nhận']],
    under_review: [['approved', 'Phê duyệt'], ['changes_requested', 'Yêu cầu sửa'], ['rejected', 'Từ chối']],
    approved: [['scheduled', 'Lên lịch'], ['published', 'Xuất bản']],
    scheduled: [['published', 'Xuất bản ngay'], ['archived', 'Lưu trữ']],
    published: [['unpublished', 'Gỡ bài'], ['archived', 'Lưu trữ']],
    unpublished: [['published', 'Đăng lại'], ['archived', 'Lưu trữ']],
    rejected: [['draft', 'Đưa về nháp'], ['archived', 'Lưu trữ']],
  }[status] || []
}

const formatDate = (value) => value
  ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
  : '—'

onMounted(load)
</script>

<template>
  <section class="ui-page space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.14em] text-secondary">KomiBook Newsroom</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-primary">Bài viết &amp; Tin tức</h1>
        <p class="mt-2 max-w-2xl text-on-surface-variant">
          {{ isAdmin ? 'Quản lý, biên tập và xét duyệt nội dung toàn hệ thống.' : 'Soạn thảo nội dung cho gian hàng và theo dõi phản hồi biên tập.' }}
        </p>
      </div>
      <button class="ui-btn ui-btn-primary" type="button" @click="router.push(`${routeBase}/create`)">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_square</span>
        Viết bài mới
      </button>
    </header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Chỉ số Newsroom">
      <div class="ui-panel"><p class="text-sm text-on-surface-variant">Đã xuất bản</p><p class="mt-2 text-3xl font-bold text-primary">{{ metrics.published || 0 }}</p></div>
      <div class="ui-panel"><p class="text-sm text-on-surface-variant">Đang chờ duyệt</p><p class="mt-2 text-3xl font-bold text-primary">{{ metrics.pending_review || 0 }}</p></div>
      <div class="ui-panel"><p class="text-sm text-on-surface-variant">Lượt đọc</p><p class="mt-2 text-3xl font-bold text-primary">{{ Number(metrics.views || 0).toLocaleString('vi-VN') }}</p></div>
      <div class="ui-panel"><p class="text-sm text-on-surface-variant">Nhấp vào sách</p><p class="mt-2 text-3xl font-bold text-primary">{{ Number(metrics.book_clicks || 0).toLocaleString('vi-VN') }}</p></div>
    </div>

    <div class="ui-panel grid gap-4 md:grid-cols-[1fr_220px_220px_auto]">
      <label class="text-sm font-semibold text-on-surface">Tìm bài viết
        <input v-model.trim="filters.search" class="ui-field mt-2" type="search" placeholder="Tiêu đề hoặc người viết" @keyup.enter="load" />
      </label>
      <label class="text-sm font-semibold text-on-surface">Trạng thái
        <select v-model="filters.status" class="ui-field mt-2">
          <option value="">Tất cả trạng thái</option>
          <option v-for="[value, label] in statuses" :key="value" :value="value">{{ label }}</option>
        </select>
      </label>
      <label class="text-sm font-semibold text-on-surface">Loại bài
        <select v-model="filters.type" class="ui-field mt-2">
          <option value="">Tất cả loại bài</option>
          <option v-for="[value, label] in types" :key="value" :value="value">{{ label }}</option>
        </select>
      </label>
      <div class="flex items-end gap-2">
        <button class="ui-btn ui-btn-primary" type="button" @click="page = 1; load()">Lọc</button>
        <button class="ui-btn ui-btn-secondary" type="button" @click="reset">Đặt lại</button>
      </div>
    </div>

    <p v-if="actionError" class="ui-alert ui-alert-error" role="alert">{{ actionError }}</p>
    <section v-if="transitionPanel.article" id="article-transition-panel" class="ui-panel border-2 border-primary-container" aria-labelledby="transition-panel-title">
      <div class="flex flex-wrap items-start justify-between gap-4"><div class="min-w-0"><p class="text-sm font-bold text-secondary">Xử lý ngay trên trang này</p><h2 id="transition-panel-title" class="mt-1 break-words text-xl font-bold text-primary">{{ transitionPanel.article.title }}</h2><p class="mt-2 text-sm text-on-surface-variant">{{ statusLabel(transitionPanel.article.status) }} → <strong>{{ statusLabel(transitionPanel.status) }}</strong></p></div><button class="ui-btn ui-btn-secondary" type="button" @click="closeTransition">Đóng</button></div>
      <div class="mt-5 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
        <label v-if="['changes_requested', 'rejected', 'unpublished', 'archived'].includes(transitionPanel.status)" class="block text-sm font-semibold">Lý do và hướng xử lý
          <textarea v-model="transitionPanel.reason" class="ui-field mt-2 min-h-28" maxlength="2000" placeholder="Nêu rõ nội dung cần sửa hoặc nguyên nhân thay đổi trạng thái."></textarea>
        </label>
        <label v-else-if="transitionPanel.status === 'scheduled'" class="block text-sm font-semibold">Thời điểm xuất bản
          <input v-model="transitionPanel.scheduled_at" class="ui-field mt-2" type="datetime-local" />
        </label>
        <p v-else class="rounded-lg bg-surface-container p-4 text-sm leading-6 text-on-surface-variant">Hệ thống sẽ ghi nhận người thao tác, thời gian và trạng thái mới trong lịch sử bài viết.</p>
        <button class="ui-btn ui-btn-primary" type="button" :disabled="transitionPanel.saving" @click="submitTransition">{{ transitionPanel.saving ? 'Đang xử lý…' : `Xác nhận ${statusLabel(transitionPanel.status).toLowerCase()}` }}</button>
      </div>
    </section>
    <div v-if="loading" class="space-y-3" role="status" aria-label="Đang tải bài viết">
      <div v-for="index in 4" :key="index" class="ui-skeleton h-24"></div>
    </div>
    <div v-else-if="error" class="ui-empty-state" role="alert">
      <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">cloud_off</span>
      <p class="font-bold">{{ error }}</p>
      <button class="ui-btn ui-btn-secondary" type="button" @click="load">Thử lại</button>
    </div>
    <div v-else-if="!articles.length" class="ui-empty-state">
      <span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">newspaper</span>
      <p class="font-bold">Chưa có bài viết phù hợp bộ lọc.</p>
    </div>
    <div v-else class="ui-table-scroll bg-surface-container-lowest" role="region" aria-label="Danh sách bài viết" tabindex="0">
      <table class="w-full min-w-[900px] border-collapse text-left">
        <thead class="bg-surface-container-low text-sm text-on-surface-variant">
          <tr><th class="p-4">Bài viết</th><th class="p-4">Loại</th><th class="p-4">Đơn vị đăng</th><th class="p-4">Trạng thái</th><th class="p-4">Cập nhật</th><th class="p-4">Thao tác</th></tr>
        </thead>
        <tbody>
          <tr v-for="article in articles" :key="article.id" class="border-t border-outline-variant/40 align-top">
            <td class="p-4">
              <button type="button" class="min-h-11 max-w-md text-left font-bold text-primary hover:underline" @click="edit(article)">{{ article.title }}</button>
              <p class="line-clamp-2 text-sm text-on-surface-variant">{{ article.excerpt || 'Chưa có tóm tắt.' }}</p>
            </td>
            <td class="p-4 text-sm">{{ typeLabel(article.article_type) }}</td>
            <td class="p-4 text-sm">{{ article.vendor?.shop_name || article.creator?.name || 'KomiBook' }}</td>
            <td class="p-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusTone(article.status)">{{ statusLabel(article.status) }}</span></td>
            <td class="p-4 text-sm text-on-surface-variant">{{ formatDate(article.updated_at) }}</td>
            <td class="p-4">
              <div class="flex max-w-xs flex-wrap gap-2">
                <button class="ui-btn ui-btn-secondary px-3 text-sm" type="button" @click="edit(article)">Chỉnh sửa</button>
                <button v-for="[value, label] in actions(article.status)" :key="value" class="ui-btn ui-btn-primary px-3 text-sm" type="button" @click="openTransition(article, value)">{{ label }}</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="lastPage > 1" class="flex items-center justify-end gap-3" aria-label="Phân trang bài viết">
      <button class="ui-btn ui-btn-secondary" type="button" :disabled="page <= 1" @click="page--; load()">Trang trước</button>
      <span class="text-sm">Trang {{ page }} / {{ lastPage }}</span>
      <button class="ui-btn ui-btn-secondary" type="button" :disabled="page >= lastPage" @click="page++; load()">Trang sau</button>
    </nav>
  </section>
</template>
