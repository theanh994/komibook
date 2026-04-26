<template>
  <div class="flex items-center justify-center min-h-screen bg-surface-50 dark:bg-surface-900 px-4">
    <Card class="w-full max-w-[450px] shadow-lg rounded-xl overflow-hidden py-4">
      <template #title>
        <div class="text-center text-2xl font-bold text-surface-900 dark:text-surface-0 mb-2">Đăng Ký Tài Khoản</div>
        <p class="text-center text-surface-500 dark:text-surface-400 text-sm font-normal">Gia nhập cộng đồng KomiBook ngay hôm nay</p>
      </template>
      
      <template #content>
        <form @submit.prevent="handleRegister" class="flex flex-col gap-5 mt-4">
          <div class="flex flex-col gap-2">
            <label for="name" class="font-medium text-surface-700 dark:text-surface-200">Họ và tên</label>
            <InputText id="name" v-model="form.name" placeholder="Nhập họ và tên" required autofocus />
          </div>

          <div class="flex flex-col gap-2">
            <label for="email" class="font-medium text-surface-700 dark:text-surface-200">Email</label>
            <InputText id="email" v-model="form.email" type="email" placeholder="Nhập địa chỉ email" required />
          </div>

          <div class="flex flex-col gap-2">
            <label for="password" class="font-medium text-surface-700 dark:text-surface-200">Mật khẩu</label>
            <Password id="password" v-model="form.password" toggleMask placeholder="Nhập mật khẩu" required inputClass="w-full" />
          </div>

          <div class="flex flex-col gap-2">
            <label for="password_confirmation" class="font-medium text-surface-700 dark:text-surface-200">Xác nhận mật khẩu</label>
            <Password id="password_confirmation" v-model="form.password_confirmation" :feedback="false" toggleMask placeholder="Nhập lại mật khẩu" required inputClass="w-full" />
          </div>

          <Button type="submit" label="Đăng ký ngay" :loading="loading" class="w-full mt-2" />
          
          <div class="text-center text-sm text-surface-600 dark:text-surface-400 mt-2">
            Đã có tài khoản? 
            <router-link to="/login" class="text-primary font-bold hover:underline">Đăng nhập</router-link>
          </div>
        </form>
      </template>
    </Card>
    
    <Toast />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'

import Card from 'primevue/card'
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
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.', life: 3000 })
    
    setTimeout(() => {
      router.push({ name: 'login' })
    }, 2000)
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
