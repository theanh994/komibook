<script setup>
import { ref, onMounted, watch } from 'vue'
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
const bookTitle = ref('Tác phẩm tự sáng tác')
const chapters = ref([])
const activeChapterIndex = ref(-1)
const activeChapter = ref({
  title: '',
  content: '',
  price: 0,
  is_preview: false,
})

const loading = ref(true)
const saving = ref(false)
const wordCount = ref(0)
const charCount = ref(0)
const error = ref(null)

const fetchChapters = async () => {
  loading.value = true
  error.value = null
  try {
    const bookRes = await apiClient.get(`/api/vendor/books/${bookId}`)
    if (bookRes.data?.data?.title) {
      bookTitle.value = bookRes.data.data.title
    }

    const res = await apiClient.get(`/api/vendor/books/${bookId}/chapters`)
    if (res.data?.status === 'success') {
      chapters.value = res.data.data || []
      if (chapters.value.length > 0) {
        selectChapter(0)
      } else {
        activeChapterIndex.value = -1
        activeChapter.value = { title: '', content: '', price: 0, is_preview: false }
      }
    }
  } catch (e) {
    console.error('Không tải được danh sách chương', e)
    error.value = e.response?.data?.message || 'Không thể kết nối API chương sách.'
    chapters.value = []
    activeChapterIndex.value = -1
    activeChapter.value = { title: '', content: '', price: 0, is_preview: false }
  } finally {
    loading.value = false
  }
}

const selectChapter = (index) => {
  activeChapterIndex.value = index
  activeChapter.value = { ...chapters.value[index] }
  updateCounts()
}

const createNewChapter = () => {
  const newIdx = chapters.value.length + 1
  const newCh = {
    id: null,
    title: `Chương ${newIdx}: Chương mới chưa đặt tên`,
    content: '',
    price: 0,
    is_preview: false,
    chapter_number: newIdx,
  }
  chapters.value.push(newCh)
  selectChapter(chapters.value.length - 1)
}

const updateCounts = () => {
  const txt = activeChapter.value.content || ''
  wordCount.value = txt.trim() === '' ? 0 : txt.trim().split(/\s+/).length
  charCount.value = txt.length
}

const saveChapter = async () => {
  saving.value = true
  try {
    const ch = activeChapter.value
    let res
    if (ch.id) {
      res = await apiClient.put(`/api/vendor/books/${bookId}/chapters/${ch.id}`, {
        title: ch.title,
        content: ch.content,
        price: ch.price,
        is_preview: ch.is_preview,
        chapter_number: ch.chapter_number
      })
    } else {
      res = await apiClient.post(`/api/vendor/books/${bookId}/chapters`, {
        title: ch.title,
        content: ch.content,
        price: ch.price,
        is_preview: ch.is_preview,
        chapter_number: ch.chapter_number
      })
    }

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã lưu chương thành công.', life: 2000 })
      // Update local state
      chapters.value[activeChapterIndex.value] = res.data.data
      activeChapter.value = { ...res.data.data }
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra.'
    toast.add({ severity: 'error', summary: 'Không lưu được', detail: msg, life: 3000 })
  } finally {
    saving.value = false
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
      activeChapter.value = { title: '', content: '', price: 0, is_preview: false }
    }
    return
  }

  if (!confirm('Bạn có chắc chắn muốn xóa chương này?')) return

  try {
    await apiClient.delete(`/api/vendor/books/${bookId}/chapters/${ch.id}`)
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Đã xóa chương sách.', life: 2000 })
    chapters.value.splice(activeChapterIndex.value, 1)
    if (chapters.value.length > 0) {
      selectChapter(0)
    } else {
      activeChapterIndex.value = -1
      activeChapter.value = { title: '', content: '', price: 0, is_preview: false }
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa chương.', life: 3000 })
  }
}

watch(() => activeChapter.value.content, () => {
  updateCounts()
})

onMounted(() => {
  fetchChapters()
})
</script>

<template>
  <div class="live-editor h-screen flex flex-col overflow-hidden bg-slate-50">
    <Toast />
    
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 px-6 py-3 flex justify-between items-center shrink-0">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" class="p-button-text p-button-secondary p-button-sm" @click="router.push({ name: 'author-dashboard' })" />
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
          <Button label="Xem thử thiết bị" icon="pi pi-eye" class="p-button-outlined p-button-sm text-xs" @click="router.push({ name: 'author-device-preview', params: { bookId } })" />
          <Button label="Lưu chương" icon="pi pi-save" class="p-button-primary bg-indigo-600 text-white p-button-sm text-xs" :loading="saving" :disabled="loading || !!error" @click="saveChapter" />
        </div>
      </div>
    </header>

    <!-- Loading State -->
    <div v-if="loading" class="flex-1 flex items-center justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex-1 flex items-center justify-center p-8 bg-slate-50">
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
    <div v-else class="flex flex-1 overflow-hidden">
      <!-- Chapters sidebar -->
      <aside class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center">
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Danh mục chương</span>
          <Button icon="pi pi-plus" class="p-button-text p-button-sm" @click="createNewChapter" />
        </div>

        <div class="flex-1 overflow-y-auto">
          <div v-if="chapters.length === 0" class="p-4 text-xs text-slate-400 text-center space-y-2">
            <p>Chưa có chương sách nào.</p>
            <Button label="Tạo chương đầu tiên" icon="pi pi-plus" class="p-button-outlined p-button-sm text-xs" @click="createNewChapter" />
          </div>
          <div 
            v-else
            v-for="(ch, idx) in chapters" 
            :key="idx"
            :class="[
              'p-3 flex items-center justify-between cursor-pointer border-l-4 transition-colors text-sm',
              activeChapterIndex === idx ? 'bg-indigo-50/50 border-indigo-600 text-indigo-700 font-bold' : 'border-transparent text-slate-600 hover:bg-slate-50'
            ]"
            @click="selectChapter(idx)"
          >
            <span class="truncate pr-2">{{ ch.title }}</span>
            <span v-if="ch.is_preview" class="bg-emerald-100 text-emerald-700 text-[10px] px-1.5 py-0.5 rounded-full font-semibold shrink-0">Đọc thử</span>
          </div>
        </div>
      </aside>

      <!-- Main writing canvas -->
      <main class="flex-1 flex flex-col bg-slate-50 relative overflow-hidden p-6">
        <!-- Chapter Meta Edit -->
        <div class="max-w-3xl mx-auto w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-6 mb-4 flex flex-col gap-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold text-slate-500">Tiêu đề chương</label>
              <InputText v-model="activeChapter.title" placeholder="Nhập tiêu đề chương (VD: Chương 1: Sự khởi đầu)" class="w-full text-sm" />
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-slate-500">Chế độ đọc</label>
                <div class="flex items-center gap-2 mt-2">
                  <input type="checkbox" id="isPreview" v-model="activeChapter.is_preview" class="rounded text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                  <label for="isPreview" class="text-xs font-semibold text-slate-600 cursor-pointer">Cho đọc thử</label>
                </div>
              </div>

              <div v-if="!activeChapter.is_preview" class="flex flex-col gap-1.5">
                <label class="text-xs font-bold text-slate-500">Giá bán (đ)</label>
                <input type="number" v-model="activeChapter.price" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs w-full text-slate-800" placeholder="Nhập giá bán" />
              </div>
            </div>
          </div>
        </div>

        <!-- Editor body -->
        <div class="flex-grow max-w-3xl mx-auto w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8 overflow-y-auto">
          <Textarea 
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
</style>
