<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-50 px-4">
    <!-- Auth Card -->
    <div class="w-full max-w-[420px] bg-white rounded-xl shadow-sm shadow-slate-200/50 border border-slate-200/60 p-8">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Đăng Nhập</h1>
        <p class="text-slate-500 text-sm mt-1.5">Truy cập vào hệ thống KomiBook</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleLogin" class="flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
          <label for="email" class="text-sm font-medium text-slate-700">Email</label>
          <InputText
            id="email"
            v-model="email"
            type="email"
            autocomplete="username"
            placeholder="you@example.com"
            required
            autofocus
            class="auth-input"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-sm font-medium text-slate-700">Mật khẩu</label>
          <Password
            id="password"
            v-model="password"
            :feedback="false"
            toggleMask
            autocomplete="current-password"
            inputClass="w-full auth-input"
            placeholder="Nhập mật khẩu"
            required
          />
        </div>

        <Button
          type="submit"
          label="Đăng nhập"
          :loading="loading"
          class="auth-btn w-full mt-1 !bg-gradient-to-b !from-indigo-500 !to-indigo-600 hover:!from-indigo-600 hover:!to-indigo-700 !text-white !border-none !rounded-lg !shadow-sm !font-medium !text-sm !py-2.5 transition-all duration-300 ease-out"
        />
      </form>

      <!-- Divider + Navigation -->
      <div class="mt-6 pt-5 border-t border-slate-100 text-center">
        <p class="text-sm text-slate-500">
          Chưa có tài khoản?
          <router-link
            to="/register"
            class="text-indigo-600 hover:text-indigo-700 font-medium transition-colors duration-200"
          >
            Đăng ký ngay
          </router-link>
        </p>
      </div>
    </div>

    <Toast />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'


import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const email = ref('')
const password = ref('')
const loading = ref(false)

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const handleLogin = async () => {
  if (!email.value || !password.value) return
  
  loading.value = true
  try {
    await authStore.login({ email: email.value, password: password.value })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng nhập thành công!', life: 3000 })
    
    // Redirect sau khi đăng nhập thành công
    setTimeout(() => {
      router.push({ name: 'dashboard' })
    }, 500)
  } catch (error) {
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
/* UUPM: Focus ring cho PrimeVue inputs */
:deep(.auth-input) {
  border-radius: 0.5rem; /* rounded-lg */
  border-color: var(--color-slate-300);
  font-size: 0.875rem; /* text-sm */
}
:deep(.auth-input:focus),
:deep(.auth-input.p-focus) {
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.3); /* ring-indigo-200/50 */
  border-color: var(--color-indigo-400);
}
/* UUPM: Hover effect cho Auth Button */
.auth-btn:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); /* shadow-md */
  transform: translateY(-2px);
}
</style>
