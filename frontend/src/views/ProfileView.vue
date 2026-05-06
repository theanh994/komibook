<template>
  <div class="min-h-screen bg-slate-50 py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Header -->
      <div class="flex items-center gap-6 mb-8">
        <!-- Avatar Upload -->
        <div class="relative group cursor-pointer" @click="$refs.avatarInput.click()">
          <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-sm">
            <img v-if="authStore.user?.avatar" :src="avatarUrl" alt="Avatar" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full bg-indigo-100 flex items-center justify-center text-indigo-500 text-3xl font-semibold">
              {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
            </div>
          </div>
          <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="pi pi-camera text-white text-xl"></i>
          </div>
          <input type="file" ref="avatarInput" class="hidden" accept="image/*" @change="onAvatarSelected" />
        </div>
        
        <div>
          <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Hồ sơ cá nhân</h1>
          <p class="text-slate-500 mt-2">Quản lý thông tin và bảo mật tài khoản của bạn.</p>
        </div>
      </div>

      <TabView>
        <TabPanel header="Hồ sơ">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
            
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
        </TabPanel>

        <TabPanel header="Sổ địa chỉ">
          <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-6 mt-4">
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-lg font-semibold text-slate-800">Danh sách địa chỉ</h2>
              <Button label="Thêm địa chỉ" icon="pi pi-plus" size="small" @click="openAddressModal()" />
            </div>

            <div v-if="loadingAddresses" class="text-center py-8 text-slate-500">
              <i class="pi pi-spin pi-spinner text-2xl"></i>
            </div>
            
            <div v-else-if="addresses.length === 0" class="text-center py-8 text-slate-500">
              Bạn chưa thêm địa chỉ nào.
            </div>

            <div v-else class="space-y-4">
              <div v-for="addr in addresses" :key="addr.id" class="border border-slate-200 rounded-lg p-4 flex justify-between items-start transition hover:border-indigo-300">
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-slate-800">{{ addr.receiver_name }}</span>
                    <span class="text-slate-500 text-sm">| {{ addr.phone }}</span>
                    <span v-if="addr.is_default" class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium ml-2">Mặc định</span>
                  </div>
                  <p class="text-slate-600 text-sm">{{ addr.address }}</p>
                </div>
                <div class="flex gap-2">
                  <Button v-if="!addr.is_default" label="Đặt mặc định" class="p-button-text p-button-sm p-button-secondary" @click="setDefaultAddress(addr.id)" />
                  <Button icon="pi pi-pencil" class="p-button-text p-button-sm" @click="openAddressModal(addr)" />
                  <Button icon="pi pi-trash" class="p-button-text p-button-sm p-button-danger" @click="confirmDeleteAddress(addr.id)" />
                </div>
              </div>
            </div>
          </div>
        </TabPanel>
      </TabView>
    </div>

    <!-- Address Modal -->
    <Dialog v-model:visible="addressDialog" :header="isEditAddress ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ mới'" :modal="true" :style="{width: '450px'}">
      <div class="flex flex-col gap-4 mt-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-slate-700">Tên người nhận</label>
          <InputText v-model="addressForm.receiver_name" placeholder="Ví dụ: Nguyễn Văn A" autofocus />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-slate-700">Số điện thoại</label>
          <InputText v-model="addressForm.phone" placeholder="Ví dụ: 0901234567" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-slate-700">Địa chỉ cụ thể</label>
          <Textarea v-model="addressForm.address" rows="3" placeholder="Số nhà, Tên đường..." class="resize-none" />
        </div>
        <div class="flex items-center gap-2 mt-2">
          <Checkbox v-model="addressForm.is_default" :binary="true" inputId="is_default" />
          <label for="is_default" class="text-sm text-slate-700 cursor-pointer">Đặt làm địa chỉ mặc định</label>
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" icon="pi pi-times" class="p-button-text" @click="addressDialog = false" />
        <Button label="Lưu" icon="pi pi-check" :loading="savingAddress" @click="saveAddress" autofocus />
      </template>
    </Dialog>

    <Toast />
    <ConfirmDialog></ConfirmDialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'

const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

// Thông tin chung & Đổi mật khẩu
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

// Avatar
const avatarUrl = computed(() => {
  if (!authStore.user?.avatar) return ''
  
  if (authStore.user.avatar.startsWith('http')) return authStore.user.avatar
  
  // Trong môi trường dev với Laravel Herd, backend thường được host ở http://komibook.test
  // Nếu đã build prod, cần lấy theo VITE_API_URL
  const baseUrl = import.meta.env.VITE_API_URL || 'http://komibook.test'
  
  // URL cho local storage
  return `${baseUrl}/storage/${authStore.user.avatar}`
})

const onAvatarSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  // Xóa giá trị input để có thể chọn lại cùng 1 file
  event.target.value = ''
  
  const formData = new FormData()
  formData.append('avatar', file)
  
  try {
    const res = await apiClient.post('/api/profile/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    // Cập nhật lại user trong store
    await authStore.fetchUser()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đổi ảnh đại diện thành công', life: 3000 })
  } catch(e) {
    let msg = 'Không thể tải lên ảnh đại diện'
    if (e.response?.data?.errors?.avatar) {
      msg = e.response.data.errors.avatar[0]
    } else if (e.response?.data?.message) {
      msg = e.response.data.message
    }
    toast.add({ severity: 'error', summary: 'Lỗi', detail: msg, life: 5000 })
  }
}

// Sổ địa chỉ
const addresses = ref([])
const loadingAddresses = ref(false)
const addressDialog = ref(false)
const isEditAddress = ref(false)
const savingAddress = ref(false)
const addressForm = ref({ id: null, receiver_name: '', phone: '', address: '', is_default: false })

const fetchAddresses = async () => {
  loadingAddresses.value = true
  try {
    const res = await apiClient.get('/api/profile/addresses')
    addresses.value = res.data.data
  } catch(error) {
    console.error(error)
  }
  loadingAddresses.value = false
}

const openAddressModal = (addr = null) => {
  if (addr) {
    isEditAddress.value = true
    addressForm.value = { ...addr }
  } else {
    isEditAddress.value = false
    addressForm.value = { id: null, receiver_name: '', phone: '', address: '', is_default: false }
  }
  addressDialog.value = true
}

const saveAddress = async () => {
  if (!addressForm.value.receiver_name || !addressForm.value.phone || !addressForm.value.address) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng điền đầy đủ thông tin', life: 3000 })
    return
  }

  savingAddress.value = true
  try {
    if (isEditAddress.value) {
      await apiClient.put(`/api/profile/addresses/${addressForm.value.id}`, addressForm.value)
    } else {
      await apiClient.post('/api/profile/addresses', addressForm.value)
    }
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lưu địa chỉ', life: 3000 })
    addressDialog.value = false
    fetchAddresses()
  } catch(e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
  savingAddress.value = false
}

const confirmDeleteAddress = (id) => {
  confirm.require({
    message: 'Bạn có chắc chắn muốn xóa địa chỉ này?',
    header: 'Xác nhận',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Xóa',
    rejectLabel: 'Hủy',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await apiClient.delete(`/api/profile/addresses/${id}`)
        toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Xóa địa chỉ thành công', life: 3000 })
        fetchAddresses()
      } catch(e) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa địa chỉ', life: 3000 })
      }
    }
  })
}

const setDefaultAddress = async (id) => {
  try {
    await apiClient.patch(`/api/profile/addresses/${id}/default`)
    fetchAddresses()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật địa chỉ mặc định', life: 3000 })
  } catch(e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 })
  }
}

// Khởi tạo
onMounted(() => {
  if (authStore.user) {
    infoForm.email = authStore.user.email || ''
    infoForm.name = authStore.user.name || ''
    infoForm.phone = authStore.user.phone || ''
    infoForm.address = authStore.user.address || ''
  }
  fetchAddresses()
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

:deep(.p-tabview-nav) {
  background: transparent;
  border-bottom: 2px solid var(--color-slate-200);
}
:deep(.p-tabview-title) {
  font-weight: 600;
}
</style>
