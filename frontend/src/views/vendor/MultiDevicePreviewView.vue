<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/axios'

import Button from 'primevue/button'

const route = useRoute()
const router = useRouter()

const bookId = route.params.bookId
const apiBase = '/api/vendor/books'
const book = ref({ title: 'Ấn phẩm', author_name: 'Người viết' })
const chapters = ref([])
const activeChapter = ref({ title: 'Chương 1', content: 'Đang tải nội dung...' })

const currentDevice = ref('mobile') // 'desktop', 'tablet', 'mobile'
const readerTheme = ref('light') // 'light', 'dark', 'sepia'
const fontSize = ref(16) // font size in px
const loading = ref(true)
const error = ref(null)

const fetchBookData = async () => {
  loading.value = true
  error.value = null
  try {
    const bookRes = await apiClient.get(`${apiBase}/${bookId}`)
    if (bookRes.data?.data) {
      book.value = {
        title: bookRes.data.data.title,
        author_name: bookRes.data.data.author_name || bookRes.data.data.author || 'Người viết'
      }
    }

    const res = await apiClient.get(`${apiBase}/${bookId}/chapters`)
    if (res.data?.status === 'success' && res.data.data && res.data.data.length > 0) {
      chapters.value = res.data.data
      activeChapter.value = res.data.data[0]
    } else {
      chapters.value = []
      activeChapter.value = null
    }
  } catch (e) {
    console.warn('Không tải được thông tin xem trước', e)
    error.value = e.response?.data?.message || 'Không thể kết nối API thông tin sách.'
    chapters.value = []
    activeChapter.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchBookData()
})
</script>

<template>
  <div class="device-preview h-screen flex flex-col overflow-hidden bg-slate-100">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-3 sm:px-6 py-3 flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center shrink-0 z-20">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" aria-label="Quay lại trình biên tập" class="p-button-text p-button-secondary p-button-sm min-h-11 min-w-11" @click="router.push({ name: 'vendor-live-editor', params: { bookId } })" />
        <span class="text-sm font-bold text-slate-800">Xem trước độc giả: {{ book.title }}</span>
      </div>

      <!-- Device Switcher -->
      <div class="flex max-w-full overflow-x-auto border border-slate-200 rounded-lg bg-slate-50" role="group" aria-label="Thiết bị xem trước">
        <button 
          :aria-pressed="currentDevice === 'desktop'"
          :class="['min-h-11 px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors', currentDevice === 'desktop' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'desktop'"
        >
          <i class="pi pi-desktop"></i> Desktop
        </button>
        <button 
          :aria-pressed="currentDevice === 'tablet'"
          :class="['min-h-11 px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors border-x border-slate-200', currentDevice === 'tablet' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'tablet'"
        >
          <i class="pi pi-tablet"></i> Tablet
        </button>
        <button 
          :aria-pressed="currentDevice === 'mobile'"
          :class="['min-h-11 px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors', currentDevice === 'mobile' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'mobile'"
        >
          <i class="pi pi-mobile"></i> Mobile
        </button>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <Button label="Thoát xem trước" class="p-button-outlined p-button-sm text-xs min-h-11" @click="router.push({ name: 'vendor-live-editor', params: { bookId } })" />
      </div>
    </header>

    <!-- Workspace -->
    <div class="flex flex-1 flex-col overflow-auto lg:flex-row lg:overflow-hidden">
      <!-- Main Canvas -->
      <main class="flex-1 overflow-y-auto flex items-center justify-center p-2 sm:p-8 bg-slate-200 relative">
        
        <div v-if="loading" role="status" aria-live="polite" class="flex justify-center p-12">
          <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
        </div>

        <div v-else-if="error" role="alert" class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm text-center max-w-md mx-auto space-y-4">
          <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
            <i class="pi pi-exclamation-triangle text-xl"></i>
          </div>
          <h3 class="text-base font-bold text-slate-900">Không thể tải thông tin xem trước</h3>
          <p class="text-xs text-slate-500 leading-relaxed">{{ error }}</p>
          <Button label="Thử lại" icon="pi pi-refresh" class="p-button-primary bg-indigo-600 p-button-sm text-xs" @click="fetchBookData" />
        </div>

        <template v-else>
          <!-- Desktop Mockup Frame -->
          <div
            v-if="currentDevice === 'desktop'"
            :class="[
              'w-full max-w-4xl h-[650px] rounded-xl border border-slate-300 shadow-xl flex flex-col transition-all overflow-hidden',
              readerTheme === 'light' ? 'bg-white text-slate-800' : readerTheme === 'dark' ? 'bg-slate-900 text-slate-100' : 'bg-orange-50/70 text-amber-950'
            ]"
          >
            <!-- Browser header bar -->
            <div class="bg-slate-100 border-b border-slate-200 px-4 py-2 flex items-center gap-2 shrink-0">
              <span class="w-3 h-3 rounded-full bg-rose-400"></span>
              <span class="w-3 h-3 rounded-full bg-amber-400"></span>
              <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
              <div class="bg-white border border-slate-200 rounded text-[10px] px-4 py-0.5 text-slate-400 flex-grow text-center max-w-xs mx-auto">
                https://komibook.com/reader/{{ bookId }}
              </div>
            </div>
            <!-- Reader body -->
            <div class="flex-grow p-12 overflow-y-auto font-serif">
              <template v-if="activeChapter">
                <h1 class="text-3xl font-extrabold mb-6">{{ activeChapter.title }}</h1>
                <p class="leading-relaxed whitespace-pre-line text-lg" :style="{ fontSize: fontSize + 'px' }">
                  {{ activeChapter.content }}
                </p>
              </template>
              <div v-else class="text-center py-12 text-slate-400 font-sans text-xs">
                Chưa có dữ liệu chương sách nào.
              </div>
            </div>
          </div>

          <!-- Tablet Mockup Frame -->
          <div
            v-else-if="currentDevice === 'tablet'"
            :class="[
              'w-[520px] h-[720px] rounded-[32px] border-8 border-slate-800 shadow-2xl flex flex-col transition-all overflow-hidden relative',
              readerTheme === 'light' ? 'bg-white text-slate-800' : readerTheme === 'dark' ? 'bg-slate-900 text-slate-100' : 'bg-orange-50/70 text-amber-950'
            ]"
          >
            <!-- Top camera hole -->
            <div class="absolute top-2 left-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-slate-950 z-20"></div>
            <!-- Reader body -->
            <div class="flex-grow p-8 pt-10 overflow-y-auto font-serif">
              <template v-if="activeChapter">
                <h1 class="text-2xl font-extrabold mb-4">{{ activeChapter.title }}</h1>
                <p class="leading-relaxed whitespace-pre-line text-base" :style="{ fontSize: fontSize + 'px' }">
                  {{ activeChapter.content }}
                </p>
              </template>
              <div v-else class="text-center py-12 text-slate-400 font-sans text-xs">
                Chưa có dữ liệu chương sách nào.
              </div>
            </div>
          </div>

          <!-- Mobile Mockup Frame -->
          <div
            v-else-if="currentDevice === 'mobile'"
            :class="[
              'w-[340px] h-[600px] rounded-[40px] border-[10px] border-slate-900 shadow-2xl flex flex-col transition-all overflow-hidden relative',
              readerTheme === 'light' ? 'bg-white text-slate-800' : readerTheme === 'dark' ? 'bg-slate-900 text-slate-100' : 'bg-orange-50/70 text-amber-950'
            ]"
          >
            <!-- Notch -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-28 h-4 bg-slate-900 rounded-b-xl z-20"></div>
            <!-- Reader body -->
            <div class="flex-grow p-6 pt-10 overflow-y-auto font-serif">
              <template v-if="activeChapter">
                <h1 class="text-xl font-bold mb-3">{{ activeChapter.title }}</h1>
                <p class="leading-relaxed whitespace-pre-line text-sm" :style="{ fontSize: (fontSize - 2) + 'px' }">
                  {{ activeChapter.content }}
                </p>
              </template>
              <div v-else class="text-center py-12 text-slate-400 font-sans text-xs">
                Chưa có dữ liệu chương sách nào.
              </div>
            </div>
          </div>
        </template>

      </main>

      <!-- Sidebar settings -->
      <aside class="w-full bg-white border-t border-slate-200 p-4 sm:p-6 flex flex-col gap-6 shrink-0 lg:w-72 lg:border-l lg:border-t-0">
        <div>
          <h3 class="font-bold text-slate-800 text-base mb-1">Cấu hình Đọc thử</h3>
          <p class="text-slate-400 text-xs">Giả lập các cài đặt của người đọc trên thiết bị.</p>
        </div>

        <hr class="border-slate-100" />

        <!-- Theme selector -->
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-3">Màu nền trang</label>
          <div class="grid grid-cols-3 gap-2">
            <button 
              :class="['p-2 rounded-lg border text-xs font-semibold flex flex-col items-center gap-1.5 transition-colors', readerTheme === 'light' ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 bg-white']"
              @click="readerTheme = 'light'"
            >
              <span class="w-6 h-6 rounded bg-white border border-slate-200"></span>
              Sáng
            </button>
            <button 
              :class="['p-2 rounded-lg border text-xs font-semibold flex flex-col items-center gap-1.5 transition-colors', readerTheme === 'sepia' ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 bg-white']"
              @click="readerTheme = 'sepia'"
            >
              <span class="w-6 h-6 rounded bg-orange-100 border border-amber-200"></span>
              Giấy cũ
            </button>
            <button 
              :class="['p-2 rounded-lg border text-xs font-semibold flex flex-col items-center gap-1.5 transition-colors', readerTheme === 'dark' ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 bg-white']"
              @click="readerTheme = 'dark'"
            >
              <span class="w-6 h-6 rounded bg-slate-900 border border-slate-800"></span>
              Tối
            </button>
          </div>
        </div>

        <!-- Font size -->
        <div>
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">Cỡ chữ ({{ fontSize }}px)</label>
          <div class="flex gap-2">
            <Button icon="pi pi-minus" class="p-button-outlined p-button-sm flex-grow" :disabled="fontSize <= 12" @click="fontSize -= 2" />
            <Button icon="pi pi-plus" class="p-button-outlined p-button-sm flex-grow" :disabled="fontSize >= 24" @click="fontSize += 2" />
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.device-preview {
  font-family: 'Inter', sans-serif;
}
:deep(.p-button) { min-height: 44px; }
</style>
