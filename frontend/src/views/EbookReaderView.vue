<template>
  <div 
    class="min-h-screen w-full flex flex-col transition-colors duration-300" 
    :class="themeClasses[currentTheme].bg"
    @contextmenu.prevent
  >
    <!-- Top Bar -->
    <div 
      class="h-16 flex items-center justify-between px-4 sm:px-6 z-40 fixed top-0 w-full transition-colors duration-300 border-b"
      :class="themeClasses[currentTheme].topbar"
    >
      <div class="flex items-center gap-2">
        <Button 
          icon="pi pi-list" 
          text 
          rounded
          :class="themeClasses[currentTheme].btn" 
          @click="showToc = true"
          v-tooltip.bottom="'Mục lục'"
        />
        <Button 
          icon="pi pi-arrow-left" 
          label="Quay lại" 
          text 
          :class="[themeClasses[currentTheme].btn, 'hidden sm:flex']" 
          @click="$router.push('/my-library')" 
        />
      </div>
      
      <div class="font-bold truncate max-w-xs sm:max-w-md transition-colors" :class="themeClasses[currentTheme].text">
        Trình Đọc E-book
      </div>
      
      <div class="flex items-center gap-1 sm:gap-2">
        <Button icon="pi pi-search-minus" text rounded :class="themeClasses[currentTheme].btn" @click="zoomOut" :disabled="scale <= 0.5" />
        <span class="text-sm font-medium w-10 sm:w-12 text-center transition-colors" :class="themeClasses[currentTheme].text">{{ Math.round(scale * 100) }}%</span>
        <Button icon="pi pi-search-plus" text rounded :class="themeClasses[currentTheme].btn" @click="zoomIn" :disabled="scale >= 3" />
        <Button icon="pi pi-cog" text rounded :class="themeClasses[currentTheme].btn" @click="showSettings = true" v-tooltip.bottom="'Giao diện'" />
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col items-center pt-24 pb-10 px-2 sm:px-4 w-full select-none overflow-y-auto h-screen relative" ref="scrollContainer">
      <!-- Loading State -->
      <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center bg-transparent z-20" :class="themeClasses[currentTheme].bg">
        <i class="pi pi-spin pi-spinner text-5xl text-primary mb-4"></i>
        <p class="font-medium transition-colors" :class="themeClasses[currentTheme].text">Đang tải nội dung sách...</p>
      </div>
      
      <!-- Error State -->
      <div v-if="error" class="absolute inset-0 flex flex-col items-center justify-center z-30" :class="themeClasses[currentTheme].bg">
        <i class="pi pi-exclamation-triangle text-5xl text-red-500 mb-4"></i>
        <p class="font-bold text-xl mb-2 transition-colors" :class="themeClasses[currentTheme].text">Không thể tải sách</p>
        <p class="mb-6 max-w-md text-center opacity-70 transition-colors" :class="themeClasses[currentTheme].text">{{ error }}</p>
        <Button label="Quay lại tủ sách" icon="pi pi-arrow-left" @click="$router.push('/my-library')" />
      </div>

      <!-- PDF Container -->
      <div 
        class="w-full max-w-3xl flex flex-col items-center transition-opacity duration-300" 
        :class="{ 'opacity-0': loading || error, 'opacity-100': !loading && !error }"
      >
        <!-- Document Page -->
        <div 
          class="w-full transition-transform duration-200 origin-top flex justify-center" 
          :style="{ transform: `scale(${scale})` }"
        >
          <VuePdfEmbed 
            ref="pdfRef"
            v-if="pdfUrl"
            :source="pdfUrl" 
            :page="currentPage"
            :text-layer="false"
            :annotation-layer="false"
            @loaded="onPdfLoaded"
            @rendered="onPdfRendered"
            class="pdf-container pointer-events-none w-full" 
            :style="{ filter: themeClasses[currentTheme].filter }"
          />
        </div>

        <!-- Navigation Buttons at the bottom of the page -->
        <div 
          v-if="totalPages > 0 && !loading" 
          class="w-full mt-20 mb-8 flex flex-col sm:flex-row items-center gap-4 transition-all"
          :style="{ marginTop: `${Math.max(20, (scale - 1) * 100 + 40)}px` }"
        >
          <button 
            @click="prevPage" 
            :disabled="currentPage <= 1"
            class="w-full sm:flex-1 py-4 px-6 rounded-xl border border-opacity-20 transition-all duration-200 flex items-center justify-center gap-2 font-medium disabled:opacity-30 disabled:cursor-not-allowed"
            :class="themeClasses[currentTheme].navBtn"
          >
            <i class="pi pi-arrow-left"></i>
            Trang trước
          </button>
          
          <!-- Quick Page input -->
          <div class="flex items-center justify-center gap-2 px-4 py-2 opacity-70 transition-colors" :class="themeClasses[currentTheme].text">
            <input 
              type="number" 
              v-model.number="inputPage"
              @keyup.enter="jumpToPage"
              @blur="jumpToPage"
              class="w-16 h-10 bg-transparent text-center rounded border border-opacity-30 focus:outline-none focus:border-primary font-bold"
              :class="themeClasses[currentTheme].input"
              min="1"
              :max="totalPages"
            />
            <span>/ {{ totalPages }}</span>
          </div>

          <button 
            @click="nextPage" 
            :disabled="currentPage >= totalPages"
            class="w-full sm:flex-1 py-4 px-6 rounded-xl border border-opacity-20 transition-all duration-200 flex items-center justify-center gap-2 font-medium disabled:opacity-30 disabled:cursor-not-allowed"
            :class="themeClasses[currentTheme].navBtn"
          >
            Trang tiếp theo
            <i class="pi pi-arrow-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Sidebar / Drawer Mục lục -->
    <Drawer v-model:visible="showToc" header="Mục lục" :class="themeClasses[currentTheme].drawer" class="w-full sm:w-80">
      <div v-if="!outline || outline.length === 0" class="text-center mt-10 opacity-70">
        <i class="pi pi-folder-open text-4xl mb-4 block"></i>
        Tài liệu này không có mục lục.
      </div>
      <div v-else class="flex flex-col gap-1">
        <div 
          v-for="(item, index) in outline" 
          :key="index"
          class="px-3 py-2 rounded cursor-pointer transition-colors"
          :class="themeClasses[currentTheme].hoverItem"
          @click="goToTocItem(item)"
        >
          <div class="truncate" :title="item.title">{{ item.title }}</div>
        </div>
      </div>
    </Drawer>

    <!-- Settings Drawer / Dialog -->
    <Drawer v-model:visible="showSettings" position="right" header="Cài đặt Giao diện" :class="themeClasses[currentTheme].drawer" class="w-full sm:w-80">
      <div class="flex flex-col gap-6 mt-4">
        <div>
          <h3 class="font-semibold mb-3 opacity-80">Chế độ Đọc (Theme)</h3>
          <div class="flex gap-3">
            <button 
              class="flex-1 py-3 rounded-lg border-2 transition-all flex flex-col items-center gap-2"
              :class="[currentTheme === 'light' ? 'border-primary' : 'border-transparent']"
              style="background-color: #f4f5f6; color: #1f2937;"
              @click="currentTheme = 'light'"
            >
              <i class="pi pi-sun"></i>
              <span>Sáng</span>
            </button>
            <button 
              class="flex-1 py-3 rounded-lg border-2 transition-all flex flex-col items-center gap-2"
              :class="[currentTheme === 'sepia' ? 'border-primary' : 'border-transparent']"
              style="background-color: #f4ecd8; color: #5b4636;"
              @click="currentTheme = 'sepia'"
            >
              <i class="pi pi-book"></i>
              <span>Giấy vàng</span>
            </button>
            <button 
              class="flex-1 py-3 rounded-lg border-2 transition-all flex flex-col items-center gap-2"
              :class="[currentTheme === 'dark' ? 'border-primary' : 'border-transparent']"
              style="background-color: #222222; color: #e5e7eb;"
              @click="currentTheme = 'dark'"
            >
              <i class="pi pi-moon"></i>
              <span>Tối</span>
            </button>
          </div>
        </div>
      </div>
    </Drawer>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import apiClient from '@/services/axios'
import VuePdfEmbed from 'vue-pdf-embed'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(true)
const pdfUrl = ref(null)
const error = ref(null)
const scale = ref(1.0)
const scrollContainer = ref(null)

// Navigation state
const currentPage = ref(1)
const totalPages = ref(0)
const inputPage = ref(1)

// TOC & Settings state
const showToc = ref(false)
const showSettings = ref(false)
const outline = ref([])
const pdfRef = ref(null)
const pdfDocument = ref(null)

// Theme Management
const currentTheme = ref(localStorage.getItem('readerTheme') || 'light')

watch(currentTheme, (newTheme) => {
  localStorage.setItem('readerTheme', newTheme)
})

const themeClasses = {
  light: {
    bg: 'bg-[#f4f5f6]',
    topbar: 'bg-white/90 border-gray-200',
    btn: 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/50',
    text: 'text-gray-800',
    filter: 'none',
    navBtn: 'border-gray-300 text-gray-700 hover:bg-gray-200/50 bg-white/50',
    input: 'border-gray-400',
    drawer: '!bg-[#f4f5f6] !text-gray-800 !border-gray-200',
    hoverItem: 'hover:bg-gray-200/50'
  },
  sepia: {
    bg: 'bg-[#f4ecd8]',
    topbar: 'bg-[#f4ecd8]/95 border-[#e0d6bd]',
    btn: 'text-[#5b4636] hover:text-[#3d2e24] hover:bg-[#e0d6bd]/50',
    text: 'text-[#5b4636]',
    filter: 'sepia(0.4) contrast(0.9) brightness(0.95)',
    navBtn: 'border-[#d0c6ac] text-[#5b4636] hover:bg-[#e6dcc6] bg-[#f4ecd8]/50',
    input: 'border-[#c0b69c]',
    drawer: '!bg-[#f4ecd8] !text-[#5b4636] !border-[#e0d6bd]',
    hoverItem: 'hover:bg-[#e0d6bd]/50'
  },
  dark: {
    bg: 'bg-[#222222]',
    topbar: 'bg-[#1a1a1a]/95 border-[#333333]',
    btn: 'text-gray-400 hover:text-white hover:bg-white/10',
    text: 'text-gray-300',
    filter: 'invert(0.9) hue-rotate(180deg) contrast(0.85) brightness(0.85)',
    navBtn: 'border-gray-700 text-gray-300 hover:bg-white/10 bg-black/20',
    input: 'border-gray-600 text-white',
    drawer: '!bg-[#222222] !text-gray-300 !border-[#333333]',
    hoverItem: 'hover:bg-white/10'
  }
}

const zoomIn = () => {
  if (scale.value < 3) scale.value += 0.2
}

const zoomOut = () => {
  if (scale.value > 0.5) scale.value -= 0.2
}

const scrollToTop = () => {
  if (scrollContainer.value) {
    scrollContainer.value.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    loading.value = true
    currentPage.value--
    inputPage.value = currentPage.value
    scrollToTop()
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    loading.value = true
    currentPage.value++
    inputPage.value = currentPage.value
    scrollToTop()
  }
}

const jumpToPage = () => {
  let p = parseInt(inputPage.value)
  if (isNaN(p) || p === currentPage.value) {
    inputPage.value = currentPage.value
    return
  }
  if (p < 1) p = 1
  if (p > totalPages.value) p = totalPages.value
  
  loading.value = true
  currentPage.value = p
  inputPage.value = p
  scrollToTop()
}

const fetchEbookUrl = async () => {
  const { orderId, bookId } = route.params
  
  try {
    const response = await apiClient.get(`/api/orders/${orderId}/ebooks/${bookId}/generate-link`)
    pdfUrl.value = response.data.url
  } catch (err) {
    console.error('Lỗi lấy link sách:', err)
    error.value = err.response?.data?.message || 'Có lỗi xảy ra khi xác thực sách. Vui lòng thử lại.'
    loading.value = false
    toast.add({
      severity: 'error',
      summary: 'Lỗi bản quyền',
      detail: error.value,
      life: 5000
    })
  }
}

const onPdfLoaded = async (pdfApp) => {
  pdfDocument.value = pdfRef.value?.doc || pdfApp
  
  if (pdfDocument.value) {
    totalPages.value = pdfDocument.value.numPages
    
    try {
      const toc = await pdfDocument.value.getOutline()
      outline.value = toc || []
    } catch (e) {
      console.warn('Không thể lấy mục lục:', e)
    }
  }
}

const onPdfRendered = () => {
  loading.value = false
}

const goToTocItem = async (item) => {
  showToc.value = false
  if (item.dest && pdfDocument.value) {
    try {
      let destArray = item.dest
      if (typeof item.dest === 'string') {
        destArray = await pdfDocument.value.getDestination(item.dest)
      }
      if (destArray && destArray[0]) {
        const pageIndex = await pdfDocument.value.getPageIndex(destArray[0])
        const p = pageIndex + 1
        if (p !== currentPage.value) {
          loading.value = true
          currentPage.value = p
          inputPage.value = p
          scrollToTop()
        }
      }
    } catch (e) {
      console.error('Lỗi chuyển trang từ mục lục:', e)
    }
  }
}

onMounted(() => {
  document.body.classList.add('overflow-hidden')
  fetchEbookUrl()
})

onUnmounted(() => {
  document.body.classList.remove('overflow-hidden')
})
</script>

<style scoped>
.select-none {
  user-select: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
}

/* Khóa chuột hoàn toàn trên khung PDF */
.pdf-container {
  pointer-events: none !important;
}

/* Loại bỏ scrollbar thô kệch */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.8);
}


</style>
