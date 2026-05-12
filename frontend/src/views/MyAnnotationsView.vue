<template>
  <div class="min-h-screen bg-background font-outfit antialiased">
    <!-- Dynamic Background Decor -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden opacity-30">
       <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-tertiary/10 blur-[100px] rounded-full"></div>
       <div class="absolute bottom-[-10%] left-[-10%] w-[400px] h-[400px] bg-primary/10 blur-[120px] rounded-full"></div>
    </div>

    <div class="w-full px-gutter max-w-[1400px] mx-auto py-xl relative z-10">
      <div class="flex flex-col lg:flex-row gap-xl">
        
        <!-- Sidebar -->
        <UserSidebar :user="authStore.user" class="lg:w-80 shrink-0" />

        <!-- Main Content -->
        <main class="flex-1 min-w-0">
          <div class="bg-surface-container-lowest/60 backdrop-blur-xl rounded-[48px] border border-outline-variant/10 shadow-2xl overflow-hidden min-h-[700px] flex flex-col">
            
            <!-- Hero Header Section -->
            <div class="p-10 md:p-14 border-b border-outline-variant/5 bg-surface-container-low/20 flex flex-col md:flex-row md:items-end justify-between gap-10">
              <div class="space-y-4">
                <div class="flex items-center gap-4">
                   <div class="w-2 h-10 bg-tertiary rounded-full"></div>
                   <h1 class="text-4xl md:text-5xl font-black text-on-surface tracking-tighter leading-none">Dấu ấn tri thức</h1>
                </div>
                <p class="text-on-surface-variant font-medium opacity-60 max-w-md">Lưu giữ những tâm đắc, suy tư và khoảnh khắc bừng sáng trong hành trình đọc sách của bạn.</p>
              </div>
              
              <div class="relative w-full md:w-80">
                 <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl">search</span>
                 <input 
                   v-model="searchQuery"
                   type="text" 
                   placeholder="Tìm nội dung ghi chú..." 
                   class="w-full pl-12 pr-6 py-4 bg-surface-container-high rounded-2xl border-none text-sm font-bold focus:ring-4 focus:ring-tertiary/10 transition-all outline-none"
                 />
              </div>
            </div>

            <div class="p-10 md:p-14 flex-grow">
              <!-- Loading -->
              <div v-if="loading" class="py-32 flex flex-col items-center gap-8 animate-fade-in">
                <div class="relative w-20 h-20">
                  <div class="absolute inset-0 border-8 border-tertiary/10 rounded-full"></div>
                  <div class="absolute inset-0 border-8 border-t-tertiary rounded-full animate-spin"></div>
                </div>
                <p class="text-[11px] font-black text-outline uppercase tracking-[0.3em] animate-pulse">Đang thu thập các dấu ấn...</p>
              </div>

              <!-- Empty State -->
              <div v-else-if="filteredAnnotations.length === 0" class="py-32 text-center animate-fade-in">
                <div class="w-32 h-32 bg-surface-container-high rounded-[48px] flex items-center justify-center mx-auto mb-10 text-outline/20 transform -rotate-3 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-7xl text-tertiary/40">edit_note</span>
                </div>
                <h3 class="text-3xl font-black text-on-surface mb-4 tracking-tight">Trang giấy chưa có mực</h3>
                <p class="text-on-surface-variant mb-12 max-w-sm mx-auto text-lg leading-relaxed font-medium opacity-60">
                  Bạn chưa có ghi chú nào. Hãy bắt đầu ghi lại những suy nghĩ khi đọc E-book nhé!
                </p>
              </div>

              <!-- Annotations List (MD3 Style) -->
              <div v-else class="space-y-12">
                 <div v-for="(group, bookTitle) in groupedAnnotations" :key="bookTitle" class="space-y-6">
                    <div class="flex items-center gap-4">
                       <span class="material-symbols-outlined text-primary">auto_stories</span>
                       <h2 class="text-xl font-black text-on-surface tracking-tight">{{ bookTitle }}</h2>
                       <div class="flex-grow h-px bg-outline-variant/10"></div>
                       <span class="text-[10px] font-black text-outline uppercase tracking-widest">{{ group.length }} ghi chú</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                       <div v-for="note in group" :key="note.id" class="group bg-surface-container-low p-8 rounded-[32px] border border-outline-variant/10 hover:border-tertiary/30 hover:bg-white transition-all duration-500 relative overflow-hidden">
                          <!-- Color accent bar -->
                          <div class="absolute top-0 left-0 w-2 h-full" :style="{ backgroundColor: note.color || '#ba0035' }"></div>
                          
                          <div class="flex justify-between items-start mb-6">
                             <div class="flex items-center gap-3">
                                <span class="px-3 py-1 bg-surface-container-high text-on-surface-variant text-[9px] font-black uppercase tracking-widest rounded-lg border border-outline-variant/10">
                                   {{ note.chapter || 'Chương 1' }}
                                </span>
                                <span class="text-[10px] font-bold text-outline opacity-60">{{ formatDate(note.created_at) }}</span>
                             </div>
                             <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="goToReader(note)" class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-primary hover:bg-primary hover:text-on-primary transition-all">
                                   <span class="material-symbols-outlined text-[20px]">open_in_new</span>
                                </button>
                                <button @click="deleteNote(note.id)" class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-error hover:bg-error hover:text-on-error transition-all">
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
                             <p class="text-lg font-black text-on-surface leading-snug tracking-tight">
                                {{ note.note_content }}
                             </p>
                          </div>
                       </div>
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

const fetchAnnotations = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/annotations')
    annotations.value = res.data.data || []
  } catch (error) {
    console.error('Annotations fetch error:', error)
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải ghi chú', life: 3000 })
  } finally {
    loading.value = false
  }
}

const filteredAnnotations = computed(() => {
  if (!searchQuery.value) return annotations.value
  const q = searchQuery.value.toLowerCase()
  return annotations.value.filter(a => 
    a.note_content.toLowerCase().includes(q) || 
    (a.highlighted_text && a.highlighted_text.toLowerCase().includes(q)) ||
    (a.book?.title && a.book.title.toLowerCase().includes(q))
  )
})

const groupedAnnotations = computed(() => {
  const groups = {}
  filteredAnnotations.value.forEach(a => {
    const title = a.book?.title || 'Unknown Book'
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
  } catch (error) {
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
@import url('https://fonts.googleapis.com/css2?family=Literata:ital,wght@0,400;0,700;1,400;1,700&family=Outfit:wght@100;400;900&display=swap');

.font-outfit {
  font-family: 'Outfit', sans-serif;
}

.font-literata {
  font-family: 'Literata', serif;
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
</style>
