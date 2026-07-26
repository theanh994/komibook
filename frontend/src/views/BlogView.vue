<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'

const articles = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const response = await apiClient.get('/api/articles')
    articles.value = response.data.data.data
  } catch {
    error.value = 'Không thể tải bài viết lúc này.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="min-h-screen bg-background px-gutter py-xxl text-on-surface">
    <section class="mx-auto max-w-6xl">
      <p class="text-xs font-bold uppercase tracking-[0.25em] text-primary">KomiBook Editorial</p>
      <h1 class="mt-3 text-4xl font-bold">Tin tức &amp; sự kiện</h1>
      <p class="mt-3 max-w-2xl text-on-surface-variant">Các bài viết đã qua quy trình biên tập và xuất bản của KomiBook.</p>

      <p v-if="loading" class="mt-12" role="status">Đang tải bài viết…</p>
      <p v-else-if="error" class="mt-12 text-error" role="alert">{{ error }}</p>
      <div v-else-if="articles.length" class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <article v-for="article in articles" :key="article.id" class="rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-6 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ article.category?.name || 'KomiBook' }}</p>
          <h2 class="mt-3 text-xl font-bold">{{ article.title }}</h2>
          <p class="mt-3 line-clamp-3 text-sm leading-6 text-on-surface-variant">{{ article.excerpt }}</p>
          <RouterLink :to="`/blog/${article.slug}`" class="mt-6 inline-flex font-semibold text-primary">Đọc bài viết</RouterLink>
        </article>
      </div>
      <div v-else class="mt-12 rounded-3xl border border-outline-variant/20 bg-surface-container-lowest p-10 text-center">
        <p class="font-semibold">Chưa có bài viết đã xuất bản.</p>
        <p class="mt-2 text-sm text-on-surface-variant">Nội dung chỉ xuất hiện sau khi được duyệt và phát hành hợp lệ.</p>
      </div>
    </section>
  </main>
</template>
