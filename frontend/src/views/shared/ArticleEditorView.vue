<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import DOMPurify from 'dompurify'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import apiClient from '@/services/axios'

const Font = Quill.import('formats/font')
Font.whitelist = ['inter', 'literata', 'times-new-roman', 'arial', 'georgia', 'monospace']
Quill.register(Font, true)

const Size = Quill.import('attributors/class/size')
Size.whitelist = ['12px', '14px', '16px', '18px', '24px', '32px']
Quill.register(Size, true)

const props = defineProps({ role: { type: String, required: true } })
const route = useRoute()
const router = useRouter()
const editorElement = ref()
const editorPanelRef = ref()
const coverInput = ref()
const inlineImageInput = ref()
const loading = ref(false)
const loadingArticle = ref(false)
const message = ref('')
const error = ref('')
const previewOpen = ref(false)
const openPreview = () => {
  if (quill) {
    form.body = quill.root.innerHTML
  }
  previewOpen.value = true
}
const dirty = ref(false)
const books = ref([])
const series = ref([])
const productSearch = ref('')
const linkTarget = ref('')
const revisions = ref([])
const hasLocalDraft = ref(false)
const coverNaturalSize = ref('')
const pendingInlineImage = ref(null)
const inlineImageAlt = ref('')
const uploadingInlineImage = ref(false)
const articleId = computed(() => route.params.id)
const isEdit = computed(() => Boolean(articleId.value))
const isAdmin = computed(() => props.role === 'admin')
const baseUrl = computed(() => `/api/${props.role}/articles`)
const routeBase = computed(() => `/${props.role}/articles`)
const draftKey = computed(() => `komibook-newsroom:${props.role}:${articleId.value || 'new'}`)
let quill
let autosaveTimer
let inlineImageRange = null

// Floating Selection Toolbar State (Trình chỉnh sửa khi bôi đen)
const selectionToolbar = reactive({
  visible: false,
  top: 0,
  left: 0,
  formats: {},
})

const defaultTitleFormat = { font: 'inter', size: '40', align: 'left', weight: 'bold', style: 'normal' }
const defaultExcerptFormat = { font: 'inter', size: '16', align: 'left', weight: 'normal', style: 'normal' }
const fontOptions = [['inter', 'Inter'], ['literata', 'Literata'], ['times-new-roman', 'Times New Roman'], ['arial', 'Arial'], ['georgia', 'Georgia'], ['monospace', 'Monospace']]
const fontFamilies = {
  inter: 'Inter, sans-serif', literata: 'Literata, Georgia, serif',
  'times-new-roman': '"Times New Roman", Times, serif', arial: 'Arial, sans-serif',
  georgia: 'Georgia, serif', monospace: 'ui-monospace, SFMono-Regular, Consolas, monospace',
}

const form = reactive({
  title: '', excerpt: '', body: '', article_type: 'news', category: '', tags: '',
  title_format: { ...defaultTitleFormat }, excerpt_format: { ...defaultExcerptFormat },
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
const getMediaUrl = (path) => !path ? '' : (/^https?:\/\//.test(path) || path.startsWith('/storage/') ? path : `/storage/${path}`)
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

const typographyStyle = (format) => ({
  fontFamily: fontFamilies[format.font] || fontFamilies.inter,
  fontSize: `${format.size || 16}px`,
  textAlign: format.align || 'left',
  fontWeight: format.weight || 'normal',
  fontStyle: format.style || 'normal',
})

const toggleFormat = (format, key, activeValue) => {
  format[key] = format[key] === activeValue ? 'normal' : activeValue
  markDirty()
}

const markDirty = () => { dirty.value = true }

const resizeTextareaElement = (el) => {
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.max(el.scrollHeight, 60)}px`
}

const autoResizeTextarea = (event) => {
  resizeTextareaElement(event.target)
  markDirty()
}

const resizeAllTextareas = () => {
  nextTick(() => {
    requestAnimationFrame(() => {
      ['article-title', 'article-excerpt'].forEach((id) => {
        const el = document.getElementById(id)
        if (el) resizeTextareaElement(el)
      })
    })
  })
}

watch(
  [
    () => form.title,
    () => form.excerpt,
    () => form.title_format.size,
    () => form.title_format.font,
    () => form.excerpt_format.size,
    () => form.excerpt_format.font,
  ],
  () => {
    resizeAllTextareas()
  },
  { immediate: true, deep: true }
)

// Floating Selection Toolbar Controls (Khi bôi đen chữ)
const updateSelectionToolbar = () => {
  if (!quill || !editorPanelRef.value) return
  const range = quill.getSelection()
  if (!range || range.length === 0) {
    selectionToolbar.visible = false
    return
  }

  // Get active formats at selection
  selectionToolbar.formats = quill.getFormat(range)

  // Bounds relative to Quill container
  const bounds = quill.getBounds(range.index, range.length)
  const panelRect = editorPanelRef.value.getBoundingClientRect()

  // Height of fixed main Quill toolbar at the top is approx 44px
  const mainToolbarHeight = 48

  // Calculate top (~52px above selection top)
  let computedTop = bounds.top - 52

  // Prevent overlap with main fixed toolbar: if near top or if computedTop < mainToolbarHeight, place BELOW selection
  if (bounds.top < 65 || computedTop < mainToolbarHeight) {
    computedTop = bounds.bottom + 8
  }

  // Double check that computedTop never overlaps the main toolbar
  if (computedTop < mainToolbarHeight) {
    computedTop = mainToolbarHeight + 4
  }

  // Center horizontally over selection
  let computedLeft = bounds.left + (bounds.width / 2) - 170
  if (computedLeft < 10) computedLeft = 10
  if (computedLeft > panelRect.width - 360) {
    computedLeft = Math.max(10, panelRect.width - 360)
  }

  selectionToolbar.top = computedTop
  selectionToolbar.left = computedLeft
  selectionToolbar.visible = true
}

const formatSelection = (format, value) => {
  if (!quill) return
  const range = quill.getSelection()
  if (range && range.length > 0) {
    quill.formatText(range.index, range.length, format, value, 'user')
  } else {
    quill.format(format, value, 'user')
  }
  form.body = quill.root.innerHTML
  markDirty()
  nextTick(updateSelectionToolbar)
}

const cleanSelection = () => {
  if (!quill) return
  const range = quill.getSelection()
  if (range && range.length > 0) {
    quill.removeFormat(range.index, range.length, 'user')
  }
  form.body = quill.root.innerHTML
  markDirty()
  nextTick(updateSelectionToolbar)
}

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
      title_format: { ...defaultTitleFormat, ...article.title_format },
      excerpt_format: { ...defaultExcerptFormat, ...article.excerpt_format },
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
  Object.entries(form.title_format).forEach(([key, value]) => payload.append(`title_format[${key}]`, value))
  Object.entries(form.excerpt_format).forEach(([key, value]) => payload.append(`excerpt_format[${key}]`, value))
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
    message.value = form.status === 'published'
      ? 'Đã lưu bản hiệu chỉnh. Bài viết vẫn giữ trạng thái đã xuất bản.'
      : 'Đã lưu bản nháp và tạo phiên bản biên tập.'
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

const rememberCoverSize = (event) => {
  const image = event.currentTarget
  coverNaturalSize.value = image?.naturalWidth && image?.naturalHeight
    ? `${image.naturalWidth} × ${image.naturalHeight} px`
    : ''
}

const chooseInlineImage = async () => {
  error.value = ''
  inlineImageRange = quill?.getSelection(true) || { index: quill?.getLength() || 0, length: 0 }

  if (!articleId.value) {
    if (!form.title.trim()) {
      error.value = 'Vui lòng nhập tiêu đề bài viết trước khi chèn ảnh vào nội dung.'
      return
    }
    const saved = await save()
    if (!saved || !articleId.value) {
      if (!error.value) {
        error.value = 'Chưa thể tạo bản nháp bài viết để tải ảnh. Vui lòng kiểm tra lại thông tin.'
      }
      return
    }
  }

  inlineImageInput.value?.click()
}

const prepareInlineImage = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  pendingInlineImage.value = file
  inlineImageAlt.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ')
}

const cancelInlineImage = () => {
  pendingInlineImage.value = null
  inlineImageAlt.value = ''
  if (inlineImageInput.value) inlineImageInput.value.value = ''
}

const uploadInlineImage = async () => {
  if (!pendingInlineImage.value || !inlineImageAlt.value.trim() || !articleId.value || !quill) return
  uploadingInlineImage.value = true
  error.value = ''
  try {
    const payload = new FormData()
    payload.append('image', pendingInlineImage.value)
    payload.append('alt_text', inlineImageAlt.value.trim())
    const response = await apiClient.post(`${baseUrl.value}/${articleId.value}/media`, payload)
    const range = inlineImageRange || { index: quill.getLength(), length: 0 }
    const imageUrl = response.data.data.url
    const altText = inlineImageAlt.value.trim()

    // 1. Insert image embed
    quill.insertEmbed(range.index, 'image', imageUrl, 'user')
    // 2. Automatically center the image line
    quill.formatLine(range.index, 1, 'align', 'center', 'user')

    // 3. Insert description text right below the image, centered and formatted (NO "Chú thích ảnh:" prefix)
    if (altText) {
      quill.insertText(range.index + 1, `\n${altText}\n`, 'user')
      quill.formatLine(range.index + 1, 1, 'align', 'center', 'user')
      quill.formatLine(range.index + 1, 1, 'italic', true, 'user')
    }

    quill.setSelection(range.index + (altText ? altText.length + 3 : 2), 0, 'silent')
    form.body = quill.root.innerHTML
    markDirty()
    cancelInlineImage()
    message.value = 'Đã chèn ảnh tự động căn giữa (70% khung) kèm mô tả vào bài viết.'
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải ảnh nội dung.'
  } finally {
    uploadingInlineImage.value = false
  }
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
    modules: {
      toolbar: {
        container: [
          [{ font: Font.whitelist }, { size: Size.whitelist }],
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ script: 'sub' }, { script: 'super' }],
          [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
          [{ align: '' }, { align: 'center' }, { align: 'right' }, { align: 'justify' }],
          ['blockquote', 'link', 'image'],
          ['clean'],
        ],
        handlers: { image: chooseInlineImage }
      }
    },
  })

  quill.on('text-change', () => {
    form.body = quill.root.innerHTML
    markDirty()
    const range = quill.getSelection()
    if (range && range.length > 0) {
      updateSelectionToolbar()
    }
  })

  quill.on('selection-change', (range) => {
    if (range && range.length > 0) {
      updateSelectionToolbar()
    } else {
      selectionToolbar.visible = false
    }
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
        <button class="ui-btn ui-btn-secondary" type="button" @click="openPreview">Xem trước</button>
        <button class="ui-btn ui-btn-primary" type="button" :disabled="loading" @click="save">{{ loading ? 'Đang lưu…' : (form.status === 'published' ? 'Lưu bản hiệu chỉnh' : 'Lưu bản nháp') }}</button>
        <button v-if="!isAdmin && ['draft', 'changes_requested'].includes(form.status)" class="ui-btn ui-btn-commerce" type="button" :disabled="loading" @click="submitForReview">Gửi duyệt</button>
      </div>
    </header>

    <p v-if="message" class="ui-alert" role="status">{{ message }}</p>
    <p v-if="error" class="ui-alert ui-alert-error" role="alert">{{ error }}</p>
    <div v-if="loadingArticle" class="ui-skeleton h-96" role="status" aria-label="Đang tải bài viết"></div>

    <div v-show="!loadingArticle" class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <div class="min-w-0 space-y-5">
        <!-- Title Panel -->
        <div class="ui-panel">
          <label class="block font-bold text-on-surface" for="article-title">Tiêu đề bài viết</label>
          <div class="typography-toolbar mt-3" role="toolbar" aria-label="Định dạng tiêu đề">
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <span class="material-symbols-outlined text-[18px] text-on-surface-variant pl-1" aria-hidden="true">font_download</span>
              <label class="sr-only" for="title-font">Phông chữ tiêu đề</label>
              <select id="title-font" v-model="form.title_format.font" class="typography-select" @change="markDirty">
                <option v-for="[value, label] in fontOptions" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <span class="material-symbols-outlined text-[18px] text-on-surface-variant pl-1" aria-hidden="true">format_size</span>
              <label class="sr-only" for="title-size">Cỡ chữ tiêu đề</label>
              <select id="title-size" v-model="form.title_format.size" class="typography-select typography-size" @change="markDirty">
                <option v-for="size in ['28', '32', '40', '48']" :key="size" :value="size">{{ size }} px</option>
              </select>
            </div>
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.weight === 'bold' }" :aria-pressed="form.title_format.weight === 'bold'" aria-label="In đậm tiêu đề" @click="toggleFormat(form.title_format, 'weight', 'bold')">
                <span class="material-symbols-outlined text-[18px]">format_bold</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.style === 'italic' }" :aria-pressed="form.title_format.style === 'italic'" aria-label="In nghiêng tiêu đề" @click="toggleFormat(form.title_format, 'style', 'italic')">
                <span class="material-symbols-outlined text-[18px]">format_italic</span>
              </button>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.align === 'left' }" aria-label="Căn trái tiêu đề" @click="form.title_format.align = 'left'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.align === 'center' }" aria-label="Căn giữa tiêu đề" @click="form.title_format.align = 'center'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_center</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.align === 'right' }" aria-label="Căn phải tiêu đề" @click="form.title_format.align = 'right'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_right</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.title_format.align === 'justify' }" aria-label="Căn đều tiêu đề" @click="form.title_format.align = 'justify'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_justify</span>
              </button>
            </div>
          </div>
          <textarea id="article-title" v-model="form.title" class="mt-3 min-h-[60px] w-full resize-none overflow-hidden border-0 bg-transparent p-0 leading-tight text-primary outline-none" :style="typographyStyle(form.title_format)" maxlength="255" rows="1" placeholder="Nhập tiêu đề rõ ràng, hấp dẫn…" @input="autoResizeTextarea"></textarea>
          <p class="mt-3 text-sm text-on-surface-variant">Đường dẫn: /blog/{{ slugPreview || 'duong-dan-bai-viet' }}</p>
        </div>

        <!-- Body Editor Panel with Sticky Toolbar and Floating Selection Bubble Toolbar -->
        <div ref="editorPanelRef" class="ui-panel p-0 relative">
          <!-- Floating Selection Bubble Toolbar (Trình chỉnh sửa nổi xuất hiện khi BÔI ĐEN chữ) -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
          >
            <div
              v-if="selectionToolbar.visible"
              class="floating-selection-bubble absolute z-50 flex items-center gap-1 rounded-2xl border border-slate-700 bg-slate-900/95 p-1.5 text-white shadow-2xl backdrop-blur-md"
              :style="{ top: `${selectionToolbar.top}px`, left: `${selectionToolbar.left}px` }"
              @mousedown.prevent
            >
              <!-- Alignment Buttons Group -->
              <div class="flex items-center gap-0.5 border-r border-slate-700/80 pr-1.5">
                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': !selectionToolbar.formats.align || selectionToolbar.formats.align === 'left' }"
                  title="Căn trái"
                  @click="formatSelection('align', false)"
                >
                  <span class="material-symbols-outlined text-[17px]">format_align_left</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.align === 'center' }"
                  title="Căn giữa"
                  @click="formatSelection('align', 'center')"
                >
                  <span class="material-symbols-outlined text-[17px]">format_align_center</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.align === 'right' }"
                  title="Căn phải"
                  @click="formatSelection('align', 'right')"
                >
                  <span class="material-symbols-outlined text-[17px]">format_align_right</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.align === 'justify' }"
                  title="Căn đều"
                  @click="formatSelection('align', 'justify')"
                >
                  <span class="material-symbols-outlined text-[17px]">format_align_justify</span>
                </button>
              </div>

              <!-- Text Styles Group -->
              <div class="flex items-center gap-0.5 border-r border-slate-700/80 px-1">
                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.bold }"
                  title="In đậm (Bold)"
                  @click="formatSelection('bold', !selectionToolbar.formats.bold)"
                >
                  <span class="material-symbols-outlined text-[17px]">format_bold</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.italic }"
                  title="In nghiêng (Italic)"
                  @click="formatSelection('italic', !selectionToolbar.formats.italic)"
                >
                  <span class="material-symbols-outlined text-[17px]">format_italic</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.underline }"
                  title="Gạch chân (Underline)"
                  @click="formatSelection('underline', !selectionToolbar.formats.underline)"
                >
                  <span class="material-symbols-outlined text-[17px]">format_underlined</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn"
                  :class="{ 'is-active': selectionToolbar.formats.strike }"
                  title="Gạch ngang (Strikethrough)"
                  @click="formatSelection('strike', !selectionToolbar.formats.strike)"
                >
                  <span class="material-symbols-outlined text-[17px]">strikethrough_s</span>
                </button>
              </div>

              <!-- Size & Font Group -->
              <div class="flex items-center gap-1 border-r border-slate-700/80 px-1">
                <select
                  :value="selectionToolbar.formats.size || '16px'"
                  class="bubble-select max-w-[72px]"
                  title="Cỡ chữ"
                  @change="formatSelection('size', $event.target.value)"
                >
                  <option value="12px">12px</option>
                  <option value="14px">14px</option>
                  <option value="16px">16px</option>
                  <option value="18px">18px</option>
                  <option value="24px">24px</option>
                  <option value="32px">32px</option>
                </select>

                <select
                  :value="selectionToolbar.formats.font || 'inter'"
                  class="bubble-select max-w-[90px]"
                  title="Phông chữ"
                  @change="formatSelection('font', $event.target.value)"
                >
                  <option v-for="[val, lbl] in fontOptions" :key="val" :value="val">{{ lbl }}</option>
                </select>
              </div>

              <!-- Color, Image Upload & Clear Group -->
              <div class="flex items-center gap-1 pl-1">
                <input
                  type="color"
                  :value="selectionToolbar.formats.color || '#000000'"
                  class="h-6 w-6 cursor-pointer rounded border-0 bg-transparent p-0"
                  title="Đổi màu chữ"
                  @change="formatSelection('color', $event.target.value)"
                />

                <button
                  type="button"
                  class="bubble-btn text-emerald-400 hover:bg-emerald-900/50"
                  title="Chèn ảnh vào vị trí con trỏ"
                  @click="chooseInlineImage"
                >
                  <span class="material-symbols-outlined text-[17px]">image</span>
                </button>

                <button
                  type="button"
                  class="bubble-btn text-rose-400 hover:bg-rose-900/50"
                  title="Xóa định dạng"
                  @click="cleanSelection"
                >
                  <span class="material-symbols-outlined text-[17px]">format_clear</span>
                </button>
              </div>
            </div>
          </Transition>

          <div ref="editorElement" class="min-h-[520px] bg-white" aria-label="Nội dung bài viết"></div>
          <input ref="inlineImageInput" class="sr-only" type="file" accept="image/*" @change="prepareInlineImage" />
          <div v-if="pendingInlineImage" class="border-t border-outline-variant/40 bg-surface-container-low p-4">
            <p class="font-bold text-primary">Mô tả ảnh trước khi chèn</p>
            <p class="mt-1 text-sm text-on-surface-variant">Mô tả giúp người dùng trình đọc màn hình hiểu nội dung ảnh.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end"><label class="text-sm font-semibold">Nội dung ảnh<input v-model="inlineImageAlt" class="ui-field mt-2" maxlength="500" /></label><button class="ui-btn ui-btn-secondary" type="button" @click="cancelInlineImage">Hủy</button><button class="ui-btn ui-btn-primary" type="button" :disabled="uploadingInlineImage || !inlineImageAlt.trim()" @click="uploadInlineImage">{{ uploadingInlineImage ? 'Đang tải…' : 'Chèn ảnh' }}</button></div>
          </div>
        </div>

        <!-- Excerpt Panel -->
        <div class="ui-panel">
          <label class="block font-bold" for="article-excerpt">Tóm tắt dùng trên danh sách tin</label>
          <p class="mt-1 text-sm leading-6 text-on-surface-variant">Phần này chỉ dùng cho thẻ tin và kết quả tìm kiếm, không tự chèn vào nội dung bài đọc.</p>
          <div class="typography-toolbar mt-3" role="toolbar" aria-label="Định dạng tóm tắt">
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <span class="material-symbols-outlined text-[18px] text-on-surface-variant pl-1" aria-hidden="true">font_download</span>
              <label class="sr-only" for="excerpt-font">Phông chữ tóm tắt</label>
              <select id="excerpt-font" v-model="form.excerpt_format.font" class="typography-select" @change="markDirty">
                <option v-for="[value, label] in fontOptions" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <span class="material-symbols-outlined text-[18px] text-on-surface-variant pl-1" aria-hidden="true">format_size</span>
              <label class="sr-only" for="excerpt-size">Cỡ chữ tóm tắt</label>
              <select id="excerpt-size" v-model="form.excerpt_format.size" class="typography-select typography-size" @change="markDirty">
                <option v-for="size in ['14', '16', '18', '20']" :key="size" :value="size">{{ size }} px</option>
              </select>
            </div>
            <div class="flex items-center gap-1 border-r border-outline-variant/40 pr-2">
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.weight === 'bold' }" :aria-pressed="form.excerpt_format.weight === 'bold'" aria-label="In đậm tóm tắt" @click="toggleFormat(form.excerpt_format, 'weight', 'bold')">
                <span class="material-symbols-outlined text-[18px]">format_bold</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.style === 'italic' }" :aria-pressed="form.excerpt_format.style === 'italic'" aria-label="In nghiêng tóm tắt" @click="toggleFormat(form.excerpt_format, 'style', 'italic')">
                <span class="material-symbols-outlined text-[18px]">format_italic</span>
              </button>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.align === 'left' }" aria-label="Căn trái tóm tắt" @click="form.excerpt_format.align = 'left'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_left</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.align === 'center' }" aria-label="Căn giữa tóm tắt" @click="form.excerpt_format.align = 'center'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_center</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.align === 'right' }" aria-label="Căn phải tóm tắt" @click="form.excerpt_format.align = 'right'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_right</span>
              </button>
              <button type="button" class="typography-icon-button" :class="{ 'is-active': form.excerpt_format.align === 'justify' }" aria-label="Căn đều tóm tắt" @click="form.excerpt_format.align = 'justify'; markDirty()">
                <span class="material-symbols-outlined text-[18px]">format_align_justify</span>
              </button>
            </div>
          </div>
          <textarea id="article-excerpt" v-model="form.excerpt" class="mt-3 min-h-[60px] w-full resize-none overflow-hidden border-0 bg-transparent p-0 leading-relaxed text-on-surface outline-none" :style="typographyStyle(form.excerpt_format)" maxlength="500" rows="1" placeholder="Viết 1–2 câu tóm tắt để người đọc nắm nhanh ý chính…" @input="autoResizeTextarea"></textarea>
        </div>
      </div>

      <!-- Right Sidebar Settings -->
      <aside class="space-y-5">
        <div class="ui-panel space-y-4">
          <h2 class="text-lg font-bold text-primary">Thiết lập bài viết</h2>
          <label class="block text-sm font-semibold">Loại bài
            <select v-model="form.article_type" class="ui-field mt-2" @change="markDirty">
              <option v-for="[value, label] in typeOptions" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>

          <div class="rounded-xl border border-outline-variant/40 bg-surface-container p-3 text-xs">
            <strong class="text-primary">Chuyên mục và thẻ được gợi ý tự động</strong>
            <p class="mt-1 text-on-surface-variant">Hệ thống lấy từ thể loại của sản phẩm đã chọn: {{ derivedTaxonomy.join(', ') || 'chưa có dữ liệu' }}.</p>
          </div>
          <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="form.allow_comments" class="h-5 w-5" type="checkbox" @change="markDirty" /> Cho phép bình luận</label>
          <label v-if="isAdmin" class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input v-model="form.home_featured" class="h-5 w-5" type="checkbox" @change="markDirty" /> Nổi bật trên trang chủ</label>
        </div>

        <div class="ui-panel">
          <h2 class="text-lg font-bold text-primary">Ảnh đại diện</h2>
          <button class="mt-3 flex min-h-44 w-full items-center justify-center overflow-hidden rounded-xl border border-dashed border-outline bg-surface-container-low p-3" type="button" @click="coverInput.click()">
            <img v-if="form.cover_url" :src="form.cover_url" alt="Xem trước ảnh đại diện" class="h-auto max-h-[480px] w-auto max-w-[70%] rounded-lg object-contain" @load="rememberCoverSize" />
            <span v-else class="inline-flex items-center gap-2 text-sm font-bold text-on-surface-variant"><span class="material-symbols-outlined" aria-hidden="true">add_photo_alternate</span>Chọn ảnh đại diện</span>
          </button>
          <input ref="coverInput" class="sr-only" type="file" accept="image/*" @change="chooseCover" />
          <p class="mt-3 text-sm leading-6 text-on-surface-variant">Ảnh được giữ đúng tỷ lệ gốc, không ép cắt thành 16:9.<span v-if="coverNaturalSize"> Kích thước: {{ coverNaturalSize }}.</span></p>
        </div>

        <div class="ui-panel space-y-4">
          <div><h2 class="text-lg font-bold text-primary">Sản phẩm liên quan</h2><p class="mt-1 text-sm text-on-surface-variant">Chọn trước sản phẩm để hệ thống tạo chuyên mục, thẻ và liên kết nội dung phù hợp.</p></div>
          <label class="block text-sm font-semibold">Tìm sách<input v-model="productSearch" class="ui-field mt-2" type="search" placeholder="Nhập tên sách hoặc ISBN…" /></label>
          <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-outline-variant/40 p-2" aria-label="Kết quả tìm sách">
            <label v-for="book in filteredBooks" :key="book.id" class="flex min-h-14 cursor-pointer items-start gap-3 rounded-lg px-3 py-2 hover:bg-surface-container">
              <input type="checkbox" class="h-5 w-5" :checked="form.book_ids.includes(book.id)" @change="toggleBook(book.id)" />
              <img v-if="book.cover_image" :src="getMediaUrl(book.cover_image)" :alt="`Bìa ${book.title}`" class="h-14 w-10 shrink-0 rounded object-contain" loading="lazy" />
              <span class="min-w-0 flex-1"><span class="block break-words text-sm font-semibold leading-5">{{ book.title }}</span><span class="mt-1 block text-xs text-on-surface-variant">{{ book.isbn || book.sku || 'Chưa có ISBN/SKU' }}</span></span>
            </label>
            <p v-if="!filteredBooks.length" class="p-3 text-sm text-on-surface-variant">Không tìm thấy sách phù hợp.</p>
          </div>
          <div v-if="series.length">
            <p class="text-sm font-bold">Chọn nhanh cả bộ sách</p>
            <div class="mt-2 flex flex-wrap gap-2"><button v-for="item in series" :key="item.id" type="button" class="min-h-11 rounded-lg border border-outline-variant px-3 text-sm font-semibold" @click="selectSeries(item)">{{ item.title }}</button></div>
          </div>
          <div v-if="selectedBooks.length" class="space-y-2"><div v-for="book in selectedBooks" :key="book.id" class="flex items-start justify-between gap-3 rounded-lg bg-primary-container px-3 py-2 text-sm font-bold text-on-primary-container"><span class="break-words leading-5">{{ book.title }}</span><button type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-full" :aria-label="`Bỏ ${book.title}`" @click="toggleBook(book.id)">×</button></div></div>
          <div class="rounded-xl border border-outline-variant/40 bg-surface-container p-3">
            <div class="flex items-center gap-1">
              <span class="text-sm font-bold text-primary">Gắn liên kết</span>
              <div class="group/info relative inline-flex cursor-help items-center" tabindex="0" aria-label="Hướng dẫn gắn liên kết">
                <span class="material-symbols-outlined text-[13px] leading-none text-on-surface-variant transition-colors hover:text-primary" aria-hidden="true">info</span>
                <span class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-60 -translate-x-1/2 rounded-xl border border-outline-variant/60 bg-surface-container-highest p-3 text-xs font-normal leading-relaxed text-on-surface shadow-lg group-hover/info:block group-focus/info:block">
                  Ví dụ: Bôi đen chữ “tại đây” trong trình soạn thảo, chọn sách hoặc bộ sách bên dưới rồi bấm Gắn liên kết.
                </span>
              </div>
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <select v-model="linkTarget" class="ui-field"><option value="">Chọn đích liên kết</option><option v-for="target in linkTargets" :key="target.value" :value="target.value">{{ target.label }}</option></select>
              <button type="button" class="ui-btn ui-btn-secondary" :disabled="!linkTarget" @click="applyProductLink">Gắn liên kết</button>
            </div>
          </div>
        </div>

        <details class="ui-panel group">
          <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-bold text-primary"><span>Hiển thị trên Google (SEO)</span><span class="material-symbols-outlined transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span></summary>
          <p class="mt-3 text-sm leading-6 text-on-surface-variant">SEO là tiêu đề và mô tả mà Google có thể dùng trong kết quả tìm kiếm. Có thể để trống: hệ thống sẽ dùng tiêu đề và tóm tắt bài viết.</p>
          <div class="mt-4 space-y-4">
            <label class="block text-sm font-semibold">Tiêu đề trên Google
              <input v-model="form.seo_title" class="ui-field mt-2" maxlength="255" :placeholder="form.title || 'Tiêu đề bài viết'" @input="markDirty" />
            </label>
            <label class="block text-sm font-semibold">Mô tả trên Google
              <textarea v-model="form.seo_description" class="ui-field mt-2 min-h-24" maxlength="320" :placeholder="form.excerpt || 'Tóm tắt ngắn của bài viết'" @input="markDirty"></textarea>
            </label>
            <p class="text-xs text-on-surface-variant">{{ form.seo_description.length }} / 320 ký tự</p>
          </div>
        </details>

        <div v-if="revisions.length" class="ui-panel">
          <h2 class="text-lg font-bold text-primary">Lịch sử phiên bản</h2>
          <ol class="mt-3 space-y-2 text-sm">
            <li v-for="revision in [...revisions].reverse().slice(0, 6)" :key="revision.id">Phiên bản {{ revision.revision }} · {{ new Date(revision.created_at).toLocaleString('vi-VN') }}</li>
          </ol>
        </div>
        <button v-if="hasLocalDraft" class="ui-btn ui-btn-secondary w-full" type="button" @click="restoreLocal">Khôi phục bản tự lưu</button>
      </aside>
    </div>

    <!-- Preview Modal -->
    <div
      v-if="previewOpen"
      class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/60 p-4 sm:p-6 md:p-10 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="preview-title"
      @click="previewOpen = false"
      @keydown.esc="previewOpen = false"
    >
      <article class="relative my-auto w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl md:p-10 text-on-surface" @click.stop>
        <div class="flex items-center justify-between gap-4 border-b border-outline-variant/30 pb-4">
          <h2 id="preview-title" class="text-xl font-bold text-primary">Xem trước bài viết</h2>
          <button class="ui-btn ui-btn-secondary flex items-center gap-1 cursor-pointer" type="button" @click="previewOpen = false">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span> Đóng
          </button>
        </div>

        <div class="mx-auto max-w-3xl text-center mt-6">
          <p class="text-sm font-bold uppercase tracking-wider text-secondary">
            {{ typeOptions.find(([value]) => value === form.article_type)?.[1] }}
          </p>
          <h1 class="mt-3 break-words text-3xl font-bold leading-tight text-primary md:text-5xl" :style="typographyStyle(form.title_format)">
            {{ form.title || 'Tiêu đề bài viết' }}
          </h1>
          <p v-if="form.excerpt" class="mt-4 text-base leading-relaxed text-on-surface-variant md:text-lg" :style="typographyStyle(form.excerpt_format)">
            {{ form.excerpt }}
          </p>
        </div>

        <div v-if="form.cover_url" class="my-8 text-center">
          <img :src="form.cover_url" alt="Xem trước ảnh đại diện" class="mx-auto block h-auto max-w-[70%] rounded-xl object-contain shadow-md" />
        </div>

        <div class="newsroom-prose mx-auto mt-8" v-html="safePreview"></div>
      </article>
    </div>
  </section>
</template>

<style scoped>
/* Sticky Top Toolbar */
:deep(.ql-toolbar.ql-snow) {
  position: sticky !important;
  top: 0 !important;
  z-index: 30 !important;
  display: flex !important;
  flex-wrap: wrap !important;
  align-items: center !important;
  gap: 4px !important;
  border: 1px solid var(--color-outline-variant) !important;
  background: #ffffff !important;
  padding: 6px 8px !important;
  border-top-left-radius: 12px !important;
  border-top-right-radius: 12px !important;
  border-bottom: 1px solid color-mix(in srgb, var(--color-outline-variant) 60%, transparent) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

:deep(.ql-snow .ql-formats) {
  display: inline-flex !important;
  align-items: center !important;
  gap: 2px !important;
  margin-right: 4px !important;
  padding-right: 6px !important;
  border-right: 1px solid color-mix(in srgb, var(--color-outline-variant) 40%, transparent) !important;
  height: 32px !important;
}
:deep(.ql-snow .ql-formats:last-child) {
  border-right: 0 !important;
  margin-right: 0 !important;
  padding-right: 0 !important;
}

:deep(.ql-snow.ql-toolbar button) {
  width: 32px !important;
  height: 32px !important;
  padding: 0 !important;
  border-radius: 6px !important;
  transition: all 0.15s ease !important;
  border: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-sizing: border-box !important;
  background: transparent;
}
:deep(.ql-snow.ql-toolbar button svg) {
  width: 18px !important;
  height: 18px !important;
  float: none !important;
  margin: 0 !important;
}
:deep(.ql-snow.ql-toolbar button:hover),
:deep(.ql-snow .ql-picker-label:hover) {
  background: var(--color-surface-container-low) !important;
  color: var(--color-primary) !important;
  border-radius: 6px !important;
}
:deep(.ql-snow.ql-toolbar button.ql-active),
:deep(.ql-snow .ql-picker-label.ql-active) {
  background: var(--color-primary-container) !important;
  color: var(--color-on-primary-container) !important;
  border-radius: 6px !important;
}
:deep(.ql-snow.ql-toolbar button.ql-active .ql-stroke) { stroke: var(--color-on-primary-container) !important; }
:deep(.ql-snow.ql-toolbar button.ql-active .ql-fill) { fill: var(--color-on-primary-container) !important; }

:deep(.ql-snow .ql-picker) {
  height: 32px !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  color: var(--color-on-surface) !important;
  display: inline-flex !important;
  align-items: center !important;
}
:deep(.ql-snow .ql-picker-label) {
  height: 32px !important;
  padding: 0 8px !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 4px !important;
  border-radius: 6px !important;
  transition: all 0.15s ease !important;
  font-size: 14px !important;
  font-weight: 500 !important;
}
:deep(.ql-snow .ql-icon-picker .ql-picker-label),
:deep(.ql-snow .ql-color-picker .ql-picker-label) {
  width: 32px !important;
  padding: 0 !important;
  justify-content: center !important;
}
:deep(.ql-snow .ql-picker-label svg) {
  width: 14px !important;
  height: 14px !important;
  float: none !important;
  margin: 0 !important;
  position: static !important;
}
:deep(.ql-snow .ql-icon-picker .ql-picker-label svg),
:deep(.ql-snow .ql-color-picker .ql-picker-label svg) {
  width: 18px !important;
  height: 18px !important;
}
:deep(.ql-snow .ql-picker-options) {
  border-radius: 12px !important;
  border: 1px solid color-mix(in srgb, var(--color-outline-variant) 50%, transparent) !important;
  box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.12) !important;
  padding: 6px !important;
  background: #ffffff !important;
}
:deep(.ql-snow .ql-picker-item) {
  border-radius: 6px !important;
  padding: 6px 10px !important;
  transition: background 0.12s ease !important;
  font-size: 14px !important;
}
:deep(.ql-snow .ql-picker-item:hover) { background: var(--color-surface-container-low) !important; }

:deep(.ql-container.ql-snow) {
  min-height: 470px;
  border: 1px solid var(--color-outline-variant);
  border-top: 0;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
  color: var(--color-on-surface);
  font-family: var(--font-literata);
  font-size: 1rem;
  line-height: 1.75;
}
:deep(.ql-editor) { min-height: 470px; color: var(--color-on-surface); }
:deep(.ql-editor img) { display: block; max-width: 70% !important; height: auto; margin: 1.75rem auto; object-fit: contain; border-radius: 0.75rem; }

:deep(.ql-font-inter) { font-family: Inter, sans-serif; }
:deep(.ql-font-literata) { font-family: Literata, Georgia, serif; }
:deep(.ql-font-times-new-roman) { font-family: "Times New Roman", Times, serif; }
:deep(.ql-font-arial) { font-family: Arial, sans-serif; }
:deep(.ql-font-georgia) { font-family: Georgia, serif; }
:deep(.ql-font-monospace) { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }

:deep(.ql-size-12px) { font-size: 12px; }
:deep(.ql-size-14px) { font-size: 14px; }
:deep(.ql-size-16px) { font-size: 16px; }
:deep(.ql-size-18px) { font-size: 18px; }
:deep(.ql-size-24px) { font-size: 24px; }
:deep(.ql-size-32px) { font-size: 32px; }

:deep(.ql-snow .ql-picker.ql-font) { width: 150px; }
:deep(.ql-snow .ql-picker.ql-size) { width: 82px; }
:deep(.ql-snow .ql-picker.ql-font .ql-picker-label::before),
:deep(.ql-snow .ql-picker.ql-font .ql-picker-item::before) { content: 'Inter'; }
:deep(.ql-snow .ql-picker.ql-font [data-value='literata']::before) { content: 'Literata'; }
:deep(.ql-snow .ql-picker.ql-font [data-value='times-new-roman']::before) { content: 'Times New Roman'; }
:deep(.ql-snow .ql-picker.ql-font [data-value='arial']::before) { content: 'Arial'; }
:deep(.ql-snow .ql-picker.ql-font [data-value='georgia']::before) { content: 'Georgia'; }
:deep(.ql-snow .ql-picker.ql-font [data-value='monospace']::before) { content: 'Monospace'; }

:deep(.ql-snow .ql-picker.ql-size .ql-picker-label::before) { content: 'Cỡ chữ'; }
:deep(.ql-snow .ql-picker.ql-size .ql-picker-label[data-value]::before),
:deep(.ql-snow .ql-picker.ql-size .ql-picker-item::before) { content: attr(data-value); }

/* Floating Selection Bubble Styling */
.bubble-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: #cbd5e1;
  cursor: pointer;
  transition: all 0.15s ease;
}
.bubble-btn:hover {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
}
.bubble-btn.is-active {
  background: #3b82f6;
  color: #ffffff;
  font-weight: bold;
}
.bubble-select {
  height: 28px;
  border: 0;
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
  padding: 0 4px;
  outline: none;
  cursor: pointer;
}
.bubble-select option {
  background: #0f172a;
  color: #ffffff;
}

.ui-panel,
.ui-field { min-width: 0; max-width: 100%; }
:deep(.newsroom-prose) { max-width: 72ch; font-family: var(--font-literata); font-size: 1.0625rem; line-height: 1.8; color: var(--color-on-surface); }
:deep(.newsroom-prose p) { margin-block: 1.25em; }
:deep(.newsroom-prose h1) { margin-top: 1.75em; font-size: 2rem; font-weight: 800; color: var(--color-primary); }
:deep(.newsroom-prose h2) { margin-top: 1.5em; font-size: 1.5rem; font-weight: 800; color: var(--color-primary); }
:deep(.newsroom-prose h3) { margin-top: 1.25em; font-size: 1.25rem; font-weight: 800; color: var(--color-primary); }
:deep(.newsroom-prose img) { display: block; max-width: 70% !important; height: auto; margin: 1.75rem auto; object-fit: contain; border-radius: 0.75rem; }
:deep(.newsroom-prose a) { color: var(--color-secondary); font-weight: 700; text-decoration: underline; }
:deep(.newsroom-prose blockquote) { border-left: 4px solid var(--color-secondary); margin: 1.5rem 0; padding: 0.75rem 1.25rem; background: var(--color-surface-container-low); border-radius: 0 0.5rem 0.5rem 0; }
:deep(.newsroom-prose .ql-align-center) { text-align: center; }
:deep(.newsroom-prose .ql-align-right) { text-align: right; }
:deep(.newsroom-prose .ql-align-justify) { text-align: justify; }
:deep(.newsroom-prose .ql-font-inter) { font-family: Inter, sans-serif; }
:deep(.newsroom-prose .ql-font-literata) { font-family: Literata, Georgia, serif; }
:deep(.newsroom-prose .ql-font-times-new-roman) { font-family: "Times New Roman", Times, serif; }
:deep(.newsroom-prose .ql-font-arial) { font-family: Arial, sans-serif; }
:deep(.newsroom-prose .ql-font-georgia) { font-family: Georgia, serif; }
:deep(.newsroom-prose .ql-font-monospace) { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
:deep(.newsroom-prose .ql-size-12px) { font-size: 12px; }
:deep(.newsroom-prose .ql-size-14px) { font-size: 14px; }
:deep(.newsroom-prose .ql-size-16px) { font-size: 16px; }
:deep(.newsroom-prose .ql-size-18px) { font-size: 18px; }
:deep(.newsroom-prose .ql-size-24px) { font-size: 24px; }
:deep(.newsroom-prose .ql-size-32px) { font-size: 32px; }
.typography-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; border: 1px solid var(--color-outline-variant); background: #ffffff; padding: 6px 8px; border-top-left-radius: 8px; border-top-right-radius: 8px; }
.typography-select { height: 32px; border: 0; background: transparent; color: var(--color-on-surface); font-size: 14px; font-weight: 500; padding: 0 4px; cursor: pointer; outline: none; }
.typography-size { min-width: 64px; }
.typography-icon-button { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; border: 0; background: transparent; color: var(--color-on-surface-variant); cursor: pointer; transition: all 0.15s ease; }
.typography-icon-button:hover { background: var(--color-surface-container-low); color: var(--color-primary); }
.typography-icon-button.is-active { background: var(--color-primary-container); color: var(--color-on-primary-container); }
.typography-select:focus-visible,
.typography-icon-button:focus-visible { position: relative; z-index: 1; outline: 2px solid var(--color-primary); }
</style>
