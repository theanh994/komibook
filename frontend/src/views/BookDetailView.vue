<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-40">
       <div class="absolute top-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/10 blur-[120px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[500px] h-[500px] bg-secondary/10 blur-[100px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl relative z-10">

      <!-- Premium Breadcrumb -->
      <nav class="mb-xl flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.2em] text-outline/60 animate-fade-in">
        <router-link to="/" class="hover:text-primary transition-all flex items-center gap-1 group">
          <span class="material-symbols-outlined text-[20px] group-hover:scale-110 transition-transform">home</span>
        </router-link>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <router-link to="/catalog" class="hover:text-primary transition-all">Danh mục</router-link>
        <template v-if="book?.category">
          <span class="material-symbols-outlined text-[14px]">chevron_right</span>
          <span class="text-primary">{{ book.category.name }}</span>
        </template>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <span class="text-on-surface truncate max-w-[200px] opacity-100">{{ book?.title || '...' }}</span>
      </nav>

      <!-- Loading State (MD3 Shimmer) -->
      <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-12 gap-xxl">
        <div class="lg:col-span-5 aspect-[3/4] bg-surface-container-low rounded-[40px] animate-pulse"></div>
        <div class="lg:col-span-7 space-y-8">
           <div class="h-16 w-3/4 bg-surface-container-low rounded-2xl animate-pulse"></div>
           <div class="h-8 w-1/4 bg-surface-container-low rounded-full animate-pulse"></div>
           <div class="space-y-4">
              <div class="h-4 w-full bg-surface-container-low rounded animate-pulse"></div>
              <div class="h-4 w-full bg-surface-container-low rounded animate-pulse"></div>
              <div class="h-4 w-2/3 bg-surface-container-low rounded animate-pulse"></div>
           </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="!book" class="flex flex-col items-center justify-center py-32 bg-surface-container-lowest rounded-[48px] shadow-2xl border border-outline-variant/10 text-center animate-fade-in">
        <div class="w-24 h-24 bg-error/10 rounded-full flex items-center justify-center mb-8">
           <span class="material-symbols-outlined text-[56px] text-error">sentiment_dissatisfied</span>
        </div>
        <h2 class="text-3xl font-bold text-on-surface mb-4 tracking-tight">Tác phẩm chưa xuất hiện</h2>
        <p class="text-on-surface-variant mb-10 max-w-md mx-auto font-medium leading-relaxed">Có thể sách đã được ẩn hoặc chuyển đến một không gian khác.</p>
        <router-link to="/" class="bg-primary text-on-primary px-10 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
          Khám phá sách khác
        </router-link>
      </div>

      <!-- ═══ MAIN CONTENT (PREMIUM) ═══ -->
      <div v-else class="animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-xxl items-start">
          
          <!-- ─── LEFT COLUMN: COVER & INTERACTIVE ─── -->
          <div class="lg:col-span-5 sticky top-xl">
            <div class="perspective-1000 group">
              <div class="relative transform-gpu transition-all duration-700 ease-out preserve-3d group-hover:rotate-y-12 group-hover:scale-[1.02]">
                <!-- Main Cover -->
                <div class="aspect-[3/4.5] bg-surface-container-low rounded-[40px] overflow-hidden shadow-[0_60px_120px_rgba(0,0,0,0.15)] border border-outline-variant/10 relative z-20">
                  <img v-if="book.cover_image" :src="getCoverUrl(book.cover_image)" :alt="book.title" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-outline/20">
                    <span class="material-symbols-outlined text-[120px]">menu_book</span>
                  </div>
                  
                  <!-- Sale Overlay Badge -->
                  <div v-if="book.sale_price && book.price > book.sale_price" class="absolute top-8 right-8 bg-error text-on-error text-xs font-bold px-5 py-2.5 rounded-2xl shadow-2xl z-30 transform rotate-12 scale-110">
                    GIẢM {{ Math.round((1 - book.sale_price / book.price) * 100) }}%
                  </div>


                </div>

                <!-- Spine detail -->
                <div class="absolute inset-y-0 left-0 w-12 bg-black/10 blur-xl rounded-l-[40px] pointer-events-none z-30"></div>
              </div>
            </div>
            
            <!-- Floating Quick Stats -->
            <div class="mt-12 grid grid-cols-3 gap-6 animate-slide-up">
              <div v-for="stat in quickStats" :key="stat.label" class="bg-surface-container-lowest/60 backdrop-blur-md p-6 rounded-[32px] border border-outline-variant/10 text-center hover:border-primary/30 transition-all group">
                <span class="material-symbols-outlined text-primary mb-3 group-hover:scale-110 transition-transform">{{ stat.icon }}</span>
                <div class="text-xl font-bold text-on-surface tracking-tighter">{{ stat.value }}</div>
                <div class="text-[9px] uppercase tracking-[0.2em] text-outline font-bold mt-1 opacity-50">{{ stat.label }}</div>
              </div>
            </div>
          </div>

          <!-- ─── RIGHT COLUMN: RICH DETAILS ─── -->
          <div class="lg:col-span-7 flex flex-col gap-12">
            
            <!-- Core Info Section -->
            <div class="bg-surface-container-lowest/80 backdrop-blur-xl rounded-[48px] shadow-sm p-10 md:p-14 border border-outline-variant/10 animate-slide-up">
              <div class="flex flex-wrap gap-3 mb-8">
                <span v-if="book.category" class="px-5 py-2 bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-[0.2em] rounded-full border border-primary/20">
                  {{ book.category.name }}
                </span>
                <span class="px-5 py-2 bg-secondary/10 text-secondary text-[10px] font-bold uppercase tracking-[0.2em] rounded-full border border-secondary/20">
                  Phát hành 2024
                </span>
              </div>
              
              <h1 class="text-5xl md:text-7xl font-bold text-on-surface mb-6 leading-[0.9] tracking-tighter">{{ book.title }}</h1>
              
              <div class="flex flex-wrap items-center gap-8 mb-12">
                <div class="flex items-center gap-2">
                   <div class="flex">
                      <span v-for="i in 5" :key="i" class="material-symbols-outlined text-2xl" :style="{ 'font-variation-settings': i <= averageRating ? `'FILL' 1` : `'FILL' 0`, color: i <= averageRating ? '#ba0035' : '#c3c6ce' }">star</span>
                   </div>
                   <span class="text-lg font-bold text-on-surface tracking-tighter">{{ averageRating }}.0 / 5.0</span>
                </div>
                <div class="h-6 w-px bg-outline-variant/30 hidden md:block"></div>
                <div class="flex items-center gap-3">
                   <div class="w-12 h-12 rounded-2xl bg-surface-container-high flex items-center justify-center text-primary font-bold">
                      {{ book.author?.charAt(0) }}
                   </div>
                   <div>
                      <p class="text-[10px] font-bold uppercase tracking-widest text-outline opacity-40">Tác giả</p>
                      <p class="text-xl font-bold text-on-surface tracking-tight">{{ book.author }}</p>
                   </div>
                </div>
              </div>

              <!-- Price & CTA -->
              <div class="bg-surface-container-low/40 rounded-[40px] p-10 border border-outline-variant/10 mb-12">
                 <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                    <div class="text-center md:text-left">
                       <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-primary mb-2">Giá niêm yết</p>
                       <div class="flex items-center gap-4">
                          <span class="text-5xl font-bold text-primary tracking-tighter">{{ formatCurrency(book.sale_price || book.price) }}</span>
                          <span v-if="book.sale_price && book.price > book.sale_price" class="text-2xl text-outline/40 line-through font-bold">{{ formatCurrency(book.price) }}</span>
                       </div>
                    </div>

                    <div class="flex flex-col gap-3 w-full md:w-auto">
                       <!-- Ownership Logic -->
                       <template v-if="book.type === 'ebook' && ownershipData.owned">
                          <button @click="goToReader" class="bg-on-surface text-surface px-12 py-5 rounded-[24px] font-bold text-xs uppercase tracking-[0.2em] shadow-2xl hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-4">
                            <span class="material-symbols-outlined text-[24px] fill-1">auto_stories</span>
                            Đọc Sách Ngay
                          </button>
                          <div class="flex items-center justify-between px-4">
                             <span class="text-[9px] font-bold uppercase tracking-widest text-outline">Đã đọc {{ readingProgress }}%</span>
                             <div class="w-24 h-1 bg-surface-container-highest rounded-full overflow-hidden">
                                <div class="h-full bg-primary" :style="{ width: readingProgress + '%' }"></div>
                             </div>
                          </div>
                       </template>
                       <template v-else>
                          <div class="flex flex-col sm:flex-row gap-4 items-center">
                             <button @click="addToCart" class="px-8 py-5 rounded-[24px] border-2 border-primary text-primary font-bold text-xs uppercase tracking-[0.2em] hover:bg-primary/5 transition-all flex items-center justify-center gap-3">
                               <span class="material-symbols-outlined text-[24px]">shopping_bag</span>
                               Giỏ hàng
                             </button>
                             <button @click="buyNow" class="bg-primary text-on-primary px-12 py-5 rounded-[24px] font-bold text-xs uppercase tracking-[0.2em] shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
                               Mua ngay
                             </button>
                             <!-- Wishlist Toggle -->
                             <button @click="toggleWishlist" class="w-16 h-16 rounded-[24px] border-2 border-outline-variant/30 flex items-center justify-center hover:bg-surface-container-high transition-all group/heart shadow-sm">
                               <span class="material-symbols-outlined text-[28px] transition-all" :class="wishlistStore.isFavorite(book?.id) ? 'text-error fill-1 scale-110' : 'text-outline group-hover/heart:text-error'">favorite</span>
                             </button>
                          </div>
                       </template>
                    </div>
                 </div>
              </div>

              <!-- Metadata Grid -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12 py-10 border-y border-outline-variant/10">
                <div v-for="meta in bookMeta" :key="meta.label" class="space-y-2">
                   <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-outline opacity-40">{{ meta.label }}</p>
                   <p class="text-base font-bold text-on-surface tracking-tight">{{ meta.value }}</p>
                </div>
              </div>

              <!-- Description -->
              <div class="prose max-w-none">
                <div class="flex items-center gap-4 mb-8">
                   <div class="w-1.5 h-8 bg-primary rounded-full"></div>
                   <h3 class="text-3xl font-bold text-on-surface tracking-tighter">Hành trình tâm hồn</h3>
                </div>
                <p class="font-inter text-xl text-on-surface-variant leading-relaxed text-justify opacity-80 first-letter:text-6xl first-letter:font-bold first-letter:text-primary first-letter:mr-4 first-letter:float-left first-letter:leading-[1]">
                  {{ book.description }}
                </p>
              </div>

              <!-- Ebook Chapters Preview Section -->
              <div v-if="book.type === 'ebook'" class="mt-12 bg-surface-container-lowest/80 backdrop-blur-xl rounded-[40px] p-8 border border-outline-variant/10">
                <div class="flex items-center gap-4 mb-8">
                   <div class="w-1.5 h-8 bg-primary rounded-full"></div>
                   <h3 class="text-3xl font-bold text-on-surface tracking-tighter">Mục lục & Đọc thử</h3>
                </div>

                <div v-if="book.chapters && book.chapters.length > 0" class="space-y-3">
                  <div 
                    v-for="chapter in book.chapters.sort((a,b) => a.order - b.order)" 
                    :key="chapter.id"
                    class="flex items-center justify-between p-4 rounded-2xl border border-outline-variant/20 hover:border-primary/20 transition-all bg-surface-container-low/20"
                  >
                    <div class="flex items-center gap-3">
                      <span class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">
                        {{ chapter.order }}
                      </span>
                      <span class="text-sm font-bold text-on-surface">{{ chapter.title }}</span>
                    </div>

                    <div>
                      <button 
                        v-if="chapter.is_free" 
                        @click="openPreviewChapter(chapter)"
                        class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-600 hover:text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer"
                      >
                        Đọc thử
                      </button>
                      <div v-else class="flex items-center gap-1.5 text-xs text-outline opacity-60 font-medium">
                        <span class="material-symbols-outlined text-[16px]">lock</span>
                        Khóa
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="text-center py-10 bg-surface-container-low/40 rounded-2xl border-2 border-dashed border-outline-variant/20">
                  <span class="material-symbols-outlined text-4xl text-outline/20 mb-3">lock_open</span>
                  <p class="text-sm text-on-surface-variant font-medium opacity-60">Cuốn e-book này chưa cấu hình chương đọc thử.</p>
                </div>
              </div>
            </div>

            <!-- Enhanced Reviews Section -->
            <section class="bg-surface-container-lowest/80 backdrop-blur-xl rounded-[48px] shadow-sm p-10 md:p-14 border border-outline-variant/10 animate-slide-up">
              <header class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-16">
                <div>
                  <h3 class="text-3xl font-bold text-on-surface tracking-tight mb-2 flex items-center gap-4">
                    <span class="material-symbols-outlined text-secondary text-4xl">forum_heart</span>
                    Cảm nhận độc giả
                  </h3>
                  <p class="text-on-surface-variant font-medium opacity-60">Kết nối cùng hàng ngàn trái tim yêu sách.</p>
                </div>
                <button @click="showReviewModal = true" class="bg-surface-container-high text-primary px-10 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest hover:bg-primary hover:text-on-primary transition-all shadow-sm">
                  Viết chia sẻ
                </button>
              </header>

              <!-- Review List -->
              <div v-if="book.reviews?.length > 0" class="space-y-12">
                <article v-for="review in book.reviews" :key="review.id" class="flex gap-8 group animate-fade-in">
                  <div class="shrink-0">
                     <div class="w-16 h-16 rounded-[22px] bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center text-primary font-bold text-2xl shadow-inner border border-outline-variant/20">
                       {{ review.user?.name?.charAt(0) || 'U' }}
                     </div>
                  </div>
                  <div class="flex-1 space-y-4">
                    <div class="flex justify-between items-center">
                      <div>
                         <h4 class="font-bold text-on-surface text-lg tracking-tight">{{ review.user?.name || 'Độc giả ẩn danh' }}</h4>
                         <div class="flex items-center gap-3 mt-1">
                            <div class="flex">
                              <span v-for="i in 5" :key="i" class="material-symbols-outlined text-[16px]" :style="{ 'font-variation-settings': i <= review.rating ? `'FILL' 1` : `'FILL' 0`, color: i <= review.rating ? '#ba0035' : '#c3c6ce' }">star</span>
                            </div>
                            <span class="text-[10px] font-bold text-outline uppercase tracking-widest">{{ formatDate(review.created_at) }}</span>
                         </div>
                      </div>
                    </div>
                    <p class="text-lg text-on-surface-variant leading-relaxed opacity-90">{{ review.comment }}</p>
                    <div class="flex gap-6 pt-2">
                       <button class="flex items-center gap-2 text-[11px] font-bold text-outline uppercase tracking-widest hover:text-primary transition-all">
                          <span class="material-symbols-outlined text-[18px]">thumb_up</span>
                          Hữu ích (12)
                       </button>
                       <button class="flex items-center gap-2 text-[11px] font-bold text-outline uppercase tracking-widest hover:text-primary transition-all">
                          <span class="material-symbols-outlined text-[18px]">reply</span>
                          Phản hồi
                       </button>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else class="text-center py-24 bg-surface-container-low/40 rounded-[40px] border-2 border-dashed border-outline-variant/20">
                <span class="material-symbols-outlined text-7xl text-outline/20 mb-8">rate_review</span>
                <h4 class="text-xl font-bold text-on-surface mb-2">Chưa có lời bộc bạch nào</h4>
                <p class="text-on-surface-variant font-medium opacity-60 max-w-xs mx-auto">Hãy là người đầu tiên chia sẻ cảm nhận về kiệt tác này.</p>
              </div>
            </section>

            <!-- Series Carousel (Premium) -->
            <section v-if="seriesBooks.length > 0" class="animate-slide-up">
              <div class="flex items-center justify-between mb-10">
                <div class="flex items-center gap-4">
                   <div class="w-1.5 h-8 bg-secondary rounded-full"></div>
                   <h3 class="text-3xl font-bold text-on-surface tracking-tighter">Trọn bộ tuyệt phẩm</h3>
                </div>
              </div>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-8">
                <div v-for="sb in seriesBooks" :key="sb.id" class="group cursor-pointer">
                  <div @click="$router.push({ name: 'book-detail', params: { slug: sb.slug } })" class="aspect-[2/3.2] rounded-[28px] overflow-hidden shadow-lg mb-6 relative">
                    <img :src="sb.cover_image" class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110 group-hover:rotate-1" />
                    <div class="absolute inset-0 bg-gradient-to-t from-primary/80 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 flex items-end p-6">
                       <span class="bg-white text-primary px-6 py-2.5 rounded-2xl text-[10px] font-bold uppercase tracking-widest shadow-2xl w-full text-center">Khám phá</span>
                    </div>
                  </div>
                  <h4 class="text-base font-bold text-on-surface line-clamp-1 group-hover:text-primary transition-colors tracking-tight">{{ sb.title }}</h4>
                  <div class="text-sm font-bold text-primary mt-2">{{ formatCurrency(sb.sale_price || sb.price) }}</div>
                </div>
              </div>
            </section>

          </div>
        </div>
      </div>

    </div>

    <!-- Review Modal (MD3 Dialog) -->
    <Dialog v-model:visible="showReviewModal" header="Viết chia sẻ" :modal="true" class="!max-w-2xl !w-[90vw] !rounded-[40px] !bg-surface-container-lowest overflow-hidden">
      <div class="flex flex-col gap-10 py-8 px-4">
        <div class="text-center">
          <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-primary mb-6">Bạn cảm thấy tác phẩm thế nào?</p>
          <div class="flex items-center justify-center gap-4">
            <button v-for="i in 5" :key="i" @click="reviewForm.rating = i" class="group relative">
              <span class="material-symbols-outlined text-[56px] transition-all duration-300" :style="{ 'font-variation-settings': i <= reviewForm.rating ? `'FILL' 1` : `'FILL' 0`, color: i <= reviewForm.rating ? '#ba0035' : '#e2e2e2' }" :class="i <= reviewForm.rating ? 'scale-110' : 'hover:scale-105'">star</span>
              <div v-if="i === reviewForm.rating" class="absolute -bottom-2 left-1/2 -translate-x-1/2 w-1.5 h-1.5 bg-primary rounded-full"></div>
            </button>
          </div>
        </div>
        
        <div class="space-y-4">
          <label class="text-xs font-bold uppercase tracking-widest text-outline">Suy tư của bạn</label>
          <Textarea v-model="reviewForm.comment" rows="5" class="!w-full !p-8 !bg-surface-container-low !border-none !rounded-[32px] !font-medium !text-lg focus:!ring-4 focus:!ring-primary/5 transition-all" placeholder="Những trang sách đã chạm đến trái tim bạn như thế nào?..." />
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
          <button @click="showReviewModal = false" class="flex-1 py-5 rounded-[22px] bg-surface-container-high text-on-surface-variant font-bold text-xs uppercase tracking-widest transition-all">Gác bút (Hủy)</button>
          <button @click="submitReview" :disabled="isSubmittingReview" class="flex-[2] bg-primary text-on-primary py-5 rounded-[22px] font-bold text-xs uppercase tracking-widest shadow-xl shadow-primary/20 flex items-center justify-center gap-3 active:scale-95 transition-all">
            <span v-if="isSubmittingReview" class="material-symbols-outlined animate-spin">progress_activity</span>
            <span v-else class="material-symbols-outlined">send</span>
            Gửi tâm tình
          </button>
        </div>
      </div>
    </Dialog>

    <!-- Preview Chapter Modal -->
    <Dialog 
      v-model:visible="previewDialogVisible" 
      :header="activePreviewChapter?.title || 'Đọc thử'" 
      :modal="true" 
      class="!max-w-3xl !w-[90vw] !rounded-[40px] !bg-surface-container-lowest"
    >
      <div class="p-6 md:p-10 font-literata text-lg text-on-surface-variant leading-relaxed select-none overflow-y-auto max-h-[60vh] text-justify whitespace-pre-wrap select-none no-copy">
        {{ activePreviewChapter?.content || 'Không có nội dung hiển thị.' }}
      </div>
      <template #footer>
        <div class="p-4 bg-indigo-50/50 rounded-2xl border border-indigo-100/60 flex items-center justify-between text-xs text-slate-600 w-full mt-4">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-700 text-sm">lock</span>
            <span>Bản đọc thử miễn phí được bảo vệ bản quyền số bởi KomiBook DRM.</span>
          </div>
          <button @click="previewDialogVisible = false" class="px-6 py-2.5 bg-primary text-on-primary rounded-xl font-bold uppercase tracking-widest border-none cursor-pointer hover:bg-primary/95 transition-all text-[10px]">Đóng</button>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import apiClient from '@/services/axios'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import { useWishlistStore } from '@/stores/wishlist'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()

const book = ref(null)
const loading = ref(true)
const seriesBooks = ref([])
const ownershipData = ref({ owned: false, order_id: null, book_id: null })
const recentAnnotations = ref([])
const readingProgress = ref(45) 
const showReviewModal = ref(false)
const previewDialogVisible = ref(false)
const activePreviewChapter = ref(null)

const openPreviewChapter = (chapter) => {
  activePreviewChapter.value = chapter
  previewDialogVisible.value = true
}

const quickStats = computed(() => [
  { label: 'Đánh giá', value: book.value?.reviews?.length || 0, icon: 'star_rate' },
  { label: 'Yêu thích', value: '4.2k', icon: 'favorite' },
  { label: 'Khám phá', value: '1.2k', icon: 'visibility' }
])

const bookMeta = computed(() => [
  { label: 'Nhà xuất bản', value: book.value?.vendor?.name || 'KomiBook Studio' },
  { label: 'Năm phát hành', value: '2024 (Digital)' },
  { label: 'Số trang', value: '458 trang' },
  { label: 'Mã ISBN', value: book.value?.isbn || '978-604-XXX' }
])

const fetchBookDetail = async () => {
  loading.value = true
  try {
    const response = await apiClient.get(`/api/books/${route.params.slug}`)
    const responseData = response.data.data || response.data
    book.value = responseData

    const promises = []
    if (authStore.isAuthenticated && responseData.type === 'ebook') {
      promises.push(checkEbookOwnership(responseData.id))
      promises.push(fetchRecentAnnotations(responseData.id))
    }
    if (responseData.series) {
      promises.push(fetchSeriesBooks(responseData.id))
    }
    await Promise.allSettled(promises)
  } catch (error) {
    console.error('Lỗi tải chi tiết sách:', error)
  } finally {
    loading.value = false
  }
}

const checkEbookOwnership = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/check-ownership`)
    ownershipData.value = res.data.data
  } catch (error) {
    console.warn('Không thể kiểm tra sở hữu:', error)
  }
}

const fetchRecentAnnotations = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/recent-annotations`)
    recentAnnotations.value = res.data.data || []
  } catch (error) {
    console.warn('Không thể tải ghi chú:', error)
  }
}

const fetchSeriesBooks = async (bookId) => {
  try {
    const res = await apiClient.get(`/api/books/${bookId}/series`)
    seriesBooks.value = res.data.data || []
  } catch (error) {
    console.warn('Không thể tải sách cùng series:', error)
  }
}

const goToReader = () => {
  if (ownershipData.value.order_id && ownershipData.value.book_id) {
    router.push({
      name: 'ebook-reader',
      params: { orderId: ownershipData.value.order_id, bookId: ownershipData.value.book_id }
    })
  }
}

const averageRating = computed(() => {
  if (!book.value || !book.value.reviews || book.value.reviews.length === 0) return 0
  const sum = book.value.reviews.reduce((acc, curr) => acc + curr.rating, 0)
  return Math.round(sum / book.value.reviews.length)
})

const reviewForm = ref({ rating: 5, comment: '' })
const isSubmittingReview = ref(false)

const submitReview = async () => {
  if (!reviewForm.value.rating) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn số sao đánh giá.', life: 3000 })
    return
  }
  isSubmittingReview.value = true
  try {
    const response = await apiClient.post(`/api/books/${book.value.id}/reviews`, reviewForm.value)
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.data.message || 'Cảm ơn bạn đã đánh giá!', life: 3000 })
    if (!book.value.reviews) book.value.reviews = []
    book.value.reviews.unshift(response.data.data)
    reviewForm.value = { rating: 5, comment: '' }
    showReviewModal.value = false
  } catch (error) {
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi gửi đánh giá'
    if (error.response?.status === 401) {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Bạn cần đăng nhập để đánh giá.', life: 5000 })
    } else {
      toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 5000 })
    }
  } finally {
    isSubmittingReview.value = false
  }
}

const addToCart = () => {
  if (!book.value) return
  cartStore.addToCart({
    id: book.value.id, title: book.value.title, slug: book.value.slug,
    author: book.value.author, cover_image: book.value.cover_image,
    price: book.value.price, sale_price: book.value.sale_price,
    type: book.value.type, vendor: book.value.vendor,
    vendor_id: book.value.vendor?.id
  })
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã thêm "${book.value.title}" vào giỏ hàng!`, life: 3000 })
}

const buyNow = () => {
  if (!book.value) return
  addToCart()
  router.push('/cart')
}

const toggleWishlist = async () => {
  if (!book.value) return
  try {
    const res = await wishlistStore.toggleWishlist(book.value.id)
    if (res.status === 'added') {
      toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã thêm vào danh sách yêu thích', life: 2000 })
    } else if (res.status === 'removed') {
      toast.add({ severity: 'info', summary: 'Đã bỏ', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
    } else if (res.status === 'unauthorized') {
      toast.add({ severity: 'warn', summary: 'Thông báo', detail: 'Vui lòng đăng nhập để lưu yêu thích', life: 3000 })
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

const formatCurrency = (value) => {
  if (!value) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateString))
}

const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}

watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    seriesBooks.value = []
    ownershipData.value = { owned: false, order_id: null, book_id: null }
    recentAnnotations.value = []
    fetchBookDetail()
  }
})

onMounted(() => {
  if (route.params.slug) fetchBookDetail()
  wishlistStore.fetchWishlistIds()
})
</script>

<style scoped>
.perspective-1000 {
  perspective: 1000px;
}

.preserve-3d {
  transform-style: preserve-3d;
}

.rotate-y-12 {
  transform: rotateY(-12deg);
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

/* Hide scrollbar */
::-webkit-scrollbar {
  width: 0px;
}
</style>
