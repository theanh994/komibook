<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import DOMPurify from 'dompurify'
import apiClient from '@/services/axios'

const route = useRoute()
const article = ref(null)
const loading = ref(true)
const error = ref('')
const safeBody = computed(() => DOMPurify.sanitize(article.value?.body || ''))
const publisherName = computed(() => (
  article.value?.creator?.vendor?.shop_name
  || article.value?.creator?.name
  || 'KomiBook'
))

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

const formatDate = (value) => value
  ? new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(value))
  : 'Ngày xuất bản đang cập nhật'

const fetchArticle = async () => {
  loading.value = true
  error.value = ''
  article.value = null
  try {
    const response = await apiClient.get(`/api/articles/${route.params.slug}`)
    article.value = response.data.data
    document.title = article.value.seo_title || article.value.title
  } catch {
    error.value = 'Bài viết không tồn tại hoặc chưa được xuất bản.'
  } finally {
    loading.value = false
  }
}

watch(() => route.params.slug, fetchArticle)
onMounted(fetchArticle)
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10 text-on-surface md:px-gutter md:py-16">
    <article class="mx-auto max-w-4xl">
      <nav class="mb-8 text-sm text-on-surface-variant" aria-label="Đường dẫn">
        <RouterLink to="/blog" class="inline-flex min-h-11 items-center gap-1 font-bold text-primary no-underline">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
          Tin tức
        </RouterLink>
      </nav>

      <div v-if="loading" class="space-y-5" role="status" aria-label="Đang tải bài viết">
        <div class="h-5 w-1/4 animate-pulse rounded bg-surface-container-high"></div>
        <div class="h-12 animate-pulse rounded bg-surface-container-high"></div>
        <div class="aspect-[16/9] animate-pulse rounded-2xl bg-surface-container-high"></div>
      </div>

      <div v-else-if="error" class="ui-state-panel" role="alert">
        <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">article_off</span>
        <p class="mt-3 font-bold">{{ error }}</p>
        <RouterLink to="/blog" class="ui-button ui-button-secondary mt-6 no-underline">Về trang tin tức</RouterLink>
      </div>

      <template v-else>
        <header>
          <p class="text-sm font-bold uppercase tracking-wider text-secondary">{{ article.category?.name || 'KomiBook Editorial' }}</p>
          <h1 class="mt-4 text-3xl font-bold leading-tight tracking-tight text-primary md:text-5xl">{{ article.title }}</h1>
          <p class="mt-5 text-sm text-on-surface-variant">Bởi {{ publisherName }} · {{ formatDate(article.published_at) }}</p>
          <p v-if="article.excerpt" class="mt-5 text-lg leading-8 text-on-surface-variant">{{ article.excerpt }}</p>
        </header>

        <div v-if="article.cover_image" class="mt-8 overflow-hidden rounded-2xl bg-surface-container">
          <img :src="getCoverUrl(article.cover_image)" :alt="article.title" class="aspect-[16/9] h-auto w-full object-cover" />
        </div>

        <div class="article-body prose mt-10 max-w-none text-on-surface" v-html="safeBody"></div>

        <section v-if="article.books?.length" class="mt-12 border-t border-outline-variant/30 pt-8" aria-labelledby="article-books-title">
          <h2 id="article-books-title" class="text-xl font-bold text-primary">Sách liên quan</h2>
          <div class="mt-4 flex flex-wrap gap-3">
            <RouterLink v-for="book in article.books" :key="book.id" :to="`/book/${book.slug}`" class="inline-flex min-h-11 items-center rounded-lg border border-outline-variant/40 px-4 font-semibold text-primary no-underline hover:border-primary">
              {{ book.title }}
            </RouterLink>
          </div>
        </section>
      </template>
    </article>
  </main>
</template>

<style scoped>
:deep(.article-body) {
  font-size: 1rem;
  line-height: 1.8;
  overflow-wrap: anywhere;
}

:deep(.article-body a) {
  color: var(--color-primary);
  text-decoration: underline;
}
</style>
