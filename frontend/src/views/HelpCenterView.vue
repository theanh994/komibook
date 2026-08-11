<script setup>
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import { useChatStore } from '@/stores/chatStore'

const chatStore = useChatStore()

const articles = ref([])
const loading = ref(true)
const error = ref(null)

const searchVal = ref('')
const selectedCategory = ref('all')
const selectedArticle = ref(null)
const detailLoading = ref(false)
const detailError = ref(null)

const helpfulMessage = ref('')
const helpfulError = ref('')

const topicCategories = [
  { id: 'all', label: 'Tất cả chủ đề', icon: 'grid_view' },
  { id: 'account', label: 'Tài khoản & Bảo mật', icon: 'manage_accounts', dbCat: 'Tài khoản & Bảo mật' },
  { id: 'orders', label: 'Đơn hàng & Vận chuyển', icon: 'local_shipping', dbCat: 'Đơn hàng & Vận chuyển' },
  { id: 'ebooks', label: 'E-book & Trình đọc', icon: 'menu_book', dbCat: 'E-book & Trình đọc' },
  { id: 'used_books', label: 'Sách cũ & Trả hàng', icon: 'cyclone', dbCat: 'Sách cũ & Trả hàng' },
  { id: 'copyright', label: 'Bản quyền & Hàng giả', icon: 'gpp_maybe', dbCat: 'Bản quyền & Hàng giả' },
  { id: 'seller', label: 'Dành cho Nhà bán', icon: 'storefront', dbCat: 'Dành cho Nhà bán' },
]

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
    console.error('Không tải được danh sách bài viết trợ giúp:', e)
    articles.value = []
    error.value = e.response?.data?.message || 'Không thể tải danh sách bài viết trợ giúp.'
  } finally {
    loading.value = false
  }
}

const filteredArticles = computed(() => {
  if (selectedCategory.value === 'all') return articles.value
  const cat = topicCategories.find(c => c.id === selectedCategory.value)
  if (!cat) return articles.value
  
  return articles.value.filter(art => {
    const artCat = (art.category_name || '').toLowerCase()
    const targetLabel = cat.label.toLowerCase()
    const targetDb = (cat.dbCat || '').toLowerCase()
    return (targetDb && artCat.includes(targetDb)) ||
           artCat.includes(targetLabel) ||
           targetLabel.includes(artCat) ||
           (art.title || '').toLowerCase().includes(cat.id)
  })
})

const selectArticle = async (art) => {
  detailLoading.value = true
  detailError.value = null
  helpfulMessage.value = ''
  helpfulError.value = ''

  try {
    const res = await apiClient.get(`/api/help-center/articles/${art.id}`)
    const fetchedArticle = res.data?.data || res.data
    if (fetchedArticle) {
      selectedArticle.value = fetchedArticle
      // Sync real incremented views_count from backend back to the list item
      if (typeof fetchedArticle.views_count === 'number') {
        art.views_count = fetchedArticle.views_count
      }
    }
  } catch (e) {
    console.error('Lỗi tải chi tiết bài viết:', e)
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
      helpfulMessage.value = 'Cảm ơn bạn đã gửi phản hồi! Đánh giá của bạn giúp KomiBook phục vụ tốt hơn.'
    }
  } catch (e) {
    console.error('Lỗi đánh giá bài viết:', e)
    helpfulError.value = e.response?.data?.message || 'Không thể gửi đánh giá lúc này.'
  }
}

const clearArticle = () => {
  selectedArticle.value = null
  detailError.value = null
  fetchArticles()
}

const selectTopicCategory = (catId) => {
  selectedCategory.value = catId
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <main class="help-center min-h-screen bg-background pb-16 pt-6" aria-labelledby="help-center-title">
    <div class="mx-auto w-full max-w-[1280px] px-4 md:px-gutter space-y-10">
      
      <!-- Search Banner Hero -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary via-primary-container to-secondary p-8 text-on-primary shadow-elevated sm:p-12 text-center">
        <div class="relative z-10 max-w-2xl mx-auto space-y-4">
          <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1 text-xs font-bold text-white uppercase tracking-wider">
            <span class="material-symbols-outlined text-base">help_center</span>
            <span>Trung tâm Trợ giúp KomiBook</span>
          </div>

          <h1 id="help-center-title" class="text-3xl font-black text-white sm:text-4xl lg:text-5xl leading-tight">
            Chúng tôi có thể giúp gì cho bạn?
          </h1>

          <p class="text-sm sm:text-base text-primary-fixed-dim leading-relaxed">
            Tra cứu nhanh chóng câu trả lời cho các thắc mắc về tài khoản, giao dịch, E-book bản quyền và chính sách bán sách cũ.
          </p>
          
          <!-- Search Input Box -->
          <div class="mt-4 flex items-center gap-2 bg-white rounded-2xl p-2 shadow-md max-w-xl mx-auto border border-outline-variant/20">
            <span class="material-symbols-outlined text-slate-400 text-2xl ml-3" aria-hidden="true">search</span>
            <input 
              id="help-search"
              v-model="searchVal" 
              type="text" 
              autocomplete="off"
              placeholder="Nhập câu hỏi hoặc từ khóa cần tìm..." 
              class="flex-grow min-w-0 bg-transparent text-slate-900 placeholder-slate-400 border-none outline-none text-sm px-2 focus:ring-0"
              @keyup.enter="fetchArticles"
            />
            <button
              type="button"
              class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-5 font-bold text-on-primary hover:bg-primary-container transition-colors shrink-0 cursor-pointer"
              aria-label="Tìm bài viết trợ giúp"
              @click="fetchArticles"
            >
              <span>Tìm kiếm</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Topic Category Filter Chips -->
      <div v-if="!selectedArticle" class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Lọc theo chủ đề quan tâm</h2>
        <div class="flex flex-wrap gap-2.5">
          <button
            v-for="cat in topicCategories"
            :key="cat.id"
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all border cursor-pointer"
            :class="selectedCategory === cat.id
              ? 'bg-primary text-on-primary border-primary shadow-xs'
              : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/30 hover:border-primary/40 hover:bg-surface-container-low'"
            @click="selectTopicCategory(cat.id)"
          >
            <span class="material-symbols-outlined text-base" aria-hidden="true">{{ cat.icon }}</span>
            <span>{{ cat.label }}</span>
          </button>
        </div>
      </div>

      <!-- Article Detail Loading State -->
      <div v-if="detailLoading" role="status" aria-live="polite" class="rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-12 text-center space-y-3 shadow-soft">
        <span class="material-symbols-outlined text-4xl text-primary animate-spin" aria-hidden="true">progress_activity</span>
        <p class="text-xs font-semibold text-on-surface-variant">Đang tải chi tiết bài viết...</p>
      </div>

      <!-- Article Detail Error State -->
      <div v-else-if="detailError" role="alert" class="rounded-3xl border border-error/30 bg-error/5 p-8 text-center space-y-4">
        <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">warning</span>
        <h3 class="text-base font-bold text-error">Không thể xem bài viết</h3>
        <p class="text-xs text-error/80 max-w-md mx-auto">{{ detailError }}</p>
        <div class="flex justify-center pt-2">
          <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container-lowest px-5 font-bold text-on-surface hover:bg-surface-container-low cursor-pointer" @click="clearArticle">
            <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            <span>Quay lại danh mục</span>
          </button>
        </div>
      </div>

      <!-- Article Detail View -->
      <article v-else-if="selectedArticle" class="rounded-3xl border border-outline-variant/30 bg-surface-container-lowest p-6 sm:p-10 shadow-soft space-y-6">
        <button
          type="button"
          class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-2 text-xs font-bold text-primary hover:bg-surface-container-high transition-colors cursor-pointer"
          @click="clearArticle"
        >
          <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_back</span>
          <span>Quay lại danh sách bài viết</span>
        </button>
        
        <div class="space-y-4 border-b border-outline-variant/15 pb-6">
          <span v-if="selectedArticle.category_name" class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary uppercase tracking-wider">
            {{ selectedArticle.category_name }}
          </span>
          <h2 class="text-2xl font-black text-on-surface sm:text-3xl">{{ selectedArticle.title }}</h2>
          <div class="flex items-center gap-4 text-xs text-on-surface-variant font-medium">
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined text-base" aria-hidden="true">visibility</span>
              {{ selectedArticle.views_count || 0 }} lượt xem
            </span>
            <span>•</span>
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined text-base text-emerald-600" aria-hidden="true">thumb_up</span>
              {{ selectedArticle.helpful_count || 0 }} lượt hữu ích
            </span>
          </div>
        </div>

        <div class="text-on-surface leading-relaxed text-sm whitespace-pre-line space-y-4">
          {{ selectedArticle.content }}
        </div>

        <!-- Rating Feedback Widget -->
        <div class="rounded-2xl border border-outline-variant/30 bg-surface-container-low p-5 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
          <span class="font-semibold text-on-surface-variant">Bài viết này có giúp ích cho bạn không? ({{ selectedArticle.helpful_count || 0 }} đánh giá)</span>
          <button
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-emerald-600 px-5 font-bold text-white shadow-2xs hover:bg-emerald-700 transition-colors cursor-pointer"
            @click="rateHelpful(selectedArticle)"
          >
            <span class="material-symbols-outlined text-lg" aria-hidden="true">thumb_up</span>
            <span>Có, rất hữu ích</span>
          </button>
        </div>

        <div v-if="helpfulMessage" role="status" aria-live="polite" class="p-4 bg-emerald-50 text-emerald-900 text-sm font-semibold rounded-2xl border border-emerald-300 flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-700 text-lg" aria-hidden="true">check_circle</span>
          {{ helpfulMessage }}
        </div>
        <div v-if="helpfulError" role="alert" class="p-4 bg-red-50 text-red-900 text-sm font-semibold rounded-2xl border border-red-300 flex items-center gap-2">
          <span class="material-symbols-outlined text-red-700 text-lg" aria-hidden="true">error</span>
          {{ helpfulError }}
        </div>
      </article>

      <!-- Main Articles Grid List -->
      <div v-else class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-black text-on-surface flex items-center gap-2">
            <span class="material-symbols-outlined text-primary" aria-hidden="true">quiz</span>
            Các bài viết trợ giúp
          </h2>
          <span class="text-xs text-on-surface-variant font-medium">Hiển thị {{ filteredArticles.length }} bài viết</span>
        </div>

        <!-- Loading State -->
        <div v-if="loading" role="status" aria-live="polite" aria-label="Đang tải các bài viết trợ giúp" class="flex flex-col items-center justify-center p-12 space-y-3">
          <span class="material-symbols-outlined text-4xl text-primary animate-spin" aria-hidden="true">progress_activity</span>
          <span class="text-xs font-semibold text-on-surface-variant">Đang tải danh sách trợ giúp...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" role="alert" class="rounded-3xl border border-error/30 bg-error/5 p-8 text-center space-y-4">
          <span class="material-symbols-outlined text-4xl text-error" aria-hidden="true">warning</span>
          <h3 class="text-base font-bold text-error">Không thể tải bài viết trợ giúp</h3>
          <p class="text-xs text-error/80 max-w-md mx-auto">{{ error }}</p>
          <div class="flex justify-center pt-2">
            <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-error px-5 font-bold text-on-error hover:bg-error/90 cursor-pointer" @click="fetchArticles">
              <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
              <span>Thử lại</span>
            </button>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredArticles.length === 0" class="rounded-3xl border border-dashed border-outline-variant/40 bg-surface-container-lowest p-10 text-center space-y-2">
          <span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">inbox</span>
          <h3 class="text-base font-bold text-on-surface">Không tìm thấy bài viết nào</h3>
          <p class="text-xs text-on-surface-variant">Thử chọn từ khóa hoặc chủ đề khác.</p>
        </div>

        <!-- Grid List -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <button
            v-for="art in filteredArticles" 
            :key="art.id" 
            type="button"
            class="group w-full min-h-11 text-left bg-surface-container-lowest p-6 rounded-3xl border border-outline-variant/30 hover:border-primary/40 cursor-pointer shadow-soft hover:-translate-y-1 hover:shadow-md transition-all duration-200 flex flex-col justify-between"
            @click="selectArticle(art)"
          >
            <div class="space-y-2">
              <span v-if="art.category_name" class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-bold text-primary uppercase tracking-wider">
                {{ art.category_name }}
              </span>
              <h3 class="font-bold text-on-surface text-base group-hover:text-primary transition-colors leading-snug">{{ art.title }}</h3>
            </div>

            <div class="flex justify-between items-center text-xs text-on-surface-variant mt-6 pt-3 border-t border-outline-variant/15">
              <span class="flex items-center gap-1 font-medium">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">visibility</span>
                {{ art.views_count || 0 }} lượt xem
              </span>
              <span class="text-primary font-bold inline-flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                <span>Xem chi tiết</span>
                <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
              </span>
            </div>
          </button>
        </div>
      </div>

      <!-- Direct Support & AI Assistant CTA Card -->
      <section class="rounded-3xl bg-gradient-to-r from-surface-container-low to-surface-container-lowest border border-outline-variant/30 p-8 shadow-soft">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary shrink-0">
              <span class="material-symbols-outlined text-3xl" aria-hidden="true">support_agent</span>
            </div>
            <div>
              <h3 class="font-bold text-on-surface text-lg">Vẫn chưa tìm thấy câu trả lời?</h3>
              <p class="text-xs text-on-surface-variant leading-relaxed mt-0.5">
                Trò chuyện trực tiếp với Trợ lý AI KomiBook hoặc gửi thắc mắc tới đội ngũ hỗ trợ để nhận phản hồi nhanh nhất.
              </p>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-on-primary shadow-xs transition hover:bg-primary-container shrink-0 cursor-pointer"
            @click="chatStore.openConversationList()"
          >
            <span class="material-symbols-outlined" aria-hidden="true">forum</span>
            <span>Mở Cửa Sổ Trợ Lý & Hỗ Trợ</span>
          </button>
        </div>
      </section>

    </div>
  </main>
</template>

<style scoped>
button:not([tabindex="-1"]) {
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
