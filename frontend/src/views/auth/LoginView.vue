<template>
  <div class="flex items-center justify-center min-h-screen bg-surface-50 dark:bg-surface-900 px-4">
    <Card class="w-full max-w-[400px] shadow-lg rounded-xl overflow-hidden py-4">
      <template #title>
        <div class="text-center text-2xl font-bold text-surface-900 dark:text-surface-0 mb-2">Đăng Nhập</div>
        <p class="text-center text-surface-500 dark:text-surface-400 text-sm font-normal">Truy cập vào hệ thống</p>
      </template>
      
      <template #content>
        <form @submit.prevent="handleLogin" class="flex flex-col gap-5 mt-4">
          <div class="flex flex-col gap-2">
            <label for="email" class="font-medium text-surface-700 dark:text-surface-200">Email</label>
            <InputText id="email" v-model="email" type="email" autocomplete="username" placeholder="Nhập email" required autofocus />
          </div>

          <div class="flex flex-col gap-2">
            <label for="password" class="font-medium text-surface-700 dark:text-surface-200">Mật khẩu</label>
            <Password id="password" v-model="password" :feedback="false" toggleMask autocomplete="current-password" inputClass="w-full" placeholder="Nhập mật khẩu" required />
          </div>

          <Button type="submit" label="Đăng nhập" :loading="loading" class="w-full mt-2" />
        </form>
      </template>
    </Card>
    
    <Toast />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'

import Card from 'primevue/card'
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
