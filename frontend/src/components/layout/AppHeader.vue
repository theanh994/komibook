<template>
  <header class="bg-white dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16 md:h-20">
        
        <!-- Brand / Logo -->
        <router-link to="/" class="flex items-center gap-3 no-underline group">
          <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl overflow-hidden shadow-md group-hover:scale-105 transition-transform duration-300">
            <!-- Logo Image -->
            <img v-if="logoExists" src="@/assets/logo.png" alt="KomiBook Logo" class="w-full h-full object-cover" />
            <!-- Fallback Icon -->
            <div v-else class="w-full h-full bg-primary flex items-center justify-center">
              <i class="pi pi-book text-white text-xl md:text-2xl"></i>
            </div>
          </div>
          <div class="flex flex-col">
            <span class="text-xl md:text-2xl font-black tracking-tight text-surface-900 dark:text-surface-0">Komi<span class="text-primary">Book</span></span>
            <span class="text-[10px] uppercase tracking-[0.2em] font-bold text-surface-400 -mt-1">Thư viện của bạn</span>
          </div>
        </router-link>

        <!-- Navigation Links (Desktop) -->
        <nav class="hidden md:flex items-center gap-2">
          <Button label="Trang chủ" icon="pi pi-home" text class="p-button-secondary font-medium" @click="$router.push('/')" />
          
          <!-- Giỏ hàng -->
          <button 
            class="relative p-2 text-surface-500 hover:text-surface-900 transition-colors mx-2 cursor-pointer bg-transparent border-none"
            @click="$router.push('/cart')"
          >
            <i class="pi pi-shopping-cart text-xl"></i>
            <span 
              v-if="cartStore.totalItems > 0"
              class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full"
            >
              {{ cartStore.totalItems > 9 ? '9+' : cartStore.totalItems }}
            </span>
          </button>
          
          <template v-if="authStore.isAuthenticated">
            <!-- Quản trị Link (Condition based on roles) -->
            <Button 
                v-if="authStore.isAdmin || authStore.isVendor"
                :label="authStore.isAdmin ? 'Hệ thống' : 'Gian hàng'" 
                :icon="authStore.isAdmin ? 'pi pi-cog' : 'pi pi-shop'" 
                class="p-button-primary font-bold shadow-sm"
                @click="goToDashboard" 
            />
            
            <div class="h-8 w-[1px] bg-surface-200 dark:bg-surface-700 mx-3"></div>
            
              <div class="flex items-center gap-3 pl-2 cursor-pointer" @click="toggleUserMenu" aria-haspopup="true" aria-controls="overlay_menu">
                <div class="flex flex-col items-end mr-1 hidden lg:flex">
                  <span class="text-xs text-surface-500 font-medium">Xin chào,</span>
                  <span class="text-sm font-bold text-surface-900 dark:text-surface-50">{{ authStore.user?.name }}</span>
                </div>
                <Avatar icon="pi pi-user" shape="circle" class="bg-primary text-white" />
                <i class="pi pi-angle-down text-surface-500 text-sm"></i>
              </div>
              <Menu ref="userMenu" id="overlay_menu" :model="userMenuItems" :popup="true" />
          </template>

          <template v-else>
            <div class="flex gap-2 ml-4">
              <Button label="Đăng nhập" icon="pi pi-sign-in" text class="font-bold" @click="$router.push('/login')" />
              <Button label="Đăng ký" icon="pi pi-user-plus" severity="primary" class="font-bold shadow-md" @click="$router.push('/register')" />
            </div>
          </template>
        </nav>

        <!-- Mobile Menu Button -->
        <Button icon="pi pi-bars" class="md:hidden" text @click="mobileMenu = !mobileMenu" />
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Menu from 'primevue/menu'

const router = useRouter()
const authStore = useAuthStore()
const cartStore = useCartStore()
const logoExists = ref(false)
const mobileMenu = ref(false)
const userMenu = ref()

const userMenuItems = ref([
  {
    label: 'Thông tin cá nhân',
    icon: 'pi pi-user-edit',
    command: () => {
      router.push('/profile')
    }
  },
  {
    separator: true
  },
  {
    label: 'Đăng xuất',
    icon: 'pi pi-sign-out',
    command: async () => {
      await authStore.logout()
      router.push({ name: 'home' })
    }
  }
])

const toggleUserMenu = (event) => {
  userMenu.value.toggle(event)
}

// Check if logo exists (simplified)
onMounted(() => {
  const img = new Image()
  img.onload = () => logoExists.value = true
  img.onerror = () => logoExists.value = false
  img.src = new URL('@/assets/logo.png', import.meta.url).href
})

const goToDashboard = () => {
  router.push({ name: 'dashboard' })
}
</script>

<style scoped>
.p-button-text {
  transition: all 0.2s ease;
}
.p-button-text:hover {
  transform: translateY(-1px);
}
</style>
