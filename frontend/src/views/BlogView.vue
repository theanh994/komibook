<script setup>
import { onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'
import { articleTypographyStyle } from '@/utils/articleTypography'

const articles = ref([])
const latestArticles = ref([])
const loading = ref(true)
const error = ref('')
const page = ref(1)
const lastPage = ref(1)
const filters = reactive({ search: '', type: '' })
const types = [['', 'Tất cả'], ['news', 'Tin tức'], ['review', 'Review sách'], ['book_introduction', 'Giới thiệu sách'], ['event', 'Sự kiện']]
const imageFailures = ref(new Set())

const fetchArticles = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/articles', { params: { ...filters, page: page.value, per_page: 10 } })
    articles.value = response.data?.data?.data || []
    lastPage.value = response.data?.data?.last_page || 1
  } catch {
    articles.value = []
    error.value = 'Không thể tải bản tin lúc này.'
  } finally {
    loading.value = false
  }
}

const fetchLatestArticles = async () => {
  try {
    const response = await apiClient.get('/api/articles', { params: { page: 1, per_page: 5 } })
    latestArticles.value = response.data?.data?.data || []
  } catch {
    latestArticles.value = []
  }
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (/^https?:\/\//.test(path) || path.startsWith('/storage/')) return path
  return `/storage/${path}`
}
const publisherName = (article) => article.vendor?.shop_name || article.creator?.vendor?.shop_name || article.creator?.name || 'KomiBook'
const formatDate = (value) => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'long' }).format(new Date(value)) : 'Đang cập nhật'
const applyFilters = () => { page.value = 1; fetchArticles() }
const markImageFailed = (articleId) => {
  imageFailures.value = new Set([...imageFailures.value, articleId])
}

onMounted(() => Promise.all([fetchArticles(), fetchLatestArticles()]))
</script>

<template>
  <main class="min-h-screen bg-background py-10 text-on-surface md:py-16">
    <section class="mx-auto max-w-[1280px] px-4 md:px-gutter" aria-labelledby="blog-title">
      <nav class="mb-8 text-sm text-on-surface-variant" aria-label="Đường dẫn">
        <RouterLink to="/" class="inline-flex min-h-11 items-center font-semibold text-primary no-underline">Trang chủ</RouterLink><span class="mx-2">/</span><span aria-current="page">Tin tức</span>
      </nav>
      <div class="text-center">
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-secondary">KomiBook Editorial</p>
        <h1 id="blog-title" class="mt-3 text-3xl font-bold tracking-tight text-primary md:text-5xl">Bài viết mới</h1>
      </div>

      <form class="mx-auto mt-8 grid max-w-4xl gap-3 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 md:grid-cols-[minmax(0,1fr)_220px_auto]" role="search" @submit.prevent="applyFilters">
        <label class="sr-only" for="news-search">Tìm trong bản tin</label>
        <div class="relative min-w-0"><span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">search</span><input id="news-search" v-model.trim="filters.search" class="ui-field pl-11" type="search" placeholder="Tìm bài viết…" /></div>
        <label class="sr-only" for="news-type">Loại bài</label><select id="news-type" v-model="filters.type" class="ui-field" @change="applyFilters"><option v-for="[value,label] in types" :key="value" :value="value">{{ label }}</option></select>
        <button class="ui-btn ui-btn-primary" type="submit"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">search</span>Tìm kiếm</button>
      </form>

      <div class="mt-10">
        <div v-if="loading" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px]" role="status" aria-label="Đang tải bài viết"><div class="space-y-6"><div v-for="i in 4" :key="i" class="ui-skeleton h-64"></div></div><div class="ui-skeleton h-96"></div></div>
        <div v-else-if="error" class="ui-empty-state" role="alert"><span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">newspaper</span><p class="font-bold">{{ error }}</p><button class="ui-btn ui-btn-secondary" type="button" @click="fetchArticles">Thử lại</button></div>
        <div v-else-if="!articles.length" class="ui-empty-state"><span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">article</span><p class="font-bold">Chưa có bài viết phù hợp.</p></div>
        <template v-else>
          <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div class="min-w-0 space-y-8">
              <article v-for="article in articles" :key="article.id" class="grid min-w-0 gap-5 border-b border-outline-variant/40 pb-8 sm:grid-cols-[240px_minmax(0,1fr)]">
                <RouterLink :to="`/blog/${article.slug}`" class="flex min-h-56 items-center justify-center overflow-hidden no-underline sm:min-h-0">
                  <img v-if="article.cover_image && !imageFailures.has(article.id)" :src="getCoverUrl(article.cover_image)" :alt="article.title" class="h-auto max-h-[300px] w-full object-contain" loading="lazy" @error="markImageFailed(article.id)" />
                  <span v-else class="grid min-h-56 place-items-center text-on-surface-variant"><span class="material-symbols-outlined text-6xl" aria-hidden="true">newspaper</span></span>
                </RouterLink>
                <div class="min-w-0 py-1">
                  <p class="text-xs font-bold uppercase tracking-wider text-secondary">{{ article.category?.name || 'Bản tin' }}</p>
                  <h2 class="mt-2 break-words text-xl font-bold leading-8 text-primary" :style="articleTypographyStyle(article.title_format, { includeSize: false })"><RouterLink :to="`/blog/${article.slug}`" class="text-inherit no-underline hover:underline">{{ article.title }}</RouterLink></h2>
                  <p class="mt-3 text-sm text-on-surface-variant">{{ publisherName(article) }} · {{ formatDate(article.published_at) }}</p>
                  <p v-if="article.excerpt" class="mt-4 line-clamp-4 text-sm leading-6 text-on-surface-variant" :style="articleTypographyStyle(article.excerpt_format, { includeSize: false })">{{ article.excerpt }}</p>
                  <RouterLink :to="`/blog/${article.slug}`" class="mt-4 inline-flex min-h-11 items-center gap-1 font-bold text-commerce no-underline">Xem thêm <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span></RouterLink>
                </div>
              </article>
            </div>
            <aside class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5 lg:sticky lg:top-24" aria-labelledby="latest-news-title">
              <h2 id="latest-news-title" class="border-b border-outline-variant/50 pb-3 text-xl font-bold text-primary">Bài viết mới nhất</h2>
              <div class="divide-y divide-outline-variant/40">
                <RouterLink v-for="item in latestArticles" :key="item.id" :to="`/blog/${item.slug}`" class="grid grid-cols-[68px_minmax(0,1fr)] gap-3 py-4 text-primary no-underline hover:underline">
                  <div class="grid h-16 place-items-center overflow-hidden rounded-lg bg-surface-container-low"><img v-if="item.cover_image && !imageFailures.has(item.id)" :src="getCoverUrl(item.cover_image)" :alt="item.title" class="h-full w-full object-contain" loading="lazy" @error="markImageFailed(item.id)" /><span v-else class="material-symbols-outlined text-outline" aria-hidden="true">article</span></div>
                  <div class="min-w-0"><h3 class="line-clamp-3 text-sm font-bold leading-5">{{ item.title }}</h3><p class="mt-1 text-xs font-normal text-on-surface-variant">{{ formatDate(item.published_at) }}</p></div>
                </RouterLink>
              </div>
            </aside>
          </div>
          <nav v-if="lastPage > 1" class="mt-10 flex justify-center gap-3" aria-label="Phân trang bản tin"><button class="ui-btn ui-btn-secondary" :disabled="page <= 1" @click="page--; fetchArticles()">Trang trước</button><span class="self-center text-sm">Trang {{ page }} / {{ lastPage }}</span><button class="ui-btn ui-btn-secondary" :disabled="page >= lastPage" @click="page++; fetchArticles()">Trang sau</button></nav>
        </template>
        <div class="mt-10 flex justify-center"><RouterLink to="/blog/contribute" class="ui-btn ui-btn-secondary no-underline"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">rate_review</span>Gửi review của bạn</RouterLink></div>
      </div>
    </section>
  </main>
</template>
