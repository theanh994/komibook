<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import apiClient from '@/services/axios'

const router = useRouter()
const editorElement = ref()
const books = ref([])
const bookQuery = ref('')
const suggestionsOpen = ref(false)
const loading = ref(false)
const loadingBooks = ref(true)
const error = ref('')
const message = ref('')
const form = reactive({ book_id: '', title: '', body: '' })
let quill

const filteredBooks = computed(() => {
  const query = bookQuery.value.trim().toLocaleLowerCase('vi')
  if (!query) return books.value.slice(0, 8)
  return books.value.filter((book) => book.title.toLocaleLowerCase('vi').includes(query)).slice(0, 12)
})
const plainTextLength = computed(() => form.body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().length)

const chooseBook = (book) => {
  form.book_id = book.id
  bookQuery.value = book.title
  suggestionsOpen.value = false
}

const loadBooks = async () => {
  loadingBooks.value = true
  try {
    const response = await apiClient.get('/api/books', { params: { per_page: 100 } })
    const payload = response.data.data
    books.value = Array.isArray(payload) ? payload : payload?.data || []
  } catch {
    error.value = 'Không thể tải danh sách sách.'
  } finally {
    loadingBooks.value = false
  }
}

const submit = async () => {
  loading.value = true
  error.value = ''
  message.value = ''
  if (!form.book_id) {
    error.value = 'Hãy tìm và chọn đúng một cuốn sách trong danh sách gợi ý.'
    loading.value = false
    return
  }
  try {
    const response = await apiClient.post('/api/article-submissions', form)
    message.value = response.data.message
    Object.assign(form, { book_id: '', title: '', body: '' })
    bookQuery.value = ''
    quill.setContents([])
  } catch (requestError) {
    const validation = requestError.response?.data?.errors
    error.value = validation ? Object.values(validation).flat().join(' ') : (requestError.response?.data?.message || 'Không thể gửi bài review.')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  quill = new Quill(editorElement.value, {
    theme: 'snow',
    placeholder: 'Chia sẻ trải nghiệm đọc, lập luận và góc nhìn của bạn…',
    modules: {
      toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'blockquote'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']],
    },
  })
  quill.on('text-change', () => { form.body = quill.root.innerHTML })
  await nextTick()
  await loadBooks()
})

onBeforeUnmount(() => { quill = null })
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10 md:px-gutter md:py-14">
    <section class="mx-auto max-w-4xl">
      <button class="inline-flex min-h-11 items-center gap-2 font-bold text-primary" type="button" @click="router.push('/blog')"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>Về trang tin tức</button>
      <p class="mt-5 text-sm font-bold uppercase tracking-wider text-secondary">Cộng đồng KomiBook</p>
      <h1 class="mt-2 text-4xl font-bold text-primary">Gửi review chuyên sâu</h1>
      <p class="mt-4 max-w-3xl leading-7 text-on-surface-variant">Tìm sách theo tên, soạn nội dung có định dạng và gửi tới ban biên tập. Review không tự động công khai.</p>

      <form class="ui-panel mt-8 space-y-6" @submit.prevent="submit">
        <div class="relative">
          <label for="review-book-search" class="block font-bold">Tìm cuốn sách muốn review</label>
          <div class="relative mt-2">
            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true">search</span>
            <input id="review-book-search" v-model="bookQuery" class="ui-field pl-11" type="search" autocomplete="off" placeholder="Nhập tên sách…" :disabled="loadingBooks" @focus="suggestionsOpen = true" @input="form.book_id = ''; suggestionsOpen = true" />
          </div>
          <ul v-if="suggestionsOpen && filteredBooks.length" class="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-2 shadow-lg" role="listbox">
            <li v-for="book in filteredBooks" :key="book.id"><button type="button" class="flex min-h-11 w-full items-center rounded-lg px-3 text-left font-semibold hover:bg-surface-container" @click="chooseBook(book)">{{ book.title }}</button></li>
          </ul>
          <p class="mt-2 text-sm text-on-surface-variant">{{ form.book_id ? 'Đã chọn sách phù hợp.' : 'Kết quả được lọc ngay khi nhập; không cần kéo qua toàn bộ danh sách.' }}</p>
        </div>

        <label class="block font-bold" for="review-title">Tiêu đề review<input id="review-title" v-model="form.title" class="ui-field mt-2" required minlength="10" maxlength="255" placeholder="Điểm nhìn riêng của bạn về cuốn sách" /></label>
        <div>
          <label class="block font-bold" for="review-editor">Nội dung review</label>
          <div id="review-editor" class="mt-2 overflow-hidden rounded-xl border border-outline-variant bg-white">
            <div ref="editorElement" class="min-h-96 font-literata text-base leading-7"></div>
          </div>
          <p class="mt-2 text-sm text-on-surface-variant">{{ plainTextLength.toLocaleString('vi-VN') }} / 50.000 ký tự · tối thiểu 500 ký tự</p>
        </div>
        <p v-if="message" class="ui-alert" role="status">{{ message }}</p>
        <p v-if="error" class="ui-alert ui-alert-error" role="alert">{{ error }}</p>
        <button class="ui-btn ui-btn-primary" type="submit" :disabled="loading || plainTextLength < 500">{{ loading ? 'Đang gửi…' : 'Gửi tới ban biên tập' }}</button>
      </form>
    </section>
  </main>
</template>
