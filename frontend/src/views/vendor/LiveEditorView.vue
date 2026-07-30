<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = route.params.bookId
const apiBase = '/api/vendor/books'
const backRoute = 'vendor-books'
const previewRoute = 'vendor-device-preview'
const bookTitle = ref('Tác phẩm tự sáng tác')
const chapters = ref([])
const activeChapterIndex = ref(-1)
const activeChapter = ref({
  title: '',
  content: '',
  order: 1,
  is_free: false,
  current_revision: 0,
})

const loading = ref(true)
const saving = ref(false)
let autosaveTimer = null
const wordCount = ref(0)
const charCount = ref(0)
const error = ref(null)

const fetchChapters = async () => {
  loading.value = true
  error.value = null
  try {
    const bookRes = await apiClient.get(`${apiBase}/${bookId}`)
    if (bookRes.data?.data?.title) {
      bookTitle.value = bookRes.data.data.title
    }

    const res = await apiClient.get(`${apiBase}/${bookId}/chapters`)
    if (res.data?.status === 'success') {
      chapters.value = (res.data.data || []).map(normalizeChapter)
      if (chapters.value.length > 0) {
        selectChapter(0)
      } else {
        createNewChapter()
      }
    }
  } catch (e) {
    console.error('Không tải được danh sách chương', e)
    error.value = e.response?.data?.message || 'Không thể kết nối API chương sách.'
    chapters.value = []
    activeChapterIndex.value = -1
    activeChapter.value = emptyChapter()
  } finally {
    loading.value = false
  }
}

const selectChapter = (index) => {
  activeChapterIndex.value = index
  activeChapter.value = normalizeChapter(chapters.value[index])
  updateCounts()
}

const emptyChapter = (order = 1) => ({
  id: null,
  title: `Chương ${order}: Chương mới chưa đặt tên`,
  content: '',
  order,
  is_free: false,
  current_revision: 0,
})

const normalizeChapter = (chapter) => ({
  ...emptyChapter(chapter?.order || chapter?.chapter_number || 1),
  ...chapter,
  order: chapter?.order || chapter?.chapter_number || 1,
  is_free: Boolean(chapter?.is_free ?? chapter?.is_preview),
})

const createNewChapter = () => {
  const existingDraftIndex = chapters.value.findIndex((chapter) => !chapter.id)
  if (existingDraftIndex >= 0) {
    selectChapter(existingDraftIndex)
    return
  }

  const newIdx = chapters.value.length + 1
  const newCh = emptyChapter(newIdx)
  chapters.value.push(newCh)
  selectChapter(chapters.value.length - 1)
}

const updateCounts = () => {
  const txt = activeChapter.value.content || ''
  wordCount.value = txt.trim() === '' ? 0 : txt.trim().split(/\s+/).length
  charCount.value = txt.length
}

const saveChapter = async () => {
  const ch = activeChapter.value
  if (activeChapterIndex.value < 0 || !ch.title?.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu tiêu đề', detail: 'Hãy nhập tiêu đề chương trước khi lưu.', life: 3000 })
    return
  }

  clearTimeout(autosaveTimer)
  saving.value = true
  try {
    let res
    if (ch.id) {
      res = await apiClient.put(`${apiBase}/${bookId}/chapters/${ch.id}`, {
        title: ch.title.trim(),
        content: ch.content,
        is_free: ch.is_free,
        order: ch.order,
      })
    } else {
      res = await apiClient.post(`${apiBase}/${bookId}/chapters`, {
        title: ch.title.trim(),
        content: ch.content,
        is_free: ch.is_free,
        order: ch.order,
      })
    }

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã lưu chương thành công.', life: 2000 })
      const savedChapter = normalizeChapter(res.data.data)
      chapters.value.splice(activeChapterIndex.value, 1, savedChapter)
      activeChapter.value = { ...savedChapter }
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra.'
    toast.add({ severity: 'error', summary: 'Không lưu được', detail: msg, life: 3000 })
  } finally {
    saving.value = false
  }
}

const autosaveChapter = async () => {
  const ch = activeChapter.value
  if (!ch.id || saving.value) return
  const chapterId = ch.id
  const chapterIndex = activeChapterIndex.value
  try {
    const res = await apiClient.patch(`${apiBase}/${bookId}/chapters/${ch.id}/autosave`, {
      title: ch.title,
      content: ch.content,
      is_free: ch.is_free,
      expected_revision: ch.current_revision || 0,
    })
    const savedChapter = normalizeChapter(res.data.data)
    chapters.value.splice(chapterIndex, 1, savedChapter)
    if (activeChapter.value.id === chapterId) {
      activeChapter.value = { ...savedChapter }
    }
  } catch (e) {
    if (e.response?.status === 422) {
      toast.add({ severity: 'warn', summary: 'Xung đột phiên bản', detail: 'Chương có phiên bản mới hơn. Hãy tải lại trước khi tiếp tục.', life: 4000 })
    }
  }
}

const deleteChapter = async () => {
  const ch = activeChapter.value
  if (!ch.id) {
    // Just remove from local array
    chapters.value.splice(activeChapterIndex.value, 1)
    if (chapters.value.length > 0) {
      selectChapter(0)
    } else {
      activeChapterIndex.value = -1
      createNewChapter()
    }
    return
  }

  if (!confirm('Bạn có chắc chắn muốn xóa chương này?')) return

  try {
    await apiClient.delete(`${apiBase}/${bookId}/chapters/${ch.id}`)
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Đã xóa chương sách.', life: 2000 })
    chapters.value.splice(activeChapterIndex.value, 1)
    if (chapters.value.length > 0) {
      selectChapter(0)
    } else {
      activeChapterIndex.value = -1
      createNewChapter()
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa chương.', life: 3000 })
  }
}

watch(() => activeChapter.value.content, () => {
  updateCounts()
  clearTimeout(autosaveTimer)
  autosaveTimer = setTimeout(autosaveChapter, 1200)
})

onMounted(() => {
  fetchChapters()
})

onBeforeUnmount(() => {
  clearTimeout(autosaveTimer)
})
</script>

<template>
  <div class="live-editor min-h-screen lg:h-screen flex flex-col lg:overflow-hidden bg-slate-50">
    <Toast />
    
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 px-3 sm:px-6 py-3 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center shrink-0">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" aria-label="Quay lại danh sách tác phẩm" class="p-button-text p-button-secondary p-button-sm min-h-11 min-w-11" @click="router.push({ name: backRoute })" />
        <div class="flex flex-col">
          <span class="text-sm font-bold text-slate-800">{{ bookTitle }}</span>
          <span class="text-xs text-slate-400">Trình soạn thảo trực tiếp chuyên nghiệp</span>
        </div>
      </div>
      
      <div class="flex items-center gap-4">
        <div class="text-xs text-slate-400 flex items-center gap-1">
          <i class="pi pi-cloud-upload"></i> Tự động lưu bản nháp
        </div>
        <div class="flex gap-2">
          <Button label="Xem thử thiết bị" icon="pi pi-eye" class="p-button-outlined p-button-sm text-xs min-h-11" @click="router.push({ name: previewRoute, params: { bookId } })" />
          <Button label="Lưu chương" icon="pi pi-save" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs" :loading="saving" :disabled="loading || !!error || activeChapterIndex < 0" @click="saveChapter" />
        </div>
      </div>
    </header>

    <!-- Loading State -->
    <div v-if="loading" role="status" aria-live="polite" class="flex-1 flex items-center justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" role="alert" class="flex-1 flex items-center justify-center p-8 bg-slate-50">
      <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm text-center max-w-md w-full space-y-4">
        <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
          <i class="pi pi-exclamation-triangle text-xl"></i>
        </div>
        <h3 class="text-base font-bold text-slate-900">Không thể tải dữ liệu chương sách</h3>
        <p class="text-xs text-slate-500 leading-relaxed">{{ error }}</p>
        <Button label="Thử lại" icon="pi pi-refresh" class="p-button-primary bg-indigo-600 p-button-sm text-xs" @click="fetchChapters" />
      </div>
    </div>

    <!-- Workspace -->
    <div v-else class="flex flex-1 flex-col overflow-visible lg:flex-row lg:overflow-hidden">
      <!-- Chapters sidebar -->
      <aside class="w-full max-h-52 bg-white border-b border-slate-200 flex flex-col shrink-0 lg:max-h-none lg:w-64 lg:border-b-0 lg:border-r">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Danh mục chương</span>
          <Button icon="pi pi-plus" aria-label="Tạo chương mới" class="p-button-text p-button-sm min-h-11 min-w-11" @click="createNewChapter" />
        </div>

        <div class="flex-1 overflow-y-auto">
          <div v-if="chapters.length === 0" class="p-4 text-xs text-slate-400 text-center space-y-2">
            <p>Chưa có chương sách nào.</p>
            <Button label="Tạo chương đầu tiên" icon="pi pi-plus" class="p-button-outlined p-button-sm text-xs" @click="createNewChapter" />
          </div>
          <button
            v-else
            v-for="(ch, idx) in chapters" 
            :key="idx"
            :class="[
              'w-full min-h-11 p-3 flex items-center justify-between text-left cursor-pointer border-l-4 transition-colors text-sm',
              activeChapterIndex === idx ? 'bg-indigo-50/50 border-indigo-600 text-indigo-700 font-bold' : 'border-transparent text-slate-600 hover:bg-slate-50'
            ]"
            @click="selectChapter(idx)"
            type="button"
          >
            <span class="truncate pr-2">{{ ch.title }}</span>
            <span v-if="ch.is_free" class="bg-emerald-100 text-emerald-700 text-[10px] px-1.5 py-0.5 rounded-full font-semibold shrink-0">Đọc thử</span>
          </button>
        </div>
      </aside>

      <!-- Main writing canvas -->
      <main class="flex-1 flex flex-col bg-slate-50 relative overflow-visible p-3 sm:p-6 lg:overflow-hidden">
        <!-- Chapter Meta Edit -->
        <div class="max-w-3xl mx-auto w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-6 mb-4 flex flex-col gap-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label for="chapter-title" class="text-sm font-bold text-slate-600">Tiêu đề chương</label>
              <InputText id="chapter-title" v-model="activeChapter.title" placeholder="Nhập tiêu đề chương (VD: Chương 1: Sự khởi đầu)" class="w-full text-sm" />
            </div>
            
            <div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-slate-500">Chế độ đọc</label>
                <div class="flex items-center gap-2 mt-2">
                  <input type="checkbox" id="isPreview" v-model="activeChapter.is_free" class="rounded text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                  <label for="isPreview" class="text-xs font-semibold text-slate-600 cursor-pointer">Cho đọc thử</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Editor body -->
        <div class="flex-grow max-w-3xl mx-auto w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8 overflow-y-auto">
          <label for="chapter-content" class="sr-only">Nội dung chương</label>
          <Textarea
            id="chapter-content"
            v-model="activeChapter.content" 
            placeholder="Bắt đầu viết chương truyện mới của bạn tại đây..." 
            rows="18" 
            class="w-full text-slate-800 font-serif leading-relaxed text-base border-none focus:ring-0 p-0 resize-none outline-none focus:outline-none"
          />
        </div>

        <!-- Editor Footer Info -->
        <div class="max-w-3xl mx-auto w-full flex justify-between items-center mt-3 text-xs text-slate-400">
          <div class="flex gap-4">
            <span>Từ: <strong>{{ wordCount }}</strong></span>
            <span>Ký tự: <strong>{{ charCount }}</strong></span>
          </div>
          <div>
            <Button label="Xóa chương này" icon="pi pi-trash" class="p-button-danger p-button-text p-button-sm text-rose-600 text-xs" @click="deleteChapter" />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.live-editor {
  font-family: 'Inter', sans-serif;
}
:deep(.p-textarea) {
  box-shadow: none !important;
  border: none !important;
}
:deep(.p-button), :deep(.p-inputtext) { min-height: 44px; }
</style>
