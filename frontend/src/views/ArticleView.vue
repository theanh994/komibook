<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import DOMPurify from 'dompurify'
import apiClient from '@/services/axios'

const route = useRoute()
const article = ref(null)
const loading = ref(true)
const error = ref('')
const safeBody = computed(() => DOMPurify.sanitize(article.value?.body || ''))

onMounted(async () => {
  try {
    const response = await apiClient.get(`/api/articles/${route.params.slug}`)
    article.value = response.data.data
    document.title = article.value.seo_title || article.value.title
  } catch {
    error.value = 'Bài viết không tồn tại hoặc chưa được xuất bản.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="min-h-screen bg-background px-gutter py-xxl text-on-surface">
    <article class="mx-auto max-w-3xl">
      <p v-if="loading" role="status">Đang tải bài viết…</p>
      <div v-else-if="error" class="rounded-3xl bg-surface-container-lowest p-10 text-center" role="alert">
        <p>{{ error }}</p>
        <RouterLink to="/blog" class="mt-6 inline-flex font-semibold text-primary">Về trang tin tức</RouterLink>
      </div>
      <template v-else>
        <p class="text-xs font-bold uppercase tracking-wider text-primary">{{ article.category?.name || 'KomiBook Editorial' }}</p>
        <h1 class="mt-4 text-4xl font-bold leading-tight">{{ article.title }}</h1>
        <p v-if="article.excerpt" class="mt-5 text-lg text-on-surface-variant">{{ article.excerpt }}</p>
        <div class="prose mt-10 max-w-none" v-html="safeBody"></div>
        <div v-if="article.books?.length" class="mt-12 border-t border-outline-variant/20 pt-6">
          <p class="font-semibold">Sách liên quan</p>
          <RouterLink v-for="book in article.books" :key="book.id" :to="`/book/${book.slug}`" class="mr-4 mt-3 inline-flex text-primary">{{ book.title }}</RouterLink>
        </div>
      </template>
    </article>
  </main>
</template>
