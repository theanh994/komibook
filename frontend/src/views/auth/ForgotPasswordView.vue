<template>
  <div class="bg-background pattern-bg min-h-[calc(100vh-80px)] flex items-center justify-center p-4 md:p-8 font-inter text-on-background overflow-x-hidden">
    <!-- Main Container -->
    <main class="w-full max-w-[500px] animate-in fade-in zoom-in duration-500 relative z-10">
      <!-- Recovery Card -->
      <div class="glass-panel soft-shadow rounded-[24px] p-8 md:p-10 border border-white/40 flex flex-col gap-8 shadow-2xl bg-white/60 backdrop-blur-sm">
        
        <!-- Header -->
        <div class="flex flex-col items-center text-center gap-4">
          <div class="w-16 h-16 bg-primary/[0.05] rounded-2xl flex items-center justify-center mb-2">
            <span class="material-symbols-outlined text-[40px] text-primary" style="font-variation-settings: 'FILL' 1;">lock_reset</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-semibold text-on-surface tracking-tight">Quên mật khẩu?</h1>
          <p class="text-base text-on-surface-variant font-medium leading-relaxed">
            Đừng lo lắng! Hãy nhập Email của bạn, chúng tôi sẽ gửi liên kết để khôi phục quyền truy cập.
          </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="flex flex-col gap-6">
          <div class="flex flex-col gap-2">
            <label class="text-[13px] font-semibold text-on-surface-variant ml-0.5 flex items-center gap-2 uppercase tracking-wide" for="recovery-email">
              <span class="material-symbols-outlined text-[18px] text-primary/80">mail</span>
              Địa chỉ Email
            </label>
            <input 
              v-model="email"
              class="w-full h-12 bg-surface-container-lowest border border-outline-variant/60 rounded-xl px-4 text-sm text-on-surface placeholder:text-outline/40 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all duration-200" 
              id="recovery-email" 
              placeholder="name@example.com" 
              type="email"
              autocomplete="email"
              required
            />
          </div>

          <!-- Submit Button -->
          <button 
            class="w-full h-12 bg-primary text-on-primary font-semibold text-base rounded-xl hover:bg-primary-container hover:shadow-lg active:scale-[0.98] focus:outline-none focus:ring-4 focus:ring-primary/10 transition-all duration-300 soft-shadow disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center gap-2 cursor-pointer mt-2" 
            type="submit"
            :disabled="loading"
          >
            <template v-if="loading">
              <span class="pi pi-spin pi-spinner text-sm"></span>
              <span>Đang gửi...</span>
            </template>
            <template v-else>
              <span>Gửi liên kết khôi phục</span>
              <span class="material-symbols-outlined text-[20px]">send</span>
            </template>
          </button>
        </form>

        <!-- Back Link -->
        <div class="text-center pt-2 border-t border-outline-variant/20">
          <router-link to="/login" class="inline-flex min-h-11 items-center gap-2 px-2 text-sm font-bold text-primary transition-colors hover:text-secondary group">
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
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const email = ref('')
const loading = ref(false)
const toast = useToast()

const handleSubmit = async () => {
  if (!email.value) return
  
  loading.value = true
  try {
    await apiClient.post('/api/auth/forgot-password', { email: email.value })
    toast.add({ 
      severity: 'success', 
      summary: 'Thành công', 
      detail: 'Liên kết đặt lại mật khẩu đã được gửi vào Email của bạn.', 
      life: 5000 
    })
  } catch (error) {
    console.error('Forgot password error:', error)
    toast.add({ 
      severity: 'error', 
      summary: 'Lỗi', 
      detail: error.response?.data?.message || 'Không thể gửi email khôi phục. Vui lòng thử lại sau.', 
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
