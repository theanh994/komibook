<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'

const articles = ref([])
const loading = ref(true)
const error = ref('')

const fetchArticles = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/articles')
    articles.value = response.data?.data?.data || []
  } catch {
    articles.value = []
    error.value = 'Không thể tải bài viết lúc này.'
  } finally {
    loading.value = false
  }
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const publisherName = (article) => (
  article.creator?.vendor?.shop_name
  || article.creator?.name
  || 'KomiBook'
)

const formatDate = (value) => value
  ? new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(value))
  : 'Ngày xuất bản đang cập nhật'

onMounted(fetchArticles)
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10 text-on-surface md:px-gutter md:py-16">
    <section class="mx-auto max-w-[1280px]" aria-labelledby="blog-title">
      <nav class="mb-8 text-sm text-on-surface-variant" aria-label="Đường dẫn">
        <RouterLink to="/" class="inline-flex min-h-11 items-center font-semibold text-primary no-underline">Trang chủ</RouterLink>
        <span class="mx-2" aria-hidden="true">/</span>
        <span aria-current="page">Tin tức</span>
      </nav>

      <p class="text-sm font-bold uppercase tracking-[0.18em] text-secondary">KomiBook Editorial</p>
      <h1 id="blog-title" class="mt-3 text-3xl font-bold tracking-tight text-primary md:text-5xl">Tin tức &amp; sự kiện</h1>
      <p class="mt-4 max-w-2xl text-base leading-7 text-on-surface-variant">Bài viết mới từ KomiBook, nhà xuất bản và các đối tác đã qua quy trình biên tập.</p>

      <div v-if="loading" class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3" role="status" aria-label="Đang tải bài viết">
        <article v-for="i in 6" :key="i" class="overflow-hidden rounded-2xl border border-outline-variant/20 bg-surface-container-lowest">
          <div class="aspect-[16/9] animate-pulse bg-surface-container-high"></div>
          <div class="space-y-3 p-6"><div class="h-4 w-1/3 animate-pulse rounded bg-surface-container-high"></div><div class="h-7 animate-pulse rounded bg-surface-container-high"></div><div class="h-16 animate-pulse rounded bg-surface-container-high"></div></div>
        </article>
      </div>

      <div v-else-if="error" class="ui-state-panel mt-10" role="alert">
        <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">newspaper</span>
        <p class="mt-3 font-bold">{{ error }}</p>
        <button type="button" class="ui-button ui-button-secondary mt-5" @click="fetchArticles">Thử lại</button>
      </div>

      <div v-else-if="articles.length" class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <article v-for="article in articles" :key="article.id" class="flex overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container-lowest shadow-sm">
          <div class="flex w-full flex-col">
            <div class="flex aspect-[16/9] items-center justify-center bg-primary-container">
              <img v-if="article.cover_image" :src="getCoverUrl(article.cover_image)" :alt="article.title" class="h-full w-full object-cover" loading="lazy" />
              <span v-else class="material-symbols-outlined text-5xl text-on-primary-container" aria-hidden="true">article</span>
            </div>
            <div class="flex flex-grow flex-col p-6">
              <p class="text-xs font-bold uppercase tracking-wider text-secondary">{{ article.category?.name || 'Bản tin' }}</p>
              <h2 class="mt-3 text-xl font-bold leading-snug">{{ article.title }}</h2>
              <p class="mt-3 text-sm text-on-surface-variant">{{ publisherName(article) }} · {{ formatDate(article.published_at) }}</p>
              <p class="mt-3 line-clamp-3 text-sm leading-6 text-on-surface-variant">{{ article.excerpt }}</p>
              <RouterLink :to="`/blog/${article.slug}`" class="mt-auto inline-flex min-h-11 items-center pt-5 font-bold text-primary no-underline">
                Đọc bài viết
                <span class="material-symbols-outlined ml-1 text-[18px]" aria-hidden="true">arrow_forward</span>
              </RouterLink>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="ui-state-panel mt-10">
        <span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">article</span>
        <p class="mt-3 font-bold">Chưa có bài viết đã xuất bản.</p>
        <p class="mt-2 text-sm text-on-surface-variant">Nội dung chỉ xuất hiện sau khi được duyệt và phát hành hợp lệ.</p>
      </div>
    </section>
  </main>
</template>
