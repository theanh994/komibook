<template>
  <div class="min-h-screen bg-background">
    <div class="w-full px-gutter max-w-[1280px] mx-auto py-xl flex flex-col lg:flex-row gap-xl">
      
      <!-- Sidebar -->
      <UserSidebar :user="authStore.user" @avatar-click="$refs.avatarInput.click()" />
      <input type="file" ref="avatarInput" class="hidden" accept="image/*" @change="onAvatarSelected" />

      <!-- Main Content -->
      <main class="flex-1 space-y-lg">
        
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/30 soft-shadow overflow-hidden">
          <div class="p-lg md:p-xl border-b border-outline-variant/10">
            <h1 class="text-2xl font-black text-on-surface tracking-tight mb-2">Hồ sơ cá nhân</h1>
            <p class="text-sm text-on-surface-variant font-medium">Quản lý thông tin tài khoản và bảo mật của bạn.</p>
          </div>

          <!-- Tabs -->
          <div class="px-md pt-md flex gap-md overflow-x-auto no-scrollbar border-b border-outline-variant/10">
            <button 
              v-for="tab in tabs" 
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-lg py-md text-sm font-bold transition-all border-none bg-transparent cursor-pointer relative whitespace-nowrap"
              :class="activeTab === tab.id ? 'text-primary' : 'text-outline hover:text-on-surface'"
            >
              {{ tab.label }}
              <div v-if="activeTab === tab.id" class="absolute bottom-0 left-0 right-0 h-1 bg-primary rounded-t-full"></div>
            </button>
          </div>

          <div class="p-lg md:p-xl">
            <!-- Tab: Thông tin chung -->
            <div v-if="activeTab === 'general'" class="animate-fade-in">
              <form @submit.prevent="handleUpdateInfo" class="grid grid-cols-1 md:grid-cols-2 gap-xl">
                <div class="space-y-6">
                  <div class="space-y-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Email (Không thể thay đổi)</label>
                    <div class="relative">
                      <InputText v-model="infoForm.email" disabled class="w-full !pl-10 !rounded-2xl !bg-surface-container-high !border-none !text-outline" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">alternate_email</span>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Họ và tên</label>
                    <div class="relative">
                      <InputText v-model="infoForm.name" placeholder="Nhập họ và tên..." class="w-full !pl-10 !rounded-2xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Số điện thoại</label>
                    <div class="relative">
                      <InputText v-model="infoForm.phone" placeholder="Nhập số điện thoại..." class="w-full !pl-10 !rounded-2xl !border-outline-variant/40" />
                      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">call</span>
                    </div>
                  </div>
                </div>

                <div class="space-y-6">
                  <div class="space-y-2 h-full flex flex-col">
                    <label class="text-sm font-bold text-on-surface-variant ml-1">Địa chỉ cá nhân</label>
                    <div class="relative flex-1">
                      <Textarea v-model="infoForm.address" placeholder="Nhập địa chỉ của bạn..." class="w-full !pl-10 !rounded-2xl !border-outline-variant/40 h-full min-h-[150px]" />
                      <span class="material-symbols-outlined absolute left-3 top-4 text-outline text-[20px]">home</span>
                    </div>
                  </div>
                </div>

                <div class="md:col-span-2 pt-lg flex justify-end">
                  <button 
                    type="submit"
                    :disabled="loadingInfo"
                    class="bg-primary text-on-primary px-xl py-md rounded-2xl font-bold shadow-md hover:bg-primary/90 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                  >
                    <span v-if="loadingInfo" class="pi pi-spin pi-spinner mr-2"></span>
                    <span v-else class="material-symbols-outlined text-[20px]">save</span>
                    Lưu thông tin
                  </button>
                </div>
              </form>
            </div>

            <!-- Tab: Sổ địa chỉ -->
            <div v-if="activeTab === 'addresses'" class="animate-fade-in space-y-md">
              <div class="flex justify-between items-center mb-lg">
                <h3 class="text-lg font-bold text-on-surface">Địa chỉ giao hàng</h3>
                <button @click="openAddressModal()" class="bg-primary-container text-on-primary-container px-lg py-sm rounded-xl font-bold text-xs hover:opacity-80 transition-all border-none cursor-pointer flex items-center gap-2">
                  <span class="material-symbols-outlined text-[18px]">add</span>
                  Thêm mới
                </button>
              </div>

              <div v-if="loadingAddresses" class="py-xl flex justify-center">
                <i class="pi pi-spin pi-spinner text-3xl text-primary"></i>
              </div>
              
              <div v-else-if="addresses.length === 0" class="py-xl text-center space-y-md">
                <div class="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto text-outline">
                  <span class="material-symbols-outlined text-3xl">location_off</span>
                </div>
                <p class="text-sm text-outline font-medium">Bạn chưa có địa chỉ giao hàng nào.</p>
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <div v-for="addr in addresses" :key="addr.id" class="p-lg rounded-2xl border-2 transition-all group" :class="addr.is_default ? 'border-primary bg-primary-container/5' : 'border-outline-variant/20 hover:border-outline-variant/60'">
                  <div class="flex justify-between items-start mb-md">
                    <div class="flex flex-col">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-on-surface">{{ addr.receiver_name }}</span>
                        <span v-if="addr.is_default" class="px-2 py-0.5 bg-primary text-on-primary text-[10px] font-black uppercase tracking-wider rounded-md">Mặc định</span>
                      </div>
                      <span class="text-xs text-on-surface-variant font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">call</span>
                        {{ addr.phone }}
                      </span>
                    </div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button @click="openAddressModal(addr)" class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface-variant hover:text-primary transition-all border-none cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                      </button>
                      <button v-if="!addr.is_default" @click="confirmDeleteAddress(addr.id)" class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface-variant hover:text-error transition-all border-none cursor-pointer flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                      </button>
                    </div>
                  </div>
                  <p class="text-sm text-on-surface-variant leading-relaxed line-clamp-2">{{ addr.address }}</p>
                  <button v-if="!addr.is_default" @click="setDefaultAddress(addr.id)" class="mt-md text-[11px] font-black uppercase text-secondary hover:underline bg-transparent border-none cursor-pointer">Đặt làm mặc định</button>
                </div>
              </div>
            </div>

            <!-- Tab: VIP & Quyền lợi -->
            <div v-if="activeTab === 'membership'" class="animate-fade-in space-y-lg">
              <div class="p-6 rounded-3xl text-white relative overflow-hidden shadow-xl"
                :class="authStore.user?.membership_tier ? 'bg-gradient-to-br from-amber-500 via-yellow-600 to-amber-700' : 'bg-gradient-to-br from-slate-600 to-slate-800'"
              >
                <!-- Decorative Circle -->
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute right-10 top-10 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>

                <div class="flex justify-between items-start relative z-10">
                  <div>
                    <span class="text-xs uppercase tracking-widest font-black opacity-80">Thẻ Thành Viên</span>
                    <h2 class="text-2xl font-black mt-1">{{ authStore.user?.membership_tier?.name || 'Khách hàng Thân thiết' }}</h2>
                  </div>
                  <span class="material-symbols-outlined text-4xl opacity-90">
                    {{ authStore.user?.membership_tier ? 'workspace_premium' : 'person' }}
                  </span>
                </div>

                <div class="mt-8 flex justify-between items-end relative z-10">
                  <div>
                    <span class="text-[11px] uppercase tracking-wider opacity-70">Điểm tích lũy</span>
                    <div class="text-xl font-bold mt-0.5">{{ authStore.user?.points || 0 }} <span class="text-xs opacity-80">KomiPoints</span></div>
                  </div>
                  <div class="text-right">
                    <span class="text-[11px] uppercase tracking-wider opacity-70">Ưu đãi giảm giá</span>
                    <div class="text-xl font-black mt-0.5">{{ authStore.user?.membership_tier?.discount_percent || 0 }}%</div>
                  </div>
                </div>
              </div>

              <!-- VIP Benefits List -->
              <div class="bg-surface-container-low p-6 rounded-2xl border border-outline-variant/20 space-y-4">
                <h3 class="font-bold text-on-surface flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary">verified</span>
                  Quyền lợi đặc quyền của bạn
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-green-600 mt-0.5">check_circle</span>
                    <div>
                      <h4 class="text-sm font-bold text-on-surface">Chiết khấu trực tiếp</h4>
                      <p class="text-xs text-on-surface-variant">Giảm giá {{ authStore.user?.membership_tier?.discount_percent || 0 }}% trực tiếp trên mỗi hóa đơn khi thanh toán.</p>
                    </div>
                  </div>
                  <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-green-600 mt-0.5">check_circle</span>
                    <div>
                      <h4 class="text-sm font-bold text-on-surface">Tích lũy điểm tự động</h4>
                      <p class="text-xs text-on-surface-variant">Nhận 1 điểm tích lũy cho mỗi 10.000 VNĐ chi tiêu khi đơn hàng giao thành công.</p>
                    </div>
                  </div>
                </div>

                <div v-if="authStore.user?.membership_tier?.benefits" class="p-4 bg-primary/5 rounded-xl border border-primary/10 mt-2">
                  <p class="text-xs text-primary font-bold">Lợi ích bổ sung: {{ authStore.user?.membership_tier?.benefits }}</p>
                </div>
              </div>
            </div>

            <!-- Tab: Bảo mật -->
            <div v-if="activeTab === 'security'" class="w-full block">
              <div class="animate-fade-in py-lg flex flex-col items-center">
                <div class="w-full max-w-[480px]">
                  <div class="text-center mb-xl">
                    <div class="w-16 h-16 bg-error-container/20 text-error rounded-full flex items-center justify-center mx-auto mb-md">
                      <span class="material-symbols-outlined text-3xl">lock</span>
                    </div>
                    <h2 class="text-xl font-black text-on-surface mb-2">Thay đổi mật khẩu</h2>
                    <p class="text-sm text-on-surface-variant font-medium">Bạn nên sử dụng mật khẩu mạnh để bảo vệ tài khoản.</p>
                  </div>

                  <form @submit.prevent="handleUpdatePassword" class="space-y-6">
                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-bold text-on-surface-variant ml-1">Mật khẩu hiện tại</label>
                      <Password v-model="passwordForm.current_password" toggleMask placeholder="••••••••" class="w-full" inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4" :feedback="false" />
                    </div>

                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-bold text-on-surface-variant ml-1">Mật khẩu mới</label>
                      <Password v-model="passwordForm.new_password" toggleMask placeholder="Tối thiểu 8 ký tự" class="w-full" inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4" />
                    </div>

                    <div class="flex flex-col gap-2">
                      <label class="text-sm font-bold text-on-surface-variant ml-1">Xác nhận mật khẩu mới</label>
                      <Password v-model="passwordForm.new_password_confirmation" toggleMask placeholder="Nhập lại mật khẩu mới" class="w-full" inputClass="w-full !rounded-2xl !border-outline-variant/40 !py-3 !px-4" :feedback="false" />
                    </div>

                    <div class="pt-6">
                      <button 
                        type="submit"
                        :disabled="loadingPassword"
                        class="w-full bg-primary text-on-primary px-xl py-3.5 rounded-2xl font-bold shadow-md hover:opacity-90 transition-all active:scale-[0.98] disabled:opacity-50 flex items-center justify-center gap-2 border-none cursor-pointer"
                      >
                        <span v-if="loadingPassword" class="pi pi-spin pi-spinner mr-2"></span>
                        <span v-else class="material-symbols-outlined text-[20px]">security</span>
                        Cập nhật mật khẩu
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
    </div>

    <!-- Address Modal -->
    <Dialog v-model:visible="addressDialog" :header="isEditAddress ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ mới'" :modal="true" class="!rounded-3xl !border-none !shadow-2xl" :style="{width: '450px'}">
      <div class="flex flex-col gap-6 mt-4">
        <div class="space-y-2">
          <label class="text-sm font-bold text-on-surface-variant ml-1">Tên người nhận</label>
          <InputText v-model="addressForm.receiver_name" placeholder="Ví dụ: Nguyễn Văn A" class="w-full !rounded-xl !border-outline-variant/40" autofocus />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-bold text-on-surface-variant ml-1">Số điện thoại</label>
          <InputText v-model="addressForm.phone" placeholder="Ví dụ: 0901234567" class="w-full !rounded-xl !border-outline-variant/40" />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-bold text-on-surface-variant ml-1">Địa chỉ chi tiết</label>
          <Textarea v-model="addressForm.address" rows="3" placeholder="Số nhà, Tên đường..." class="w-full !rounded-xl !border-outline-variant/40 resize-none" />
        </div>
        <div class="flex items-center gap-3 p-md bg-surface-container-low rounded-2xl border border-outline-variant/20">
          <Checkbox v-model="addressForm.is_default" :binary="true" inputId="is_default" />
          <label for="is_default" class="text-sm font-bold text-on-surface cursor-pointer">Đặt làm địa chỉ mặc định</label>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-end pt-md">
          <button @click="addressDialog = false" class="px-lg py-sm rounded-xl text-sm font-bold text-outline hover:bg-surface-container-high transition-all border-none bg-transparent cursor-pointer">Hủy</button>
          <button @click="saveAddress" :loading="savingAddress" class="px-xl py-sm rounded-xl text-sm font-bold bg-primary text-on-primary shadow-md hover:bg-primary/90 transition-all active:scale-95 border-none cursor-pointer flex items-center gap-2">
            <span v-if="savingAddress" class="pi pi-spin pi-spinner"></span>
            Lưu địa chỉ
          </button>
        </div>
      </template>
    </Dialog>

    <Toast />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import apiClient from '@/services/axios'

import UserSidebar from '@/components/profile/UserSidebar.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'

const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const activeTab = ref('general')
const tabs = [
  { id: 'general', label: 'Thông tin chung' },
  { id: 'membership', label: 'Hạng VIP & Quyền lợi' },
  { id: 'addresses', label: 'Sổ địa chỉ' },
  { id: 'security', label: 'Bảo mật' }
]

// Forms
const loadingInfo = ref(false)
const infoForm = reactive({ email: '', name: '', phone: '', address: '' })

const loadingPassword = ref(false)
const passwordForm = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })

// Addresses
const addresses = ref([])
const loadingAddresses = ref(false)
const addressDialog = ref(false)
const isEditAddress = ref(false)
const savingAddress = ref(false)
const addressForm = ref({ id: null, receiver_name: '', phone: '', address: '', is_default: false })

onMounted(() => {
  if (authStore.user) {
    infoForm.email = authStore.user.email || ''
    infoForm.name = authStore.user.name || ''
    infoForm.phone = authStore.user.phone || ''
    infoForm.address = authStore.user.address || ''
  }
  fetchAddresses()
})

const fetchAddresses = async () => {
  loadingAddresses.value = true
  try {
    const res = await apiClient.get('/api/profile/addresses')
    addresses.value = res.data.data
  } catch(error) { console.error(error) }
  loadingAddresses.value = false
}

const openAddressModal = (addr = null) => {
  if (addr) {
    isEditAddress.value = true
    addressForm.value = { ...addr, is_default: !!addr.is_default }
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
  } finally { savingAddress.value = false }
}

const confirmDeleteAddress = (id) => {
  confirm.require({
    message: 'Bạn có chắc chắn muốn xóa địa chỉ này?',
    header: 'Xác nhận xóa',
    icon: 'pi pi-exclamation-triangle text-error',
    acceptLabel: 'Xóa',
    rejectLabel: 'Hủy',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await apiClient.delete(`/api/profile/addresses/${id}`)
        toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Xóa địa chỉ thành công', life: 3000 })
        fetchAddresses()
      } catch(e) { toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể xóa', life: 3000 }) }
    }
  })
}

const setDefaultAddress = async (id) => {
  try {
    await apiClient.patch(`/api/profile/addresses/${id}/default`)
    fetchAddresses()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật mặc định', life: 3000 })
  } catch(e) { toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra', life: 3000 }) }
}

const handleUpdateInfo = async () => {
  loadingInfo.value = true
  try {
    await authStore.updateProfile({ ...infoForm })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Cập nhật thông tin thành công', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể cập nhật thông tin', life: 3000 })
  } finally { loadingInfo.value = false }
}

const handleUpdatePassword = async () => {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu xác nhận không khớp', life: 3000 })
    return
  }
  loadingPassword.value = true
  try {
    await authStore.updatePassword({ ...passwordForm })
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đổi mật khẩu thành công', life: 3000 })
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    passwordForm.new_password_confirmation = ''
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Mật khẩu hiện tại không đúng', life: 3000 })
  } finally { loadingPassword.value = false }
}

const onAvatarSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return
  event.target.value = ''
  const formData = new FormData()
  formData.append('avatar', file)
  try {
    await apiClient.post('/api/profile/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    await authStore.fetchUser()
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đổi ảnh đại diện thành công', life: 3000 })
  } catch(e) { toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải lên ảnh', life: 5000 }) }
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.4s ease-out forwards;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
