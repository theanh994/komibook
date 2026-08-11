<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import DOMPurify from 'dompurify'
import apiClient from '@/services/axios'
import { articleTypographyStyle } from '@/utils/articleTypography'

const route = useRoute()
const article = ref(null)
const related = ref([])
const latest = ref([])
const loading = ref(true)
const error = ref('')
const commentMessage = ref('')
const commentError = ref('')
const submittingComment = ref(false)
const comment = reactive({ guest_name: '', guest_email: '', body: '' })
const safeBody = computed(() => DOMPurify.sanitize(article.value?.body || ''))
const publisher = computed(() => article.value?.vendor || article.value?.creator?.vendor)
const publisherName = computed(() => publisher.value?.shop_name || article.value?.creator?.name || 'KomiBook')
const titleStyle = computed(() => articleTypographyStyle(article.value?.title_format))
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
    latest.value = response.data.latest || []
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
const copied = ref(false)
const share = async () => {
  if (navigator.share) {
    try { await navigator.share({ title: article.value?.title, url: window.location.href }) } catch { /* ignore user cancel */ }
  } else {
    try {
      await navigator.clipboard.writeText(window.location.href)
      copied.value = true
      setTimeout(() => { copied.value = false }, 2000)
    } catch { /* ignore */ }
  }
}

watch(() => route.params.slug, fetchArticle)
onMounted(fetchArticle)
</script>

<template>
  <main class="min-h-screen bg-background py-10 text-on-surface md:py-16">
    <div v-if="loading" class="mx-auto max-w-[1280px] px-4 md:px-gutter space-y-5" role="status" aria-label="Đang tải bài viết"><div class="ui-skeleton h-5 w-1/4"></div><div class="ui-skeleton h-16"></div><div class="ui-skeleton aspect-video"></div></div>
    <div v-else-if="error" class="ui-empty-state mx-auto max-w-4xl px-4 md:px-gutter" role="alert"><span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">article_off</span><p class="font-bold">{{ error }}</p><RouterLink to="/blog" class="ui-btn ui-btn-secondary no-underline">Về trang tin tức</RouterLink></div>
    <template v-else>
      <nav class="mx-auto mb-8 max-w-[1280px] px-4 md:px-gutter text-sm text-on-surface-variant" aria-label="Đường dẫn"><RouterLink to="/" class="inline-flex min-h-11 items-center font-bold text-primary">Trang chủ</RouterLink><span class="mx-2">/</span><RouterLink to="/blog" class="inline-flex min-h-11 items-center font-bold text-primary">Tin tức</RouterLink><span class="mx-2">/</span><span aria-current="page">{{ article.category?.name || 'Bài viết' }}</span></nav>
      <div class="mx-auto grid max-w-[1280px] px-4 md:px-gutter items-start gap-0 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="min-w-0 lg:pr-10">
          <header>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">{{ article.category?.name || 'Tin tức sách & văn học' }}</p>
            <h1 class="mt-3 break-words text-3xl font-bold leading-tight tracking-tight text-primary md:text-5xl" :style="titleStyle">{{ article.title }}</h1>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-y border-outline-variant/30 py-3.5 text-sm">
              <div class="flex flex-wrap items-center gap-3 md:gap-5 text-on-surface-variant">
                <RouterLink v-if="publisher?.slug" :to="{ name: 'vendor-storefront', params: { slug: publisher.slug } }" class="inline-flex items-center gap-1.5 rounded-full border border-primary/25 bg-primary/5 px-3 py-1 text-sm font-bold text-primary transition-all hover:bg-primary/10 hover:border-primary no-underline">
                  <span class="material-symbols-outlined text-[17px] text-primary" aria-hidden="true">storefront</span>
                  <span>{{ publisherName }}</span>
                </RouterLink>
                <div v-else class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-low px-3 py-1 text-sm font-bold text-primary">
                  <span class="material-symbols-outlined text-[17px] text-primary" aria-hidden="true">edit_note</span>
                  <span>{{ publisherName }}</span>
                </div>

                <div class="inline-flex items-center gap-1.5 font-medium">
                  <span class="material-symbols-outlined text-[17px] text-on-surface-variant/70" aria-hidden="true">calendar_today</span>
                  <span>{{ formatDate(article.published_at) }}</span>
                </div>

                <div class="inline-flex items-center gap-1.5 font-medium">
                  <span class="material-symbols-outlined text-[17px] text-on-surface-variant/70" aria-hidden="true">schedule</span>
                  <span>{{ article.reading_minutes || 1 }} phút đọc</span>
                </div>
              </div>

              <button class="inline-flex min-h-9 items-center gap-2 rounded-full border border-outline-variant/60 bg-surface-container-lowest px-4 py-1.5 text-sm font-bold text-primary shadow-sm transition-all duration-200 hover:border-primary hover:bg-surface-container-low active:scale-95" type="button" @click="share">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ copied ? 'check' : 'share' }}</span>
                <span>{{ copied ? 'Đã sao chép!' : 'Chia sẻ' }}</span>
              </button>
            </div>
          </header>
          <div v-if="article.cover_image" class="my-6 overflow-hidden md:my-8 text-center max-w-[70%] mx-auto rounded-xl shadow-md">
            <img :src="getCoverUrl(article.cover_image)" :alt="article.title" class="block h-auto w-full max-w-full object-contain" />
          </div>

          <article class="min-w-0">
          <div class="article-body prose max-w-none text-on-surface" v-html="safeBody"></div>
          <div v-if="article.books?.length || publisher" class="mt-12 grid items-stretch gap-5 border-t border-outline-variant/40 pt-8 md:grid-cols-[minmax(0,2fr)_minmax(240px,1fr)]">
            <section v-if="article.books?.length" aria-labelledby="article-books-title">
              <h2 id="article-books-title" class="text-2xl font-bold text-primary">Sách được nhắc đến</h2>
              <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <RouterLink v-for="book in article.books" :key="book.id" :to="`/book/${book.slug}`" class="flex min-h-28 gap-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 text-primary no-underline hover:border-primary" @click="track('book_click')"><img v-if="book.cover_image" :src="getCoverUrl(book.cover_image)" :alt="`Bìa ${book.title}`" class="h-24 w-16 shrink-0 object-contain" /><div class="flex min-w-0 flex-col justify-between"><div><h3 class="break-words font-bold">{{ book.title }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ book.type === 'ebook' ? 'Ebook' : 'Sách vật lý' }}</p></div><p class="mt-2 font-bold text-commerce">Xem sách</p></div></RouterLink>
              </div>
            </section>
            <section v-if="publisher" aria-labelledby="article-publisher-title">
              <h2 id="article-publisher-title" class="text-2xl font-bold text-primary">Đơn vị đăng</h2>
              <div class="mt-5">
                <RouterLink v-if="publisher.slug" :to="{ name: 'vendor-storefront', params: { slug: publisher.slug } }" class="flex min-h-28 gap-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 text-primary no-underline hover:border-primary" @click="track('shop_click')">
                  <img v-if="publisher.logo || publisher.shop_logo" :src="getCoverUrl(publisher.logo || publisher.shop_logo)" :alt="`Logo ${publisherName}`" class="h-24 w-16 shrink-0 object-contain" />
                  <div v-else class="grid h-24 w-16 shrink-0 place-items-center rounded-lg bg-surface-container-low text-primary">
                    <span class="material-symbols-outlined text-3xl" aria-hidden="true">storefront</span>
                  </div>
                  <div class="flex min-w-0 flex-col justify-between">
                    <div>
                      <h3 class="break-words font-bold">{{ publisherName }}</h3>
                      <p class="mt-1 text-sm text-on-surface-variant">Đơn vị phát hành</p>
                    </div>
                    <p class="mt-2 font-bold text-commerce">Xem gian hàng</p>
                  </div>
                </RouterLink>
                <div v-else class="flex min-h-28 gap-4 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-4 text-primary">
                  <div class="grid h-24 w-16 shrink-0 place-items-center rounded-lg bg-surface-container-low text-primary">
                    <span class="material-symbols-outlined text-3xl" aria-hidden="true">storefront</span>
                  </div>
                  <div class="flex min-w-0 flex-col justify-between">
                    <div>
                      <h3 class="break-words font-bold">{{ publisherName }}</h3>
                      <p class="mt-1 text-sm text-on-surface-variant">Đơn vị phát hành</p>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          </div>
          <section v-if="article.allow_comments" class="mt-12 rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-6 md:p-8" aria-labelledby="comments-title">
            <h2 id="comments-title" class="border-b border-outline-variant/30 pb-4 text-2xl font-bold text-primary">Bình luận</h2>
            <div v-if="article.comments?.length" class="mt-6 space-y-4">
              <article v-for="item in article.comments" :key="item.id" class="rounded-xl border border-outline-variant/30 bg-surface-container-low p-4">
                <p class="font-bold text-primary">{{ item.user?.name || item.guest_name || 'Độc giả' }}</p>
                <p class="mt-2 whitespace-pre-wrap leading-relaxed text-on-surface-variant">{{ item.body }}</p>
              </article>
            </div>
            <p v-else class="mt-4 text-sm font-medium text-on-surface-variant">Chưa có bình luận đã được duyệt.</p>
            <form class="mt-8 space-y-5 border-t border-outline-variant/20 pt-6" @submit.prevent="submitComment">
              <p class="text-sm font-semibold text-on-surface-variant">Bình luận sẽ được kiểm duyệt trước khi xuất hiện công khai.</p>
              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-bold text-on-surface">
                  Tên của bạn
                  <input v-model="comment.guest_name" class="ui-field mt-2 w-full rounded-xl border border-outline-variant/50 bg-background px-4 py-2.5 text-sm transition-colors focus:border-primary focus:bg-white focus:outline-none" required maxlength="120" placeholder="Nhập tên của bạn" />
                </label>
                <label class="block text-sm font-bold text-on-surface">
                  Email (không công khai)
                  <input v-model="comment.guest_email" class="ui-field mt-2 w-full rounded-xl border border-outline-variant/50 bg-background px-4 py-2.5 text-sm transition-colors focus:border-primary focus:bg-white focus:outline-none" type="email" maxlength="255" placeholder="nhapemail@example.com" />
                </label>
              </div>
              <label class="block text-sm font-bold text-on-surface">
                Nội dung bình luận
                <textarea v-model="comment.body" class="ui-field mt-2 w-full rounded-xl border border-outline-variant/50 bg-background p-4 text-sm leading-relaxed transition-colors min-h-32 focus:border-primary focus:bg-white focus:outline-none" required minlength="3" maxlength="3000" placeholder="Viết cảm nghĩ của bạn về bài viết..."></textarea>
              </label>
              <p v-if="commentMessage" class="ui-alert" role="status">{{ commentMessage }}</p>
              <p v-if="commentError" class="ui-alert ui-alert-error" role="alert">{{ commentError }}</p>
              <button class="ui-btn ui-btn-primary min-h-11 rounded-2xl px-6 py-2.5 text-sm font-bold text-on-primary transition-all hover:bg-primary-container hover:text-on-primary-container disabled:opacity-60 cursor-pointer" type="submit" :disabled="submittingComment">
                {{ submittingComment ? 'Đang gửi…' : 'Gửi bình luận' }}
              </button>
            </form>
          </section>
          <section v-if="related.length" class="mt-14 border-t border-outline-variant/40 pt-8" aria-labelledby="related-articles-title"><h2 id="related-articles-title" class="text-2xl font-bold text-primary">Bài viết liên quan</h2><div class="mt-6 grid gap-4 md:grid-cols-2"><RouterLink v-for="item in related" :key="item.id" :to="`/blog/${item.slug}`" class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5 text-base font-bold leading-7 text-primary no-underline hover:border-primary">{{ item.title }}<span class="mt-2 block text-sm font-normal text-on-surface-variant">{{ formatDate(item.published_at) }}</span></RouterLink></div></section>
          </article>
        </div>
        <aside v-if="latest.length" class="rounded-xl border border-outline-variant/40 bg-surface-container-lowest p-5 lg:sticky lg:top-24" aria-labelledby="latest-article-title">
          <h2 id="latest-article-title" class="border-b border-outline-variant/50 pb-3 text-xl font-bold text-primary">Bài viết mới nhất</h2>
          <div class="divide-y divide-outline-variant/40">
            <RouterLink v-for="item in latest" :key="item.id" :to="`/blog/${item.slug}`" class="grid grid-cols-[68px_minmax(0,1fr)] gap-3 py-4 text-primary no-underline hover:underline">
              <div class="grid h-16 place-items-center overflow-hidden rounded-lg bg-surface-container-low"><img v-if="item.cover_image" :src="getCoverUrl(item.cover_image)" :alt="item.title" class="h-full w-full object-contain" loading="lazy" /><span v-else class="material-symbols-outlined text-outline" aria-hidden="true">article</span></div>
              <div class="min-w-0"><h3 class="line-clamp-3 text-sm font-bold leading-5">{{ item.title }}</h3><p class="mt-1 text-xs font-normal text-on-surface-variant">{{ formatDate(item.published_at) }}</p></div>
            </RouterLink>
          </div>
        </aside>
      </div>
    </template>
  </main>
</template>

<style scoped>
:deep(.article-body) { max-width: 100%; font-family: var(--font-literata); font-size: 1.0625rem; line-height: 1.85; overflow-wrap: anywhere; }
:deep(.article-body p) { margin-block: 1.35em; }
:deep(.article-body h2) { margin-top: 2em; font-size: 1.75rem; font-weight: 800; color: var(--color-primary); }
:deep(.article-body h3) { margin-top: 1.75em; font-size: 1.35rem; font-weight: 800; color: var(--color-primary); }
:deep(.article-body img) { display: block; max-width: 70% !important; height: auto; margin: 1.75rem auto; border-radius: 0.75rem; }
:deep(.article-body a) { color: var(--color-secondary); font-weight: 700; text-decoration: underline; }
:deep(.article-body blockquote) { border-left: 4px solid var(--color-secondary); margin: 2rem 0; padding: 1rem 1.5rem; background: var(--color-surface-container-low); }
:deep(.article-body > :first-child) { margin-top: 0; }
:deep(.article-body .ql-font-inter) { font-family: Inter, sans-serif; }
:deep(.article-body .ql-font-literata) { font-family: Literata, Georgia, serif; }
:deep(.article-body .ql-font-times-new-roman) { font-family: "Times New Roman", Times, serif; }
:deep(.article-body .ql-font-arial) { font-family: Arial, sans-serif; }
:deep(.article-body .ql-font-georgia) { font-family: Georgia, serif; }
:deep(.article-body .ql-font-monospace) { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
:deep(.article-body .ql-size-12px) { font-size: 12px; }
:deep(.article-body .ql-size-14px) { font-size: 14px; }
:deep(.article-body .ql-size-16px) { font-size: 16px; }
:deep(.article-body .ql-size-18px) { font-size: 18px; }
:deep(.article-body .ql-size-24px) { font-size: 24px; }
:deep(.article-body .ql-size-32px) { font-size: 32px; }
:deep(.article-body .ql-align-center) { text-align: center; }
:deep(.article-body .ql-align-right) { text-align: right; }
:deep(.article-body .ql-align-justify) { text-align: justify; }
:deep(.article-body .ql-indent-1) { padding-left: 3em; }
:deep(.article-body .ql-indent-2) { padding-left: 6em; }
:deep(.article-body .ql-indent-3) { padding-left: 9em; }
</style>
