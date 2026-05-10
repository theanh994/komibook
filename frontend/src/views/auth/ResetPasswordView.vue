<template>
  <div class="bg-background pattern-bg min-h-[calc(100vh-80px)] flex items-center justify-center p-4 md:p-8 font-inter text-on-background overflow-x-hidden">
    <!-- Main Container -->
    <main class="w-full max-w-[500px] animate-in fade-in zoom-in duration-500 relative z-10">
      <!-- Reset Card -->
      <div class="glass-panel soft-shadow rounded-[24px] p-8 md:p-10 border border-white/40 flex flex-col gap-8 shadow-2xl bg-white/60 backdrop-blur-sm">
        
        <!-- Header -->
        <div class="flex flex-col items-center text-center gap-4">
          <div class="w-16 h-16 bg-primary/[0.05] rounded-2xl flex items-center justify-center mb-2">
            <span class="material-symbols-outlined text-[40px] text-primary" style="font-variation-settings: 'FILL' 1;">password</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-semibold text-on-surface tracking-tight">Đặt lại mật khẩu</h1>
          <p class="text-base text-on-surface-variant font-medium leading-relaxed">
            Vui lòng nhập mật khẩu mới để khôi phục quyền truy cập vào Komibook Premium.
          </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleReset" class="flex flex-col gap-6">
          <div class="flex flex-col gap-5">
            <!-- New Password -->
            <div class="flex flex-col gap-1.5">
              <label class="text-[13px] font-semibold text-on-surface-variant ml-0.5 flex items-center gap-2 uppercase tracking-wide" for="new-password">
                <span class="material-symbols-outlined text-[18px] text-primary/80">lock</span>
                Mật khẩu mới
              </label>
              <div class="relative group">
                <input 
                  v-model="password"
                  class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200" 
                  id="new-password" 
                  placeholder="••••••••" 
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                />
                <button @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary cursor-pointer transition-colors p-1" type="button">
                  <span class="material-symbols-outlined" style="font-size: 20px;">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                </button>
              </div>
              
              <!-- Dynamic Strength Indicator -->
              <div class="mt-3 flex flex-col gap-2 px-0.5" v-if="password">
                <div class="flex gap-1.5 h-1.5">
                  <div 
                    v-for="i in 4" :key="i"
                    class="flex-1 rounded-full transition-all duration-500"
                    :class="[
                      i <= strengthScore ? strengthColor : 'bg-outline-variant/20'
                    ]"
                  ></div>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-[11px] font-bold uppercase tracking-widest transition-colors duration-500" :class="strengthTextColor">
                    Độ mạnh: {{ strengthLabel }}
                  </span>
                  <span class="text-[10px] text-on-surface-variant/60 font-medium">Tối thiểu 8 ký tự</span>
                </div>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="flex flex-col gap-1.5">
              <label class="text-[13px] font-semibold text-on-surface-variant ml-0.5 flex items-center gap-2 uppercase tracking-wide" for="confirm-password">
                <span class="material-symbols-outlined text-[18px] text-primary/80">verified_user</span>
                Xác nhận mật khẩu
              </label>
              <input 
                v-model="password_confirmation"
                class="w-full h-11 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200" 
                id="confirm-password" 
                placeholder="••••••••" 
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                required
              />
              <p v-if="password_confirmation && password !== password_confirmation" class="text-[11px] text-error font-medium mt-1 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">error</span>
                Mật khẩu xác nhận không khớp
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <button 
            class="w-full h-12 bg-primary text-on-primary font-semibold text-base rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-primary/10 transition-all duration-300 soft-shadow disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center gap-2 cursor-pointer mt-2" 
            type="submit"
            :disabled="loading || strengthScore < 2"
          >
            <template v-if="loading">
              <span class="pi pi-spin pi-spinner text-sm"></span>
              <span>Đang cập nhật...</span>
            </template>
            <template v-else>
              <span>Cập nhật mật khẩu</span>
              <span class="material-symbols-outlined text-[20px]">check_circle</span>
            </template>
          </button>
        </form>

        <!-- Back Link -->
        <div class="text-center pt-2 border-t border-outline-variant/20">
          <router-link to="/login" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:text-secondary transition-colors group">
            <span class="material-symbols-outlined text-base group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Quay lại Đăng nhập
          </router-link>
        </div>
      </div>
    </main>

    <!-- Background Accents -->
    <div class="fixed top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary-fixed rounded-full opacity-10 blur-[120px] -z-0"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-secondary-fixed rounded-full opacity-10 blur-[120px] -z-0"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const showPassword = ref(false)

const token = ref('')
const email = ref('')

onMounted(() => {
  token.value = route.query.token || ''
  email.value = route.query.email || ''
  
  if (!token.value) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Liên kết không hợp lệ hoặc đã hết hạn.', life: 5000 })
  }
})

// Logic tính toán độ mạnh mật khẩu
const strengthScore = computed(() => {
  let score = 0
  if (!password.value) return 0
  
  // Tiêu chí 1: Độ dài
  if (password.value.length >= 8) score++
  if (password.value.length >= 12) score++
  
  // Tiêu chí 2: Có số
  if (/[0-9]/.test(password.value)) score++
  
  // Tiêu chí 3: Có ký tự đặc biệt hoặc chữ hoa
  if (/[A-Z]/.test(password.value) || /[^A-Za-z0-9]/.test(password.value)) score++
  
  return Math.min(score, 4)
})

const strengthLabel = computed(() => {
  const labels = ['Rất yếu', 'Yếu', 'Trung bình', 'Mạnh', 'Rất mạnh']
  return labels[strengthScore.value]
})

const strengthColor = computed(() => {
  if (strengthScore.value <= 1) return 'bg-error'
  if (strengthScore.value === 2) return 'bg-secondary' // Màu hồng đậm trong theme
  if (strengthScore.value === 3) return 'bg-primary-container'
  return 'bg-green-500'
})

const strengthTextColor = computed(() => {
  if (strengthScore.value <= 1) return 'text-error'
  if (strengthScore.value === 2) return 'text-secondary'
  if (strengthScore.value === 3) return 'text-primary'
  return 'text-green-600'
})

const handleReset = async () => {
  if (password.value !== password_confirmation.value) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }

  if (strengthScore.value < 2) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mật khẩu quá yếu. Vui lòng thêm số hoặc ký tự.', life: 3000 })
    return
  }

  loading.value = true
  try {
    await apiClient.post('/api/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value
    })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Mật khẩu đã được thay đổi thành công!', life: 3000 })
    
    setTimeout(() => {
      router.push({ name: 'login' })
    }, 2000)
  } catch (error) {
    toast.add({ 
      severity: 'error', 
      summary: 'Lỗi', 
      detail: error.response?.data?.message || 'Có lỗi xảy ra, vui lòng thử lại.', 
      life: 3000 
    })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
input:focus {
  transform: translateY(-1px);
}
.animate-in {
  animation-duration: 0.6s;
  animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes zoom-in { from { transform: scale(0.95) translateY(20px); } to { transform: scale(1) translateY(0); } }
.animate-in.fade-in { animation-name: fade-in; }
.animate-in.zoom-in { animation-name: zoom-in; }
</style>
