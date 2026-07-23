<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row items-stretch gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" />

      <!-- Main Content -->
      <main class="flex-1 min-w-0 w-full flex flex-col">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden flex-1 flex flex-col">
          <div class="p-lg md:p-xl border-b border-outline-variant/10">
            <h1 class="text-2xl font-black text-on-surface tracking-tight mb-1">Danh sách yêu thích</h1>
            <p class="text-sm text-on-surface-variant font-medium">Lưu giữ những cuốn sách bạn đang quan tâm.</p>
          </div>
 
          <div class="p-lg md:p-xl">
            <!-- Loading State -->
            <div v-if="loading" class="py-20 flex flex-col items-center gap-4">
               <div class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
               <p class="text-sm font-bold text-outline">Đang tải danh sách...</p>
            </div>
 
            <!-- Empty State -->
            <div v-else-if="wishlist.length === 0" class="py-16 text-center animate-fade-in">
              <div class="w-20 h-20 bg-error-container/20 rounded-3xl flex items-center justify-center mx-auto mb-4 text-error border border-outline-variant/10">
                <span class="material-symbols-outlined text-4xl">favorite</span>
              </div>
              <h3 class="text-lg font-bold text-on-surface mb-1 tracking-tight">Chưa có cuốn sách nào</h3>
              <p class="text-xs text-on-surface-variant mb-6 max-w-xs mx-auto font-medium leading-relaxed">
                Hãy thả tim cho những cuốn sách bạn yêu thích để chúng xuất hiện ở đây nhé!
              </p>
              <button @click="$router.push('/catalog')" class="bg-primary text-on-primary px-5 py-2.5 rounded-xl font-bold text-xs shadow-md hover:bg-primary/90 active:scale-95 transition-all border-none cursor-pointer flex items-center gap-2 mx-auto">
                <span>Khám phá KomiBook</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </button>
            </div>
 
            <!-- Wishlist Grid -->
            <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-gutter animate-fade-in">
              <div v-for="book in wishlist" :key="book.id" 
                   class="group bg-surface-container-low/30 rounded-2xl border border-outline-variant/20 overflow-hidden hover:border-primary/30 transition-all flex flex-col">
                <div class="relative aspect-[3/4] cursor-pointer overflow-hidden" @click="$router.push(`/book/${book.slug}`)">
                  <img :src="getCoverUrl(book.cover_image)" :alt="book.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                  <div class="absolute top-2 right-2">
                    <button @click.stop="toggleWishlist(book.id)" class="w-8 h-8 rounded-full bg-white/90 backdrop-blur shadow-md flex items-center justify-center text-error hover:scale-110 transition-all">
                      <span class="material-symbols-outlined text-[20px] fill-1">favorite</span>
                    </button>
                  </div>
                </div>
                <div class="p-4 flex-grow flex flex-col">
                  <h4 class="text-sm font-bold text-on-surface line-clamp-2 mb-1 leading-snug group-hover:text-primary transition-colors cursor-pointer" @click="$router.push(`/book/${book.slug}`)">
                    {{ book.title }}
                  </h4>
                  <p class="text-[11px] text-on-surface-variant font-medium mb-3">{{ book.author }}</p>
                  <div class="mt-auto flex items-center justify-between">
                    <span class="text-sm font-bold text-primary">{{ formatCurrency(book.sale_price || book.price) }}</span>
                    <button @click="addToCart(book)" class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center hover:bg-primary hover:text-on-primary transition-all">
                      <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
 
<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import UserSidebar from '@/components/profile/UserSidebar.vue'
 
const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const toast = useToast()
 
const wishlist = ref([])
const loading = ref(true)
 
const fetchWishlist = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/api/wishlist')
    wishlist.value = res.data.data || res.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách yêu thích', life: 3000 })
  } finally {
    loading.value = false
  }
}
 
const toggleWishlist = async (bookId) => {
  try {
    await wishlistStore.toggleWishlist(bookId)
    wishlist.value = wishlist.value.filter(b => b.id !== bookId)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã xóa khỏi danh sách yêu thích', life: 2000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}
 
const addToCart = (book) => {
  cartStore.addToCart({
    id: book.id, title: book.title, slug: book.slug,
    author: book.author, cover_image: book.cover_image,
    price: book.price, sale_price: book.sale_price,
    type: book.type, vendor: book.vendor,
    vendor_id: book.vendor?.id
  })
  toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vào giỏ hàng', life: 2000 })
}
 
const formatCurrency = (val) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
 
const getCoverUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  if (path.includes('/storage/')) return path.substring(path.indexOf('/storage/'))
  return `/storage/${path}`
}
 
onMounted(() => {
  fetchWishlist()
})
</script>
 
<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
.fill-1 { font-variation-settings: 'FILL' 1; }
</style>
