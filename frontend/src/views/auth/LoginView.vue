<template>
  <div class="bg-background pattern-bg min-h-[calc(100vh-80px)] flex items-center justify-center p-4 md:p-8 font-inter text-on-background overflow-x-hidden">
    <!-- Main Container -->
    <main class="w-full max-w-[960px] animate-in fade-in zoom-in duration-500 relative z-10">
      <!-- Login Card -->
      <div class="glass-panel soft-shadow rounded-[24px] overflow-hidden border border-white/40 flex flex-col lg:flex-row shadow-2xl">
        
        <!-- Left Column: Brand & Welcome -->
        <div class="lg:flex flex-col justify-center p-8 lg:p-12 bg-primary/[0.03] relative overflow-hidden border-b lg:border-b-0 lg:border-r border-outline-variant/10 flex-1">
          <div class="absolute top-0 right-0 w-64 h-64 bg-primary-fixed rounded-bl-full opacity-20 -z-10 blur-3xl"></div>
          
          <div class="flex flex-col items-center lg:items-start text-center lg:text-left gap-4">
            <div class="text-3xl md:text-4xl font-bold text-primary tracking-tight leading-tight select-none">
              Komibook<span class="text-secondary text-xl md:text-2xl font-semibold align-top ml-1">Premium</span>
            </div>
            <div class="flex flex-col gap-3">
              <h1 class="text-2xl md:text-3xl font-semibold text-on-surface tracking-tight">Chào mừng trở lại</h1>
              <p class="text-base text-on-surface-variant font-medium max-w-[300px] leading-relaxed">Đăng nhập để tiếp tục khám phá kho tàng tri thức của bạn.</p>
            </div>
            
            <!-- Trust Indicators -->
            <div class="hidden lg:flex flex-col gap-3 mt-6">
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Hơn 10,000+ đầu sách tuyển chọn</span>
              </div>
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Trải nghiệm đọc Premium mượt mà</span>
              </div>
              <div class="flex items-center gap-3 text-on-surface-variant/80">
                <span class="material-symbols-outlined text-primary text-xl">check_circle</span>
                <span class="text-sm font-medium">Đồng bộ dữ liệu đa thiết bị</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Login Form -->
        <div class="p-8 md:p-10 flex flex-col gap-6 flex-1 bg-white/60 backdrop-blur-sm relative">
          <div class="absolute bottom-0 left-0 w-40 h-40 bg-secondary-fixed rounded-tr-full opacity-10 -z-10 blur-3xl"></div>
          
          <!-- Social Logins -->
          <div class="flex flex-col gap-3">
            <button type="button" class="w-full h-12 flex items-center justify-center gap-3 px-6 border border-outline-variant/60 rounded-xl bg-surface hover:bg-surface-container-low transition-all duration-200 cursor-pointer group shadow-sm active:scale-[0.98]">
              <svg class="w-5 h-5 transition-transform group-hover:scale-110" viewBox="0 0 24 24">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"></path>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"></path>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"></path>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"></path>
              </svg>
              <span class="text-sm font-semibold text-on-surface">Tiếp tục với Google</span>
            </button>
            <button type="button" class="w-full h-12 flex items-center justify-center gap-3 px-6 border border-outline-variant/60 rounded-xl bg-surface hover:bg-surface-container-low transition-all duration-200 cursor-pointer group shadow-sm active:scale-[0.98]">
              <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.05 20.28c-.98.95-2.05 1.8-3.08 1.8-1.09 0-1.44-.65-2.68-.65-1.25 0-1.63.63-2.65.65-1.07.03-2.26-.95-3.13-1.92-2.58-2.9-4.34-8.08-1.63-11.23C5.2 7.37 6.8 6.4 8.25 6.4c1.07 0 2.08.68 2.7.68.65 0 1.95-.83 3.25-.7 1.35.1 2.38.55 3.08 1.25-2.5 1.48-2.13 4.98.38 5.95-.53 1.45-1.35 3.1-2.6 4.7zM12.03 6.15c-.15-2.03 1.55-3.9 3.45-4.15.28 2.15-1.88 4.05-3.45 4.15z"></path>
              </svg>
              <span class="text-sm font-semibold text-on-surface">Tiếp tục với Apple</span>
            </button>
          </div>

          <!-- Divider -->
          <div class="flex items-center gap-4 py-2">
            <div class="flex-1 h-[1px] bg-outline-variant/40"></div>
            <span class="text-[11px] font-bold text-outline uppercase tracking-widest select-none">hoặc đăng nhập bằng email</span>
            <div class="flex-1 h-[1px] bg-outline-variant/40"></div>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleLogin" class="flex flex-col gap-6">
            <div class="flex flex-col gap-4">
              <!-- Email Input -->
              <div class="flex flex-col gap-1.5">
                <label class="text-[13px] font-semibold text-on-surface-variant ml-0.5 flex items-center gap-2 uppercase tracking-wide" for="email">
                  <span class="material-symbols-outlined text-[18px] text-primary/80">mail</span>
                  Email
                </label>
                <input 
                  v-model="email"
                  class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200" 
                  id="login-email" 
                  placeholder="name@example.com" 
                  type="email"
                  autocomplete="username"
                  required
                />
              </div>
              <!-- Password Input -->
              <div class="flex flex-col gap-1.5">
                <div class="flex justify-between items-center px-0.5">
                  <label class="text-[13px] font-semibold text-on-surface-variant flex items-center gap-2 uppercase tracking-wide" for="login-password">
                    <span class="material-symbols-outlined text-[18px] text-primary/80">lock</span>
                    Mật khẩu
                  </label>
                  <router-link to="/forgot-password" class="text-xs font-semibold text-primary hover:text-secondary transition-colors">Quên mật khẩu?</router-link>
                </div>
                <div class="relative group">
                  <input 
                    v-model="password"
                    class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200" 
                    id="login-password" 
                    placeholder="••••••••" 
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                  />
                  <button @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary cursor-pointer transition-colors p-1" type="button">
                    <span class="material-symbols-outlined" style="font-size: 20px;">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                  </button>
                </div>
              </div>
              <!-- Remember Me -->
              <div class="flex items-center gap-3 mt-1 px-0.5">
                <input 
                  v-model="remember"
                  class="peer w-5 h-5 rounded-md border border-outline-variant text-primary focus:ring-primary focus:ring-offset-0 bg-surface-container-lowest cursor-pointer transition-all checked:bg-primary checked:border-primary" 
                  id="remember" 
                  type="checkbox"
                />
                <label class="text-sm font-medium text-on-surface-variant cursor-pointer select-none hover:text-on-surface transition-colors" for="remember">Duy trì đăng nhập</label>
              </div>
            </div>
            <!-- Submit Button -->
            <button 
              class="w-full h-12 bg-primary text-on-primary font-semibold text-base rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-primary/10 transition-all duration-300 soft-shadow disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center gap-2 cursor-pointer mt-2" 
              type="submit"
              :disabled="loading"
            >
              <template v-if="loading">
                <span class="pi pi-spin pi-spinner text-sm"></span>
                <span>Đang đăng nhập...</span>
              </template>
              <template v-else>
                <span>Đăng nhập</span>
                <span class="material-symbols-outlined text-[20px]">login</span>
              </template>
            </button>
          </form>

          <!-- Footer Links -->
          <div class="text-center mt-2">
            <span class="text-sm text-on-surface-variant">Chưa có tài khoản?</span>
            <router-link to="/register" class="text-sm font-bold text-primary hover:text-secondary ml-2 underline underline-offset-4 transition-colors">Đăng ký ngay</router-link>
          </div>
        </div>
      </div>

      <!-- Trust indicators / Minimal Footer -->
      <div class="mt-8 text-center flex flex-col items-center gap-4">
        <div class="flex items-center gap-4 text-xs font-semibold text-on-surface-variant/40 uppercase tracking-widest">
          <a class="hover:text-primary transition-all" href="#">Điều khoản</a>
          <span class="w-1 h-1 bg-outline/20 rounded-full"></span>
          <a class="hover:text-primary transition-all" href="#">Bảo mật</a>
          <span class="w-1 h-1 bg-outline/20 rounded-full"></span>
          <a class="hover:text-primary transition-all" href="#">Trợ giúp</a>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const showPassword = ref(false)

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const handleLogin = async () => {
  if (!email.value || !password.value) return
  
  loading.value = true
  try {
    await authStore.login({ 
      email: email.value, 
      password: password.value,
      remember: remember.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập thành công!', life: 3000 })
    
    // Redirect sau khi đăng nhập thành công
    setTimeout(() => {
      router.push({ name: 'dashboard' })
    }, 500)
  } catch (error) {
    console.error('Login error:', error)
    let errorMessage = 'Sai email hoặc mật khẩu, vui lòng thử lại.'
    if (error.response?.data?.message) {
        errorMessage = error.response.data.message
    }
    toast.add({ severity: 'error', summary: 'Lỗi đăng nhập', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* Smooth focus state */
input:focus {
  transform: translateY(-1px);
}

.animate-in {
  animation-duration: 0.6s;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes zoom-in {
  from { transform: scale(0.95) translateY(20px); }
  to { transform: scale(1) translateY(0); }
}

.animate-in.fade-in { animation-name: fade-in; }
.animate-in.zoom-in { animation-name: zoom-in; }
</style>
