<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/axios'

import Button from 'primevue/button'

const route = useRoute()
const router = useRouter()

const bookId = route.params.bookId
const book = ref({ title: 'Tác phẩm tự viết', author_name: 'Tác giả' })
const chapters = ref([])
const activeChapter = ref({ title: 'Chương 1', content: 'Đang tải nội dung...' })

const currentDevice = ref('mobile') // 'desktop', 'tablet', 'mobile'
const readerTheme = ref('light') // 'light', 'dark', 'sepia'
const fontSize = ref(16) // font size in px

const fetchBookData = async () => {
  try {
    const bookRes = await apiClient.get(`/api/books`)
    const b = bookRes.data?.data?.find(item => item.id == bookId)
    if (b) {
      book.value = {
        title: b.title,
        author_name: b.author_name || 'Tác giả'
      }
    }

    const res = await apiClient.get(`/api/vendor/books/${bookId}/chapters`)
    if (res.data?.status === 'success' && res.data.data.length > 0) {
      chapters.value = res.data.data
      activeChapter.value = res.data.data[0]
    } else {
      activeChapter.value = {
        title: 'Chương 1: Khởi đầu giấc mơ',
        content: 'Đây là nội dung chương sách giả định dùng để xem trước giao diện đọc sách trên nhiều thiết bị khác nhau. Bạn có thể sử dụng Trình soạn thảo để thêm nội dung thực tế cho chương này.'
      }
    }
  } catch (e) {
    console.warn('Không tải được thông tin xem trước', e)
    activeChapter.value = {
      title: 'Chương 1: Hành trình kỳ bí',
      content: 'Bóng tối bắt đầu bao trùm lên ngôi làng nhỏ ở thung lũng... Đây là văn bản xem trước đa thiết bị mô phỏng giao diện đọc thực tế.'
    }
  }
}

onMounted(() => {
  fetchBookData()
})
</script>

<template>
  <div class="device-preview h-screen flex flex-col overflow-hidden bg-slate-100">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-6 py-3 flex justify-between items-center shrink-0 z-20">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" class="p-button-text p-button-secondary p-button-sm" @click="router.push({ name: 'author-live-editor', params: { bookId } })" />
        <span class="text-sm font-bold text-slate-800">Xem trước độc giả: {{ book.title }}</span>
      </div>

      <!-- Device Switcher -->
      <div class="flex border border-slate-200 rounded-lg overflow-hidden bg-slate-50">
        <button 
          :class="['px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors', currentDevice === 'desktop' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'desktop'"
        >
          <i class="pi pi-desktop"></i> Desktop
        </button>
        <button 
          :class="['px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors border-x border-slate-200', currentDevice === 'tablet' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'tablet'"
        >
          <i class="pi pi-tablet"></i> Tablet
        </button>
        <button 
          :class="['px-4 py-2 text-xs font-bold flex items-center gap-2 transition-colors', currentDevice === 'mobile' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-150']"
          @click="currentDevice = 'mobile'"
        >
          <i class="pi pi-mobile"></i> Mobile
        </button>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <Button label="Thoát xem trước" class="p-button-outlined p-button-sm text-xs" @click="router.push({ name: 'author-live-editor', params: { bookId } })" />
      </div>
    </header>

    <!-- Workspace -->
    <div class="flex flex-1 overflow-hidden">
      <!-- Main Canvas -->
      <main class="flex-1 overflow-y-auto flex items-center justify-center p-8 bg-slate-200 relative">
        
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
            <h1 class="text-3xl font-extrabold mb-6">{{ activeChapter.title }}</h1>
            <p class="leading-relaxed whitespace-pre-line text-lg" :style="{ fontSize: fontSize + 'px' }">
              {{ activeChapter.content }}
            </p>
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
            <h1 class="text-2xl font-extrabold mb-4">{{ activeChapter.title }}</h1>
            <p class="leading-relaxed whitespace-pre-line text-base" :style="{ fontSize: fontSize + 'px' }">
              {{ activeChapter.content }}
            </p>
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
            <h1 class="text-xl font-bold mb-3">{{ activeChapter.title }}</h1>
            <p class="leading-relaxed whitespace-pre-line text-sm" :style="{ fontSize: (fontSize - 2) + 'px' }">
              {{ activeChapter.content }}
            </p>
          </div>
        </div>

      </main>

      <!-- Sidebar settings -->
      <aside class="w-72 bg-white border-l border-slate-200 p-6 flex flex-col gap-6 shrink-0">
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
</style>
