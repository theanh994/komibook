<script setup>
import { ref, onMounted } from 'vue'
import apiClient from '@/services/axios'

import Button from 'primevue/button'

const articles = ref([])
const loading = ref(true)
const error = ref(null)

const searchVal = ref('')
const selectedArticle = ref(null)
const detailLoading = ref(false)
const detailError = ref(null)

const helpfulMessage = ref('')
const helpfulError = ref('')

const fetchArticles = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await apiClient.get('/api/help-center/articles', {
      params: { search: searchVal.value }
    })
    if (res.data?.status === 'success') {
      articles.value = res.data.data || []
    } else if (Array.isArray(res.data)) {
      articles.value = res.data
    } else {
      articles.value = []
    }
  } catch (e) {
    console.error('Không tải được FAQs', e)
    articles.value = []
    error.value = e.response?.data?.message || 'Không thể tải danh sách bài viết trợ giúp.'
  } finally {
    loading.value = false
  }
}

const selectArticle = async (art) => {
  detailLoading.value = true
  detailError.value = null
  helpfulMessage.value = ''
  helpfulError.value = ''

  try {
    const res = await apiClient.get(`/api/help-center/articles/${art.id}`)
    if (res.data?.status === 'success') {
      selectedArticle.value = res.data.data
    } else if (res.data) {
      selectedArticle.value = res.data
    }
  } catch (e) {
    console.error('Lỗi tải chi tiết bài viết', e)
    selectedArticle.value = null
    detailError.value = e.response?.data?.message || 'Không thể tải nội dung bài viết.'
  } finally {
    detailLoading.value = false
  }
}

const rateHelpful = async (art) => {
  helpfulMessage.value = ''
  helpfulError.value = ''
  try {
    const res = await apiClient.post(`/api/help-center/articles/${art.id}/helpful`)
    if (res.data?.status === 'success' || res.status === 200) {
      art.helpful_count = (art.helpful_count || 0) + 1
      helpfulMessage.value = 'Cảm ơn bạn đã gửi phản hồi hữu ích!'
    }
  } catch (e) {
    console.error('Lỗi đánh giá bài viết', e)
    helpfulError.value = e.response?.data?.message || 'Không thể gửi đánh giá lúc này.'
  }
}

const clearArticle = () => {
  selectedArticle.value = null
  detailError.value = null
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <main class="help-center min-h-screen bg-slate-50 py-8 md:py-12 px-4 md:px-8" aria-labelledby="help-center-title">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Search Banner -->
      <div class="text-center py-12 bg-indigo-900 text-white rounded-3xl relative overflow-hidden shadow-md px-6">
        <div class="relative z-10 max-w-2xl mx-auto space-y-4">
          <h1 id="help-center-title" class="text-3xl font-black">Trung tâm Trợ giúp KomiBook</h1>
          <p class="text-indigo-200 text-sm">Tìm kiếm câu trả lời nhanh chóng cho mọi thắc mắc của bạn về tài khoản, bản quyền và in ấn.</p>
          
          <div class="flex gap-2 bg-white/10 p-1.5 rounded-full border border-indigo-700 max-w-lg mx-auto">
            <label for="help-search" class="sr-only">Tìm kiếm bài viết trợ giúp</label>
            <input 
              id="help-search"
              v-model="searchVal" 
              type="text" 
              autocomplete="off"
              placeholder="Nhập câu hỏi, từ khóa cần tìm..." 
              class="flex-grow min-w-0 min-h-11 bg-transparent text-white placeholder-indigo-300 border-none outline-none focus:ring-0 px-4 text-sm"
              @keyup.enter="fetchArticles"
            />
            <Button icon="pi pi-search" aria-label="Tìm bài viết trợ giúp" class="p-button-rounded p-button-primary bg-white text-indigo-950 p-2 border-none min-w-11 min-h-11" @click="fetchArticles" />
          </div>
        </div>
      </div>

      <!-- Detail Loading State -->
      <div v-if="detailLoading" role="status" aria-live="polite" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center space-y-3">
        <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
        <p class="text-xs text-slate-500">Đang tải bài viết...</p>
      </div>

      <!-- Detail Error State -->
      <div v-else-if="detailError" role="alert" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-4">
        <i class="pi pi-exclamation-triangle text-3xl text-rose-500"></i>
        <h3 class="text-base font-bold text-rose-800">Không thể xem bài viết</h3>
        <p class="text-xs text-rose-600 max-w-md mx-auto">{{ detailError }}</p>
        <div class="flex justify-center gap-3 pt-2">
          <Button label="Quay lại danh mục" icon="pi pi-arrow-left" class="p-button-outlined p-button-secondary p-button-sm text-xs" @click="clearArticle" />
        </div>
      </div>

      <!-- Detail View -->
      <div v-else-if="selectedArticle" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">
        <Button label="Quay lại danh mục" icon="pi pi-arrow-left" class="p-button-text p-button-sm text-xs text-indigo-600" @click="clearArticle" />
        
        <div class="space-y-4">
          <span v-if="selectedArticle.category_name" class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest block bg-indigo-50 px-2 py-0.5 rounded w-max">
            {{ selectedArticle.category_name }}
          </span>
          <h2 class="text-2xl font-extrabold text-slate-800">{{ selectedArticle.title }}</h2>
          <div class="text-slate-600 leading-relaxed text-sm whitespace-pre-line border-t border-slate-100 pt-4">
            {{ selectedArticle.content }}
          </div>
        </div>

        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs">
          <span class="text-slate-500">Bài viết này có giúp ích cho bạn không? ({{ selectedArticle.helpful_count || 0 }} lượt đánh giá)</span>
          <div class="flex gap-2 items-center">
            <Button label="Có, rất hữu ích" icon="pi pi-thumbs-up" class="p-button-outlined p-button-success p-button-sm text-xs" @click="rateHelpful(selectedArticle)" />
          </div>
        </div>

        <div v-if="helpfulMessage" role="status" aria-live="polite" class="p-3 bg-emerald-50 text-emerald-700 text-sm font-semibold rounded-lg border border-emerald-200">
          {{ helpfulMessage }}
        </div>
        <div v-if="helpfulError" role="alert" class="p-3 bg-rose-50 text-rose-700 text-sm font-semibold rounded-lg border border-rose-200">
          {{ helpfulError }}
        </div>
      </div>

      <!-- FAQ categories List -->
      <div v-else class="space-y-6">
        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
          <i class="pi pi-question-circle text-indigo-600"></i> Các câu hỏi thường gặp
        </h2>

        <div v-if="loading" role="status" aria-live="polite" aria-label="Đang tải các câu hỏi thường gặp" class="flex justify-center p-8">
          <i class="pi pi-spin pi-spinner text-2xl text-indigo-600"></i>
        </div>

        <div v-else-if="error" role="alert" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center space-y-4">
          <i class="pi pi-exclamation-triangle text-3xl text-rose-500"></i>
          <h3 class="text-base font-bold text-rose-800">Không thể tải FAQs</h3>
          <p class="text-xs text-rose-600 max-w-md mx-auto">{{ error }}</p>
          <div class="flex justify-center pt-2">
            <Button label="Thử lại" icon="pi pi-refresh" class="p-button-danger p-button-sm text-xs bg-rose-600 text-white" @click="fetchArticles" />
          </div>
        </div>

        <div v-else-if="articles.length === 0" class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400 text-xs space-y-2">
          <i class="pi pi-inbox text-3xl text-slate-300"></i>
          <p>Không tìm thấy bài viết trợ giúp nào phù hợp.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            v-for="art in articles" 
            :key="art.id" 
            type="button"
            class="w-full min-h-11 text-left bg-white p-5 rounded-2xl border border-slate-200 hover:border-indigo-400 cursor-pointer shadow-sm hover:shadow-md transition-all flex flex-col justify-between focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            @click="selectArticle(art)"
          >
            <div>
              <span v-if="art.category_name" class="text-xs font-bold text-indigo-600 uppercase tracking-widest block mb-2">{{ art.category_name }}</span>
              <h3 class="font-bold text-slate-800 text-sm leading-snug">{{ art.title }}</h3>
            </div>
            <div class="flex justify-between items-center text-xs text-slate-500 mt-4 pt-2 border-t border-slate-100">
              <span>Lượt xem: {{ art.views_count || 0 }}</span>
              <span class="text-indigo-600 font-semibold flex items-center gap-1">Xem chi tiết <i class="pi pi-angle-right"></i></span>
            </div>
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.help-center {
  font-family: 'Inter', sans-serif;
}

:deep(.p-button) {
  min-height: 44px;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
