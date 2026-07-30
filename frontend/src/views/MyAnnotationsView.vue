<template>
  <div class="min-h-screen bg-background font-inter antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-tertiary/10 blur-[100px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-primary/10 blur-[120px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl relative z-10">
      <div class="flex flex-col lg:flex-row items-start gap-xl">
        
        <!-- Sidebar -->
        <UserSidebar :user="authStore.user" />

        <!-- Main Content -->
        <main class="flex-1 min-w-0" aria-labelledby="annotations-title">
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-3xl border border-outline-variant/20 shadow-xl overflow-hidden min-h-[390px] flex flex-col">
            
            <!-- Hero Header Section -->
            <div class="p-6 md:p-10 border-b border-outline-variant/10 bg-surface-container-low/20 flex flex-col md:flex-row md:items-end justify-between gap-6">
              <div class="space-y-4">
                <div class="flex items-center gap-4">
                   <div class="w-2 h-10 bg-tertiary rounded-full"></div>
                   <h1 id="annotations-title" class="text-3xl md:text-4xl font-bold text-on-surface tracking-tight leading-tight">Ghi chú đọc sách</h1>
                </div>
                <p class="text-on-surface-variant font-medium max-w-md">Tìm lại ghi chú và phần văn bản bạn đã đánh dấu trong ebook.</p>
              </div>
              
              <div class="relative w-full md:w-80">
                 <label for="annotation-search" class="sr-only">Tìm trong ghi chú</label>
                 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl">search</span>
                 <input 
                   id="annotation-search"
                   v-model="searchQuery"
                   type="text" 
                   placeholder="Tìm nội dung ghi chú..." 
                   class="w-full pl-12 pr-6 py-4 bg-surface-container-high rounded-2xl border-none text-sm font-bold focus:ring-4 focus:ring-tertiary/10 transition-all outline-none"
                 />
              </div>
            </div>

            <div class="p-6 md:p-10 flex-grow">
              <!-- Loading -->
              <div v-if="loading" class="py-20 flex flex-col items-center gap-8 animate-fade-in" role="status" aria-live="polite">
                <div class="relative w-20 h-20">
                  <div class="absolute inset-0 border-8 border-tertiary/10 rounded-full"></div>
                  <div class="absolute inset-0 border-8 border-t-tertiary rounded-full animate-spin"></div>
                </div>
                <p class="text-sm font-bold text-on-surface-variant">Đang tải ghi chú...</p>
              </div>

              <div v-else-if="error" class="py-16 text-center" role="alert">
                <span class="material-symbols-outlined text-5xl text-error" aria-hidden="true">edit_off</span>
                <h2 class="mt-3 text-xl font-bold text-on-surface">Không thể tải ghi chú</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-on-surface-variant">{{ error }}</p>
                <button type="button" class="mt-5 min-h-11 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-on-primary" @click="fetchAnnotations">Thử lại</button>
              </div>

              <!-- Empty State -->
              <div v-else-if="filteredAnnotations.length === 0" class="py-32 text-center animate-fade-in">
                <div class="w-24 h-24 bg-surface-container-high rounded-3xl flex items-center justify-center mx-auto mb-6 text-outline/30 border border-outline-variant/20">
                  <span class="material-symbols-outlined text-7xl text-tertiary/40">edit_note</span>
                </div>
                <h2 class="text-2xl font-bold text-on-surface mb-3 tracking-tight">{{ annotations.length ? 'Không tìm thấy ghi chú' : 'Bạn chưa có ghi chú' }}</h2>
                <p class="text-on-surface-variant max-w-sm mx-auto text-base leading-relaxed font-medium">
                  {{ annotations.length ? 'Hãy thử từ khóa khác.' : 'Bạn có thể tạo ghi chú trong khi đọc ebook.' }}
                </p>
              </div>

              <!-- Annotations List (MD3 Style) -->
              <div v-else class="space-y-12">
                 <div v-for="(group, bookTitle) in groupedAnnotations" :key="bookTitle" class="space-y-6">
                    <div class="flex items-center gap-4">
                       <span class="material-symbols-outlined text-primary">auto_stories</span>
                       <h2 class="text-xl font-bold text-on-surface tracking-tight">{{ bookTitle }}</h2>
                       <div class="flex-grow h-px bg-outline-variant/10"></div>
                       <span class="text-xs font-bold text-outline">{{ group.length }} ghi chú</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                       <article v-for="note in group" :key="note.id" class="group bg-surface-container-low p-6 rounded-2xl border border-outline-variant/20 hover:border-tertiary/30 transition-colors relative overflow-hidden">
                          <!-- Color accent bar -->
                          <div class="absolute top-0 left-0 w-2 h-full" :style="{ backgroundColor: note.color || '#ba0035' }"></div>
                          
                          <div class="flex justify-between items-start mb-6">
                             <div class="flex items-center gap-3">
                                <span class="px-3 py-1 bg-surface-container-high text-on-surface-variant text-xs font-bold rounded-lg border border-outline-variant/10">
                                   {{ note.chapter || 'Chương 1' }}
                                </span>
                                <span class="text-xs font-bold text-outline">{{ formatDate(note.created_at) }}</span>
                             </div>
                             <div class="flex gap-2 opacity-100">
                                <button type="button" @click="goToReader(note)" class="w-11 h-11 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary hover:bg-primary hover:text-on-primary transition-colors" :aria-label="`Mở ghi chú trong ${bookTitle}`">
                                   <span class="material-symbols-outlined text-[20px]">open_in_new</span>
                                </button>
                                <button type="button" @click="deleteNote(note.id)" class="w-11 h-11 rounded-xl bg-white shadow-sm flex items-center justify-center text-error hover:bg-error hover:text-on-error transition-colors" :aria-label="`Xóa ghi chú trong ${bookTitle}`">
                                   <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                             </div>
                          </div>

                          <div class="space-y-4">
                             <div v-if="note.highlighted_text" class="relative pl-6 py-2">
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-outline-variant/20 rounded-full"></div>
                                <p class="text-sm italic font-literata text-on-surface-variant leading-relaxed line-clamp-3">
                                   "{{ note.highlighted_text }}"
                                </p>
                             </div>
                             <p class="text-lg font-bold text-on-surface leading-snug tracking-tight">
                                {{ note.note_content }}
                             </p>
                          </div>
                       </article>
                    </div>
                 </div>
              </div>
            </div>
            
          </div>
        </main>
      </div>
    </div>

    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import UserSidebar from '@/components/profile/UserSidebar.vue'
import Toast from 'primevue/toast'

const authStore = useAuthStore()
const router = useRouter()
const toast = useToast()

const annotations = ref([])
const loading = ref(true)
const searchQuery = ref('')
const error = ref('')

const fetchAnnotations = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await apiClient.get('/api/annotations')
    annotations.value = res.data.data || []
  } catch (requestError) {
    console.error('Annotations fetch error:', requestError)
    annotations.value = []
    error.value = 'Vui lòng kiểm tra kết nối và thử lại.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải ghi chú', life: 3000 })
  } finally {
    loading.value = false
  }
}

const filteredAnnotations = computed(() => {
  if (!searchQuery.value) return annotations.value
  const q = searchQuery.value.toLowerCase()
  return annotations.value.filter(a =>
    String(a.note_content || '').toLowerCase().includes(q) ||
    String(a.highlighted_text || '').toLowerCase().includes(q) ||
    String(a.book?.title || '').toLowerCase().includes(q)
  )
})

const groupedAnnotations = computed(() => {
  const groups = {}
  filteredAnnotations.value.forEach(a => {
    const title = a.book?.title || 'Sách không còn trong danh mục'
    if (!groups[title]) groups[title] = []
    groups[title].push(a)
  })
  return groups
})

const goToReader = (note) => {
  if (note.order_id && note.book_id) {
    router.push({
      name: 'ebook-reader',
      params: { orderId: note.order_id, bookId: note.book_id },
      query: { page: note.page_number }
    })
  }
}

const deleteNote = async (id) => {
  if (!confirm('Bạn có chắc muốn xóa ghi chú này?')) return
  try {
    await apiClient.delete(`/api/annotations/${id}`)
    annotations.value = annotations.value.filter(a => a.id !== id)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã xóa ghi chú', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa ghi chú', life: 3000 })
  }
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(dateString))
}

onMounted(() => {
  fetchAnnotations()
})
</script>

<style scoped>
.font-literata {
  font-family: Georgia, 'Times New Roman', serif;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slide-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
  animation: slide-up 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  opacity: 0;
}

.fill-1 {
  font-variation-settings: 'FILL' 1;
}

::-webkit-scrollbar {
  width: 0px;
}

@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-up,
  .animate-spin,
  .animate-pulse {
    animation: none !important;
  }
}
</style>
