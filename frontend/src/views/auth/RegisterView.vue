<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-50 px-4">
    <!-- Auth Card -->
    <div class="w-full max-w-[440px] bg-white rounded-xl shadow-sm shadow-slate-200/50 border border-slate-200/60 p-8">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">Đăng Ký Tài Khoản</h1>
        <p class="text-slate-500 text-sm mt-1.5">Gia nhập cộng đồng KomiBook ngay hôm nay</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleRegister" class="flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
          <label for="name" class="text-sm font-medium text-slate-700">Họ và tên</label>
          <InputText
            id="name"
            v-model="form.name"
            placeholder="Nguyễn Văn A"
            required
            autofocus
            class="auth-input"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="email" class="text-sm font-medium text-slate-700">Email</label>
          <InputText
            id="email"
            v-model="form.email"
            type="email"
            placeholder="you@example.com"
            required
            class="auth-input"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="password" class="text-sm font-medium text-slate-700">Mật khẩu</label>
          <Password
            id="password"
            v-model="form.password"
            toggleMask
            placeholder="Tối thiểu 8 ký tự"
            required
            inputClass="w-full auth-input"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label for="password_confirmation" class="text-sm font-medium text-slate-700">Xác nhận mật khẩu</label>
          <Password
            id="password_confirmation"
            v-model="form.password_confirmation"
            :feedback="false"
            toggleMask
            placeholder="Nhập lại mật khẩu"
            required
            inputClass="w-full auth-input"
          />
        </div>

        <Button
          type="submit"
          label="Đăng ký ngay"
          :loading="loading"
          class="auth-btn w-full mt-1 !bg-gradient-to-b !from-indigo-500 !to-indigo-600 hover:!from-indigo-600 hover:!to-indigo-700 !text-white !border-none !rounded-lg !shadow-sm !font-medium !text-sm !py-2.5 transition-all duration-300 ease-out"
        />
      </form>

      <!-- Divider + Navigation -->
      <div class="mt-6 pt-5 border-t border-slate-100 text-center">
        <p class="text-sm text-slate-500">
          Đã có tài khoản?
          <router-link
            to="/login"
            class="text-indigo-600 hover:text-indigo-700 font-medium transition-colors duration-200"
          >
            Đăng nhập
          </router-link>
        </p>
      </div>
    </div>

    <Toast />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'


import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)

const handleRegister = async () => {
  if (form.password !== form.password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }

  loading.value = true
  try {
    await authStore.register({ ...form })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng ký tài khoản thành công! Đang chuyển hướng...', life: 3000 })
    
    setTimeout(() => {
      router.push({ name: 'dashboard' })
    }, 1000)
  } catch (error) {
    let errorMessage = 'Có lỗi xảy ra trong quá trình đăng ký.'
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.response?.data?.errors) {
      // Lấy lỗi đầu tiên từ validation
      errorMessage = Object.values(error.response.data.errors)[0][0]
    }
    toast.add({ severity: 'error', summary: 'Lỗi đăng ký', detail: errorMessage, life: 3000 })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* UUPM: Focus ring cho PrimeVue inputs */
:deep(.auth-input) {
  border-radius: 0.5rem;
  border-color: var(--color-slate-300);
  font-size: 0.875rem;
}
:deep(.auth-input:focus),
:deep(.auth-input.p-focus) {
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.3);
  border-color: var(--color-indigo-400);
}
/* UUPM: Hover effect cho Auth Button */
.auth-btn:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}
</style>
