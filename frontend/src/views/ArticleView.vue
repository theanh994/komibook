<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import DOMPurify from 'dompurify'
import apiClient from '@/services/axios'
import BackToTopButton from '@/components/BackToTopButton.vue'

const route = useRoute()
const article = ref(null)
const related = ref([])
const loading = ref(true)
const error = ref('')
const commentMessage = ref('')
const commentError = ref('')
const submittingComment = ref(false)
const comment = reactive({ guest_name: '', guest_email: '', body: '' })
const safeBody = computed(() => DOMPurify.sanitize(article.value?.body || ''))
const publisher = computed(() => article.value?.vendor || article.value?.creator?.vendor)
const publisherName = computed(() => publisher.value?.shop_name || article.value?.creator?.name || 'KomiBook')
const getCoverUrl = (path) => !path ? '' : (/^https?:\/\//.test(path) || path.startsWith('/storage/') ? path : `/storage/${path}`)
const formatDate = (value) => value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'long' }).format(new Date(value)) : 'Đang cập nhật'

const fetchArticle = async () => {
  loading.value = true
  error.value = ''
  article.value = null
  try {
    const response = await apiClient.get(`/api/articles/${route.params.slug}`)
    article.value = response.data.data
    related.value = response.data.related || []
    document.title = article.value.seo_title || article.value.title
    document.querySelector('meta[name="description"]')?.setAttribute('content', article.value.seo_description || article.value.excerpt || '')
  } catch {
    error.value = 'Bài viết không tồn tại hoặc chưa được xuất bản.'
  } finally {
    loading.value = false
  }
}

const track = (event) => apiClient.post(`/api/articles/${route.params.slug}/track`, { event }).catch(() => {})
const submitComment = async () => {
  submittingComment.value = true
  commentError.value = ''
  commentMessage.value = ''
  try {
    const response = await apiClient.post(`/api/articles/${route.params.slug}/comments`, comment)
    commentMessage.value = response.data.message
    Object.assign(comment, { guest_name: '', guest_email: '', body: '' })
  } catch (requestError) {
    commentError.value = requestError.response?.data?.message || 'Không thể gửi bình luận.'
  } finally {
    submittingComment.value = false
  }
}
const share = async () => {
  if (navigator.share) await navigator.share({ title: article.value.title, url: window.location.href })
  else await navigator.clipboard.writeText(window.location.href)
}

watch(() => route.params.slug, fetchArticle)
onMounted(fetchArticle)
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10 text-on-surface md:px-gutter md:py-16">
    <div v-if="loading" class="mx-auto max-w-6xl space-y-5" role="status" aria-label="Đang tải bài viết"><div class="ui-skeleton h-5 w-1/4"></div><div class="ui-skeleton h-16"></div><div class="ui-skeleton aspect-video"></div></div>
    <div v-else-if="error" class="ui-empty-state mx-auto max-w-4xl" role="alert"><span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">article_off</span><p class="font-bold">{{ error }}</p><RouterLink to="/blog" class="ui-btn ui-btn-secondary no-underline">Về trang tin tức</RouterLink></div>
    <template v-else>
      <nav class="mx-auto mb-8 max-w-6xl text-sm text-on-surface-variant" aria-label="Đường dẫn"><RouterLink to="/" class="inline-flex min-h-11 items-center font-bold text-primary">Trang chủ</RouterLink><span class="mx-2">/</span><RouterLink to="/blog" class="inline-flex min-h-11 items-center font-bold text-primary">Tin tức</RouterLink><span class="mx-2">/</span><span aria-current="page">{{ article.category?.name || 'Bài viết' }}</span></nav>
      <header class="mx-auto max-w-4xl text-center"><p class="text-sm font-bold uppercase tracking-wider text-secondary">{{ article.category?.name || 'KomiBook Editorial' }}</p><h1 class="mt-4 text-3xl font-bold leading-tight tracking-tight text-primary md:text-5xl">{{ article.title }}</h1><p class="mt-5 text-sm text-on-surface-variant">Bởi {{ publisherName }} · {{ formatDate(article.published_at) }} · {{ article.reading_minutes || 1 }} phút đọc</p><p v-if="article.excerpt" class="mx-auto mt-6 max-w-3xl text-lg font-semibold leading-8 text-on-surface-variant">{{ article.excerpt }}</p><button class="ui-btn ui-btn-secondary mx-auto mt-5" type="button" @click="share"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">share</span>Chia sẻ</button></header>
      <div v-if="article.cover_image" class="mx-auto mt-10 max-w-5xl overflow-hidden bg-surface-container"><img :src="getCoverUrl(article.cover_image)" :alt="article.title" class="aspect-[16/9] h-auto w-full object-cover" /></div>

      <div class="mx-auto mt-12 grid max-w-6xl items-start gap-10 lg:grid-cols-[minmax(0,1fr)_280px]">
        <article>
          <div class="article-body prose max-w-none text-on-surface" v-html="safeBody"></div>
          <section v-if="article.books?.length" class="mt-12 border-t border-outline-variant/40 pt-8" aria-labelledby="article-books-title">
            <h2 id="article-books-title" class="text-2xl font-bold text-primary">Sách được nhắc đến</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              <RouterLink v-for="book in article.books" :key="book.id" :to="`/book/${book.slug}`" class="flex min-h-28 gap-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 text-primary no-underline hover:border-primary" @click="track('book_click')"><img v-if="book.cover_image" :src="getCoverUrl(book.cover_image)" :alt="`Bìa ${book.title}`" class="h-24 w-16 object-contain" /><div><h3 class="font-bold">{{ book.title }}</h3><p class="mt-2 text-sm text-on-surface-variant">{{ book.type === 'ebook' ? 'Ebook' : 'Sách vật lý' }}</p><p class="mt-2 font-bold text-commerce">Xem sách</p></div></RouterLink>
            </div>
          </section>
          <section v-if="article.allow_comments" class="mt-12 border-t border-outline-variant/40 pt-8" aria-labelledby="comments-title">
            <h2 id="comments-title" class="text-2xl font-bold text-primary">Bình luận</h2>
            <div v-if="article.comments?.length" class="mt-6 space-y-4"><article v-for="item in article.comments" :key="item.id" class="rounded-xl bg-surface-container-low p-4"><p class="font-bold">{{ item.user?.name || item.guest_name || 'Độc giả' }}</p><p class="mt-2 whitespace-pre-wrap leading-7">{{ item.body }}</p></article></div>
            <p v-else class="mt-4 text-on-surface-variant">Chưa có bình luận đã được duyệt.</p>
            <form class="mt-8 space-y-4" @submit.prevent="submitComment">
              <p class="font-semibold">Bình luận sẽ được kiểm duyệt trước khi xuất hiện công khai.</p>
              <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold">Tên của bạn<input v-model="comment.guest_name" class="ui-field mt-2" required maxlength="120" /></label><label class="text-sm font-bold">Email (không công khai)<input v-model="comment.guest_email" class="ui-field mt-2" type="email" maxlength="255" /></label></div>
              <label class="block text-sm font-bold">Nội dung bình luận<textarea v-model="comment.body" class="ui-field mt-2 min-h-36" required minlength="3" maxlength="3000"></textarea></label>
              <p v-if="commentMessage" class="ui-alert" role="status">{{ commentMessage }}</p><p v-if="commentError" class="ui-alert ui-alert-error" role="alert">{{ commentError }}</p>
              <button class="ui-btn ui-btn-primary" type="submit" :disabled="submittingComment">{{ submittingComment ? 'Đang gửi…' : 'Gửi bình luận' }}</button>
            </form>
          </section>
        </article>
        <aside class="space-y-6 lg:sticky lg:top-24">
          <section v-if="publisher" class="ui-panel"><p class="text-xs font-bold uppercase tracking-wider text-secondary">Đơn vị đăng</p><h2 class="mt-3 text-xl font-bold text-primary">{{ publisherName }}</h2><RouterLink v-if="publisher.slug" :to="{ name: 'catalog', query: { vendor: publisher.slug } }" class="ui-btn ui-btn-secondary mt-4 w-full no-underline" @click="track('shop_click')">Đến gian hàng</RouterLink></section>
          <section v-if="related.length" class="ui-panel"><h2 class="text-xl font-bold text-primary">Bài viết liên quan</h2><div class="mt-4 divide-y divide-outline-variant/40"><RouterLink v-for="item in related" :key="item.id" :to="`/blog/${item.slug}`" class="block py-4 text-sm font-bold leading-6 text-primary no-underline hover:underline">{{ item.title }}<span class="mt-1 block font-normal text-on-surface-variant">{{ formatDate(item.published_at) }}</span></RouterLink></div></section>
        </aside>
      </div>
    </template>
    <BackToTopButton />
  </main>
</template>

<style scoped>
:deep(.article-body) { max-width: 72ch; font-family: var(--font-literata); font-size: 1.0625rem; line-height: 1.85; overflow-wrap: anywhere; }
:deep(.article-body p) { margin-block: 1.35em; }
:deep(.article-body h2) { margin-top: 2em; font-size: 1.75rem; font-weight: 800; color: var(--color-primary); }
:deep(.article-body img) { display: block; max-width: 100%; height: auto; margin: 2rem auto; }
:deep(.article-body a) { color: var(--color-secondary); font-weight: 700; text-decoration: underline; }
:deep(.article-body blockquote) { border-left: 4px solid var(--color-secondary); margin: 2rem 0; padding: 1rem 1.5rem; background: var(--color-surface-container-low); }
</style>
