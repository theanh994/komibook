<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const articles = ref([])
const loading = ref(true)
const error = ref('')
const page = ref(1)
const lastPage = ref(1)
const filters = reactive({ search: '', type: '' })
const types = [['', 'Tất cả'], ['news', 'Tin tức'], ['review', 'Review sách'], ['book_introduction', 'Giới thiệu sách'], ['event', 'Sự kiện']]
const featured = computed(() => articles.value[0])
const remaining = computed(() => articles.value.slice(1))

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

const getCoverUrl = (path) => {
  if (!path) return ''
  if (/^https?:\/\//.test(path) || path.startsWith('/storage/')) return path
  return `/storage/${path}`
}
const publisherName = (article) => article.vendor?.shop_name || article.creator?.vendor?.shop_name || article.creator?.name || 'KomiBook'
const formatDate = (value) => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'long' }).format(new Date(value)) : 'Đang cập nhật'
const applyFilters = () => { page.value = 1; fetchArticles() }

onMounted(fetchArticles)
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10 text-on-surface md:px-gutter md:py-16">
    <section class="mx-auto max-w-[1280px]" aria-labelledby="blog-title">
      <nav class="mb-8 text-sm text-on-surface-variant" aria-label="Đường dẫn">
        <RouterLink to="/" class="inline-flex min-h-11 items-center font-semibold text-primary no-underline">Trang chủ</RouterLink><span class="mx-2">/</span><span aria-current="page">Tin tức</span>
      </nav>
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-secondary">KomiBook Editorial</p>
        <h1 id="blog-title" class="mt-3 text-3xl font-bold tracking-tight text-primary md:text-5xl">Tin tức &amp; Review sách</h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-on-surface-variant">Bản tin mới từ KomiBook, Nhà bán và các đối tác đã qua quy trình biên tập.</p>
      </div>

      <div class="mt-10 grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0">
          <div v-if="loading" class="grid gap-6 md:grid-cols-2" role="status" aria-label="Đang tải bài viết"><div v-for="i in 6" :key="i" class="ui-skeleton aspect-[4/3]"></div></div>
          <div v-else-if="error" class="ui-empty-state" role="alert"><span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">newspaper</span><p class="font-bold">{{ error }}</p><button class="ui-btn ui-btn-secondary" type="button" @click="fetchArticles">Thử lại</button></div>
          <div v-else-if="!articles.length" class="ui-empty-state"><span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">article</span><p class="font-bold">Chưa có bài viết phù hợp.</p></div>
          <template v-else>
            <article class="grid overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-container-lowest xl:grid-cols-[1.1fr_1fr]">
              <div class="aspect-video bg-primary-container xl:aspect-auto">
                <img v-if="featured.cover_image" :src="getCoverUrl(featured.cover_image)" :alt="featured.title" class="h-full w-full object-cover" />
                <div v-else class="grid h-full min-h-64 place-items-center"><span class="material-symbols-outlined text-6xl text-on-primary-container" aria-hidden="true">article</span></div>
              </div>
              <div class="flex flex-col justify-center p-6 md:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-secondary">{{ featured.category?.name || 'Bản tin nổi bật' }}</p>
                <h2 class="mt-4 text-2xl font-bold leading-tight text-primary">{{ featured.title }}</h2>
                <p class="mt-4 text-sm text-on-surface-variant">{{ publisherName(featured) }} · {{ formatDate(featured.published_at) }} · {{ featured.reading_minutes || 1 }} phút đọc</p>
                <p class="mt-5 line-clamp-3 leading-7 text-on-surface-variant">{{ featured.excerpt }}</p>
                <RouterLink :to="`/blog/${featured.slug}`" class="ui-btn ui-btn-primary mt-6 self-start no-underline">Đọc bài viết</RouterLink>
              </div>
            </article>
            <div class="mt-8 grid gap-6 md:grid-cols-2">
              <article v-for="article in remaining" :key="article.id" class="flex overflow-hidden rounded-xl border border-outline-variant/40 bg-surface-container-lowest shadow-sm">
                <div class="flex w-full flex-col">
                  <div class="aspect-[16/9] bg-primary-container"><img v-if="article.cover_image" :src="getCoverUrl(article.cover_image)" :alt="article.title" class="h-full w-full object-cover" loading="lazy" /></div>
                  <div class="flex flex-grow flex-col p-6"><p class="text-xs font-bold uppercase tracking-wider text-secondary">{{ article.category?.name || 'Bản tin' }}</p><h2 class="mt-3 text-xl font-bold leading-snug text-primary">{{ article.title }}</h2><p class="mt-3 text-sm text-on-surface-variant">{{ publisherName(article) }} · {{ formatDate(article.published_at) }}</p><p class="mt-3 line-clamp-3 text-sm leading-6 text-on-surface-variant">{{ article.excerpt }}</p><RouterLink :to="`/blog/${article.slug}`" class="mt-auto inline-flex min-h-11 items-center pt-5 font-bold text-primary no-underline hover:underline">Đọc bài viết<span class="material-symbols-outlined ml-1 text-[18px]" aria-hidden="true">arrow_forward</span></RouterLink></div>
                </div>
              </article>
            </div>
            <nav v-if="lastPage > 1" class="mt-10 flex justify-center gap-3" aria-label="Phân trang bản tin"><button class="ui-btn ui-btn-secondary" :disabled="page <= 1" @click="page--; fetchArticles()">Trang trước</button><span class="self-center text-sm">Trang {{ page }} / {{ lastPage }}</span><button class="ui-btn ui-btn-secondary" :disabled="page >= lastPage" @click="page++; fetchArticles()">Trang sau</button></nav>
          </template>
        </div>
        <aside class="w-full space-y-3 lg:sticky lg:top-24 lg:justify-self-end">
          <form class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-3" role="search" @submit.prevent="applyFilters">
            <label class="sr-only" for="news-search">Tìm trong bản tin</label>
            <div class="flex min-w-0 items-center gap-2">
              <div class="relative min-w-0 flex-1"><span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">search</span><input id="news-search" v-model.trim="filters.search" class="ui-field pl-11" type="search" placeholder="Tìm bài viết…" /></div>
              <button class="ui-btn ui-btn-primary shrink-0" type="submit" aria-label="Tìm kiếm"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">search</span></button>
            </div>
            <label class="mt-3 block text-sm font-bold">Loại bài<select v-model="filters.type" class="ui-field mt-2" @change="applyFilters"><option v-for="[value,label] in types" :key="value" :value="value">{{ label }}</option></select></label>
          </form>
          <RouterLink to="/blog/contribute" class="ui-btn ui-btn-secondary mt-3 w-full no-underline"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">rate_review</span>Gửi review của bạn</RouterLink>
        </aside>
      </div>
    </section>
  </main>
</template>
