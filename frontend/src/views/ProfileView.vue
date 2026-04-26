<template>
  <div class="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Header -->
      <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Hồ sơ cá nhân</h1>
        <p class="text-slate-500 mt-2">Quản lý thông tin và bảo mật tài khoản của bạn.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Card 1: Thông tin chung -->
        <div class="bg-white rounded-xl shadow-sm shadow-slate-200/50 border border-slate-200/60 p-6 md:p-8 flex flex-col h-full">
          <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-800">Thông tin chung</h2>
            <p class="text-sm text-slate-500">Cập nhật họ tên, số điện thoại và địa chỉ của bạn.</p>
          </div>
          
          <form @submit.prevent="handleUpdateInfo" class="flex flex-col gap-5 flex-grow">
            <div class="flex flex-col gap-1.5">
              <label for="email" class="text-sm font-medium text-slate-700">Email</label>
              <InputText
                id="email"
                v-model="infoForm.email"
                disabled
                class="profile-input bg-slate-50 opacity-70"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="name" class="text-sm font-medium text-slate-700">Họ và tên</label>
              <InputText
                id="name"
                v-model="infoForm.name"
                required
                placeholder="Ví dụ: Nguyễn Văn A"
                class="profile-input"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="phone" class="text-sm font-medium text-slate-700">Số điện thoại</label>
              <InputText
                id="phone"
                v-model="infoForm.phone"
                placeholder="Ví dụ: 0901234567"
                class="profile-input"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="address" class="text-sm font-medium text-slate-700">Địa chỉ</label>
              <Textarea
                id="address"
                v-model="infoForm.address"
                rows="3"
                placeholder="Địa chỉ giao hàng mặc định"
                class="profile-input resize-none"
              />
            </div>

            <div class="mt-auto pt-4">
              <Button
                type="submit"
                label="Lưu thông tin"
                :loading="loadingInfo"
                class="profile-btn w-full !bg-gradient-to-b !from-indigo-500 !to-indigo-600 hover:!from-indigo-600 hover:!to-indigo-700 !text-white !border-none !rounded-lg !shadow-sm !font-medium !text-sm !py-2.5 transition-all duration-300 ease-out"
              />
            </div>
          </form>
        </div>

        <!-- Card 2: Đổi mật khẩu -->
        <div class="bg-white rounded-xl shadow-sm shadow-slate-200/50 border border-slate-200/60 p-6 md:p-8 flex flex-col h-full">
          <div class="mb-6">
            <h2 class="text-lg font-semibold text-slate-800">Đổi mật khẩu</h2>
            <p class="text-sm text-slate-500">Đảm bảo tài khoản của bạn sử dụng mật khẩu dài và an toàn.</p>
          </div>
          
          <form @submit.prevent="handleUpdatePassword" class="flex flex-col gap-5 flex-grow">
            <div class="flex flex-col gap-1.5">
              <label for="current_password" class="text-sm font-medium text-slate-700">Mật khẩu hiện tại</label>
              <Password
                id="current_password"
                v-model="passwordForm.current_password"
                :feedback="false"
                toggleMask
                placeholder="Nhập mật khẩu hiện tại"
                required
                inputClass="w-full profile-input"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="new_password" class="text-sm font-medium text-slate-700">Mật khẩu mới</label>
              <Password
                id="new_password"
                v-model="passwordForm.new_password"
                toggleMask
                placeholder="Tối thiểu 8 ký tự"
                required
                inputClass="w-full profile-input"
              />
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="new_password_confirmation" class="text-sm font-medium text-slate-700">Xác nhận mật khẩu mới</label>
              <Password
                id="new_password_confirmation"
                v-model="passwordForm.new_password_confirmation"
                :feedback="false"
                toggleMask
                placeholder="Nhập lại mật khẩu mới"
                required
                inputClass="w-full profile-input"
              />
            </div>

            <div class="mt-auto pt-4">
              <Button
                type="submit"
                label="Đổi mật khẩu"
                :loading="loadingPassword"
                class="profile-btn w-full !bg-white hover:!bg-slate-50 !text-slate-800 !border !border-slate-300 !rounded-lg !shadow-sm !font-medium !text-sm !py-2.5 transition-all duration-300 ease-out"
              />
            </div>
          </form>
        </div>

      </div>
    </div>
    <Toast />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'

import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Toast from 'primevue/toast'

const authStore = useAuthStore()
const toast = useToast()

const loadingInfo = ref(false)
const loadingPassword = ref(false)

const infoForm = reactive({
  email: '',
  name: '',
  phone: '',
  address: ''
})

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

onMounted(() => {
  if (authStore.user) {
    infoForm.email = authStore.user.email || ''
    infoForm.name = authStore.user.name || ''
    infoForm.phone = authStore.user.phone || ''
    infoForm.address = authStore.user.address || ''
  }
})

const handleUpdateInfo = async () => {
  loadingInfo.value = true
  try {
    const payload = {
      name: infoForm.name,
      phone: infoForm.phone,
      address: infoForm.address
    }
    const res = await authStore.updateProfile(payload)
    toast.add({ severity: 'success', summary: 'Thành công', detail: res.message || 'Cập nhật thông tin thành công.', life: 3000 })
  } catch (error) {
    let errorMessage = 'Có lỗi xảy ra.'
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.response?.data?.errors) {
      errorMessage = Object.values(error.response.data.errors)[0][0]
    }
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMessage, life: 3000 })
  } finally {
    loadingInfo.value = false
  }
}

const handleUpdatePassword = async () => {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp.', life: 3000 })
    return
  }

  loadingPassword.value = true
  try {
    const res = await authStore.updatePassword({ ...passwordForm })
    toast.add({ severity: 'success', summary: 'Thành công', detail: res.message || 'Đổi mật khẩu thành công.', life: 3000 })
    // Reset form
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    passwordForm.new_password_confirmation = ''
  } catch (error) {
    let errorMessage = 'Có lỗi xảy ra.'
    if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    } else if (error.response?.data?.errors) {
      errorMessage = Object.values(error.response.data.errors)[0][0]
    }
    toast.add({ severity: 'error', summary: 'Lỗi', detail: errorMessage, life: 3000 })
  } finally {
    loadingPassword.value = false
  }
}
</script>

<style scoped>
/* UUPM: Focus ring cho PrimeVue inputs */
:deep(.profile-input) {
  border-radius: 0.5rem;
  border-color: var(--color-slate-300);
  font-size: 0.875rem;
}
:deep(.profile-input:focus),
:deep(.profile-input.p-focus) {
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.3);
  border-color: var(--color-indigo-400);
}

/* UUPM: Hover effect cho Button */
.profile-btn:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}
</style>
