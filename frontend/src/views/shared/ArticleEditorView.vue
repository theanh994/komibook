<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import DOMPurify from 'dompurify'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import apiClient from '@/services/axios'

const props = defineProps({ role: { type: String, required: true } })
const route = useRoute()
const router = useRouter()
const editorElement = ref()
const coverInput = ref()
const loading = ref(false)
const loadingArticle = ref(false)
const message = ref('')
const error = ref('')
const previewOpen = ref(false)
const dirty = ref(false)
const books = ref([])
const series = ref([])
const productSearch = ref('')
const linkTarget = ref('')
const revisions = ref([])
const hasLocalDraft = ref(false)
const articleId = computed(() => route.params.id)
const isEdit = computed(() => Boolean(articleId.value))
const isAdmin = computed(() => props.role === 'admin')
const baseUrl = computed(() => `/api/${props.role}/articles`)
const routeBase = computed(() => `/${props.role}/articles`)
const draftKey = computed(() => `komibook-newsroom:${props.role}:${articleId.value || 'new'}`)
let quill
let autosaveTimer

const form = reactive({
  title: '', excerpt: '', body: '', article_type: 'news', category: '', tags: '',
  book_ids: [], allow_comments: true, home_featured: false, seo_title: '', seo_description: '',
  cover_image: null, cover_url: '', status: 'draft', slug: '',
})

const typeOptions = [
  ['news', 'Tin tức'], ['review', 'Review sách'], ['book_introduction', 'Giới thiệu sách'],
  ['event', 'Sự kiện'], ['vendor_announcement', 'Thông báo nhà bán'],
]
const safePreview = computed(() => DOMPurify.sanitize(form.body))
const slugPreview = computed(() => form.slug || form.title.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''))
const filteredBooks = computed(() => {
  const needle = productSearch.value.trim().toLocaleLowerCase('vi')
  if (!needle) return books.value.slice(0, 20)
  return books.value.filter((book) => `${book.title} ${book.isbn || ''}`.toLocaleLowerCase('vi').includes(needle)).slice(0, 30)
})
const selectedBooks = computed(() => books.value.filter((book) => form.book_ids.includes(book.id)))
const derivedTaxonomy = computed(() => {
  const categories = selectedBooks.value.flatMap((book) => [
    book.category?.name,
    ...(book.categories || []).map((category) => category.name),
  ]).filter(Boolean)
  return [...new Set(categories)]
})
const linkTargets = computed(() => [
  ...selectedBooks.value.map((book) => ({ value: `/book/${book.slug}`, label: `Sách · ${book.title}` })),
  ...series.value
    .filter((item) => item.books?.some((book) => form.book_ids.includes(book.id)))
    .map((item) => ({ value: `/catalog?series=${item.id}`, label: `Bộ sách · ${item.title}` })),
])

const markDirty = () => { dirty.value = true }

const loadBooks = async () => {
  try {
    const response = await apiClient.get(`/api/${props.role}/books`, { params: { per_page: 100 } })
    const payload = response.data.data
    books.value = Array.isArray(payload) ? payload : payload?.data || response.data?.data || []
  } catch {
    books.value = []
  }
}

const loadSeries = async () => {
  try {
    const response = await apiClient.get(isAdmin.value ? '/api/series' : '/api/vendor/series')
    const payload = response.data?.data || []
    if (isAdmin.value) {
      series.value = payload.map((item) => ({
        ...item,
        books: books.value.filter((book) => Number(book.series_id) === Number(item.id)),
      }))
    } else {
      series.value = payload
    }
  } catch {
    series.value = []
  }
}

const toggleBook = (bookId) => {
  const id = Number(bookId)
  form.book_ids = form.book_ids.includes(id)
    ? form.book_ids.filter((value) => value !== id)
    : [...form.book_ids, id]
  markDirty()
}

const selectSeries = (item) => {
  const ids = (item.books || []).map((book) => Number(book.id))
  form.book_ids = [...new Set([...form.book_ids, ...ids])]
  markDirty()
}

const applyProductLink = () => {
  if (!linkTarget.value || !quill) return
  const range = quill.getSelection()
  if (!range || range.length === 0) {
    error.value = 'Hãy bôi đen đoạn chữ như “tại đây” trong nội dung trước khi gắn liên kết.'
    return
  }
  quill.formatText(range.index, range.length, 'link', linkTarget.value, 'user')
  form.body = quill.root.innerHTML
  message.value = 'Đã gắn liên kết sản phẩm vào đoạn chữ được chọn.'
  markDirty()
}

const loadArticle = async () => {
  if (!isEdit.value) {
    const local = localStorage.getItem(draftKey.value)
    hasLocalDraft.value = Boolean(local)
    if (local) Object.assign(form, JSON.parse(local))
    await nextTick()
    quill?.clipboard.dangerouslyPasteHTML(form.body || '')
    return
  }
  loadingArticle.value = true
  try {
    const response = await apiClient.get(`${baseUrl.value}/${articleId.value}`)
    const article = response.data.data
    Object.assign(form, {
      title: article.title || '', excerpt: article.excerpt || '', body: article.body || '',
      article_type: article.article_type || 'news', category: article.category?.name || '',
      tags: (article.tags || []).map((tag) => tag.name).join(', '),
      book_ids: (article.books || []).map((book) => book.id),
      allow_comments: article.allow_comments !== false, home_featured: Boolean(article.home_featured),
      seo_title: article.seo_title || '', seo_description: article.seo_description || '',
      cover_url: article.cover_image ? `/storage/${article.cover_image}` : '',
      status: article.status, slug: article.slug,
    })
    revisions.value = article.revisions || []
    await nextTick()
    quill?.clipboard.dangerouslyPasteHTML(form.body)
    dirty.value = false
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải bài viết.'
  } finally {
    loadingArticle.value = false
  }
}

const createPayload = () => {
  const payload = new FormData()
  const fields = ['title', 'excerpt', 'body', 'article_type', 'category', 'seo_title', 'seo_description']
  fields.forEach((field) => payload.append(field, form[field] || ''))
  form.tags.split(',').map((tag) => tag.trim()).filter(Boolean).forEach((tag, index) => payload.append(`tags[${index}]`, tag))
  form.book_ids.forEach((id, index) => payload.append(`book_ids[${index}]`, id))
  payload.append('allow_comments', form.allow_comments ? '1' : '0')
  if (isAdmin.value) payload.append('home_featured', form.home_featured ? '1' : '0')
  if (form.cover_image) payload.append('cover_image', form.cover_image)
  return payload
}

const save = async () => {
  error.value = ''
  message.value = ''
  if (!form.title.trim() || !form.body.replace(/<[^>]+>/g, '').trim()) {
    error.value = 'Tiêu đề và nội dung bài viết là bắt buộc.'
    return null
  }
  if ((!isAdmin.value || ['review', 'book_introduction'].includes(form.article_type)) && form.book_ids.length === 0) {
    error.value = isAdmin.value
      ? 'Bài review hoặc giới thiệu sách phải chọn ít nhất một sản phẩm liên quan.'
      : 'Nhà bán phải chọn ít nhất một sách hoặc bộ sách liên quan trước khi lưu.'
    return null
  }
  loading.value = true
  try {
    const payload = createPayload()
    let response
    if (isEdit.value) {
      payload.append('_method', 'PATCH')
      response = await apiClient.post(`${baseUrl.value}/${articleId.value}`, payload)
    } else {
      response = await apiClient.post(baseUrl.value, payload)
    }
    localStorage.removeItem(draftKey.value)
    hasLocalDraft.value = false
    dirty.value = false
    message.value = 'Đã lưu bản nháp và tạo phiên bản biên tập.'
    const saved = response.data.data
    if (!isEdit.value) await router.replace(`${routeBase.value}/${saved.id}/edit`)
    else await loadArticle()
    return saved
  } catch (requestError) {
    const validation = requestError.response?.data?.errors
    error.value = validation ? Object.values(validation).flat().join(' ') : (requestError.response?.data?.message || 'Không thể lưu bài viết.')
    return null
  } finally {
    loading.value = false
  }
}

const submitForReview = async () => {
  const saved = await save()
  const id = saved?.id || articleId.value
  if (!id) return
  try {
    await apiClient.post(`${baseUrl.value}/${id}/submit`, { operation_key: `newsroom-submit:${id}:${Date.now()}` })
    message.value = 'Bài viết đã được gửi tới ban biên tập.'
    await loadArticle()
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể gửi duyệt bài viết.'
  }
}

const chooseCover = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  form.cover_image = file
  form.cover_url = URL.createObjectURL(file)
  markDirty()
}

const restoreLocal = () => {
    const local = localStorage.getItem(draftKey.value)
  if (!local) return
  Object.assign(form, JSON.parse(local))
  quill?.clipboard.dangerouslyPasteHTML(form.body || '')
  dirty.value = true
  hasLocalDraft.value = true
}

onBeforeRouteLeave(() => {
  if (!dirty.value) return true
  return window.confirm('Bản nháp có thay đổi chưa lưu. Bạn vẫn muốn rời trang?')
})

onMounted(async () => {
  quill = new Quill(editorElement.value, {
    theme: 'snow',
    placeholder: 'Bắt đầu viết nội dung bài báo...',
    modules: { toolbar: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'blockquote'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']] },
  })
  quill.on('text-change', () => {
    form.body = quill.root.innerHTML
    markDirty()
  })
  await loadBooks()
  await Promise.all([loadSeries(), loadArticle()])
  autosaveTimer = window.setInterval(() => {
    if (!dirty.value) return
    const local = { ...form, cover_image: null }
    localStorage.setItem(draftKey.value, JSON.stringify(local))
    hasLocalDraft.value = true
  }, 15000)
})

onBeforeUnmount(() => window.clearInterval(autosaveTimer))
</script>

<template>
  <section class="ui-page space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-outline-variant/50 pb-5">
      <div>
        <button class="mb-2 inline-flex min-h-11 items-center gap-2 font-bold text-primary" type="button" @click="router.push(routeBase)">
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span> Quay lại Newsroom
        </button>
        <h1 class="text-3xl font-bold text-primary">{{ isEdit ? 'Chỉnh sửa bài viết' : 'Viết bài mới' }}</h1>
        <p class="mt-2 text-on-surface-variant">Bản nháp được lưu cục bộ mỗi 15 giây; lịch sử chỉ tạo khi bạn bấm lưu.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button class="ui-btn ui-btn-secondary" type="button" @click="previewOpen = true">Xem trước</button>
        <button class="ui-btn ui-btn-primary" type="button" :disabled="loading" @click="save">{{ loading ? 'Đang lưu…' : 'Lưu bản nháp' }}</button>
        <button v-if="!isAdmin && ['draft', 'changes_requested'].includes(form.status)" class="ui-btn ui-btn-commerce" type="button" :disabled="loading" @click="submitForReview">Gửi duyệt</button>
      </div>
    </header>

    <p v-if="message" class="ui-alert" role="status">{{ message }}</p>
    <p v-if="error" class="ui-alert ui-alert-error" role="alert">{{ error }}</p>
    <div v-if="loadingArticle" class="ui-skeleton h-96" role="status" aria-label="Đang tải bài viết"></div>

    <div v-else class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0 space-y-5">
        <div class="ui-panel">
          <label class="block font-bold text-on-surface" for="article-title">Tiêu đề bài viết</label>
          <input id="article-title" v-model="form.title" class="mt-2 w-full border-0 bg-transparent p-0 text-3xl font-bold text-primary outline-none" maxlength="255" placeholder="Nhập tiêu đề rõ ràng, hấp dẫn…" @input="markDirty" />
          <p class="mt-3 text-sm text-on-surface-variant">Đường dẫn: /blog/{{ slugPreview || 'duong-dan-bai-viet' }}</p>
        </div>
        <div class="ui-panel p-0">
          <div ref="editorElement" class="min-h-[520px] bg-white"></div>
        </div>
        <div class="ui-panel">
          <label class="block font-bold" for="article-excerpt">Tóm tắt bài viết</label>
          <textarea id="article-excerpt" v-model="form.excerpt" class="ui-field mt-2 min-h-28" maxlength="1000" placeholder="Nội dung ngắn dùng trên danh sách tin và mạng xã hội." @input="markDirty"></textarea>
        </div>
      </div>

      <aside class="min-w-0 space-y-5" aria-label="Thiết lập xuất bản">
        <div class="ui-panel space-y-4">
          <h2 class="text-lg font-bold text-primary">Thiết lập bài viết</h2>
          <label class="block text-sm font-semibold">Loại bài
            <select v-model="form.article_type" class="ui-field mt-2" @change="markDirty"><option v-for="[value, label] in typeOptions" :key="value" :value="value">{{ label }}</option></select>
          </label>
          <div class="rounded-lg bg-surface-container p-3 text-sm">
            <strong class="text-primary">Chuyên mục và thẻ được gợi ý tự động</strong>
            <p class="mt-1 text-on-surface-variant">Hệ thống lấy từ thể loại của sản phẩm đã chọn: {{ derivedTaxonomy.join(', ') || 'chưa có dữ liệu' }}.</p>
          </div>
          <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="form.allow_comments" class="h-5 w-5" type="checkbox" @change="markDirty" /> Cho phép bình luận</label>
          <label v-if="isAdmin" class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="form.home_featured" class="h-5 w-5" type="checkbox" @change="markDirty" /> Nổi bật trên trang chủ</label>
        </div>

        <div class="ui-panel">
          <h2 class="text-lg font-bold text-primary">Ảnh đại diện</h2>
          <button class="mt-3 flex min-h-44 w-full items-center justify-center overflow-hidden rounded-xl border border-dashed border-outline bg-surface-container-low" type="button" @click="coverInput.click()">
            <img v-if="form.cover_url" :src="form.cover_url" alt="" class="h-full w-full object-cover" />
            <span v-else class="text-sm font-bold text-on-surface-variant">Chọn ảnh bìa 16:9</span>
          </button>
          <input ref="coverInput" class="sr-only" type="file" accept="image/*" @change="chooseCover" />
        </div>

        <div class="ui-panel space-y-4">
          <div><h2 class="text-lg font-bold text-primary">Sản phẩm liên quan</h2><p class="mt-1 text-sm text-on-surface-variant">Chọn trước sản phẩm để hệ thống tạo chuyên mục, thẻ và liên kết nội dung phù hợp.</p></div>
          <label class="block text-sm font-semibold">Tìm sách<input v-model="productSearch" class="ui-field mt-2" type="search" placeholder="Nhập tên sách hoặc ISBN…" /></label>
          <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-outline-variant/40 p-2" aria-label="Kết quả tìm sách">
            <label v-for="book in filteredBooks" :key="book.id" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-surface-container">
              <input type="checkbox" class="h-5 w-5" :checked="form.book_ids.includes(book.id)" @change="toggleBook(book.id)" />
              <span class="min-w-0 truncate text-sm font-semibold">{{ book.title }}</span>
            </label>
            <p v-if="!filteredBooks.length" class="p-3 text-sm text-on-surface-variant">Không tìm thấy sách phù hợp.</p>
          </div>
          <div v-if="series.length">
            <p class="text-sm font-bold">Chọn nhanh cả bộ sách</p>
            <div class="mt-2 flex flex-wrap gap-2"><button v-for="item in series" :key="item.id" type="button" class="min-h-11 rounded-lg border border-outline-variant px-3 text-sm font-semibold" @click="selectSeries(item)">{{ item.title }}</button></div>
          </div>
          <div v-if="selectedBooks.length" class="flex flex-wrap gap-2"><span v-for="book in selectedBooks" :key="book.id" class="inline-flex items-center gap-2 rounded-full bg-primary-container px-3 py-2 text-xs font-bold text-on-primary-container">{{ book.title }}<button type="button" class="grid h-7 w-7 place-items-center rounded-full" :aria-label="`Bỏ ${book.title}`" @click="toggleBook(book.id)">×</button></span></div>
          <div class="rounded-xl border border-outline-variant/40 bg-surface-container p-3">
            <p class="text-sm font-bold text-primary">Gắn liên kết vào chữ đang bôi đen</p>
            <p class="mt-1 text-xs text-on-surface-variant">Ví dụ: bôi đen chữ “tại đây” trong trình soạn thảo, chọn sách hoặc bộ sách bên dưới rồi bấm Gắn liên kết.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <select v-model="linkTarget" class="ui-field"><option value="">Chọn đích liên kết</option><option v-for="target in linkTargets" :key="target.value" :value="target.value">{{ target.label }}</option></select>
              <button type="button" class="ui-btn ui-btn-secondary" :disabled="!linkTarget" @click="applyProductLink">Gắn liên kết</button>
            </div>
          </div>
        </div>

        <div class="ui-panel space-y-4">
          <h2 class="text-lg font-bold text-primary">SEO</h2>
          <label class="block text-sm font-semibold">Tiêu đề SEO
            <input v-model="form.seo_title" class="ui-field mt-2" maxlength="255" @input="markDirty" />
          </label>
          <label class="block text-sm font-semibold">Mô tả SEO
            <textarea v-model="form.seo_description" class="ui-field mt-2 min-h-24" maxlength="320" @input="markDirty"></textarea>
          </label>
          <p class="text-xs text-on-surface-variant">{{ form.seo_description.length }} / 320 ký tự</p>
        </div>

        <div v-if="revisions.length" class="ui-panel">
          <h2 class="text-lg font-bold text-primary">Lịch sử phiên bản</h2>
          <ol class="mt-3 space-y-2 text-sm">
            <li v-for="revision in [...revisions].reverse().slice(0, 6)" :key="revision.id">Phiên bản {{ revision.revision }} · {{ new Date(revision.created_at).toLocaleString('vi-VN') }}</li>
          </ol>
        </div>
        <button v-if="hasLocalDraft" class="ui-btn ui-btn-secondary w-full" type="button" @click="restoreLocal">Khôi phục bản tự lưu</button>
      </aside>
    </div>

    <div v-if="previewOpen" class="fixed inset-0 z-[100] overflow-y-auto bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="preview-title" @keydown.esc="previewOpen = false">
      <article class="mx-auto max-w-4xl rounded-2xl bg-white p-6 shadow-xl md:p-10">
        <div class="flex justify-between gap-4"><h2 id="preview-title" class="text-2xl font-bold text-primary">Xem trước bài viết</h2><button class="ui-btn ui-btn-secondary" type="button" @click="previewOpen = false">Đóng</button></div>
        <p class="mt-8 text-sm font-bold uppercase tracking-wider text-secondary">{{ typeOptions.find(([value]) => value === form.article_type)?.[1] }}</p>
        <h1 class="mt-3 text-4xl font-bold text-primary">{{ form.title || 'Tiêu đề bài viết' }}</h1>
        <p class="mt-4 text-lg text-on-surface-variant">{{ form.excerpt }}</p>
        <img v-if="form.cover_url" :src="form.cover_url" alt="" class="mt-8 aspect-video w-full object-cover" />
        <div class="newsroom-prose mt-8" v-html="safePreview"></div>
      </article>
    </div>
  </section>
</template>

<style scoped>
:deep(.ql-toolbar.ql-snow) { display: flex; flex-wrap: wrap; border: 0; border-bottom: 1px solid var(--color-outline-variant); }
:deep(.ql-container.ql-snow) { min-height: 470px; border: 0; font-family: var(--font-literata); font-size: 1rem; line-height: 1.75; }
:deep(.ql-editor) { min-height: 470px; }
.ui-panel,
.ui-field { min-width: 0; max-width: 100%; }
.newsroom-prose { max-width: 72ch; font-family: var(--font-literata); font-size: 1.0625rem; line-height: 1.8; }
</style>
