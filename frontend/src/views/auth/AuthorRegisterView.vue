<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import Message from 'primevue/message'

const router = useRouter()
const toast = useToast()

const form = ref({
  pen_name: '',
  bio: '',
  bank_account_number: '',
  bank_name: '',
  bank_holder_name: '',
})
const identityDocument = ref(null)
const termsAccepted = ref(false)
const saving = ref(false)
const registrationStatus = ref(null)

const bankOptions = [
  { label: 'Vietcombank', value: 'Vietcombank' },
  { label: 'Techcombank', value: 'Techcombank' },
  { label: 'MB Bank', value: 'MB Bank' },
  { label: 'Ví MoMo', value: 'Ví MoMo' },
  { label: 'Ví ZaloPay', value: 'Ví ZaloPay' },
]

const checkStatus = async () => {
  try {
    const res = await apiClient.get('/api/author/status')
    if (res.data?.data) {
      registrationStatus.value = res.data.data.status
      if (res.data.data.status === 'active') {
        toast.add({ severity: 'success', summary: 'Thông báo', detail: 'Tài khoản tác giả của bạn đã hoạt động.', life: 3000 })
        router.push({ name: 'author-dashboard' })
      }
    }
  } catch (e) {
    console.warn('Không tải được trạng thái tác giả', e)
  }
}

const onFileSelect = (e) => {
  identityDocument.value = e.files?.[0] || null
}

const registerAuthor = async () => {
  if (!termsAccepted.value) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Bạn phải đồng ý với Điều khoản và Chính sách bản quyền.', life: 3000 })
    return
  }
  if (!form.value.pen_name || !form.value.bank_account_number || !form.value.bank_name || !form.value.bank_holder_name || !identityDocument.value) {
    toast.add({ severity: 'error', summary: 'Lỗi nhập liệu', detail: 'Vui lòng điền đầy đủ các thông tin bắt buộc và tải lên CCCD.', life: 3000 })
    return
  }

  saving.value = true
  try {
    const formData = new FormData()
    formData.append('pen_name', form.value.pen_name)
    formData.append('bio', form.value.bio)
    formData.append('bank_account_number', form.value.bank_account_number)
    formData.append('bank_name', form.value.bank_name)
    formData.append('bank_holder_name', form.value.bank_holder_name)
    formData.append('identity_document', identityDocument.value)

    await apiClient.post('/api/auth/register-author', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Gửi yêu cầu đăng ký tác giả thành công!', life: 4000 })
    registrationStatus.value = 'pending'
    router.push({ name: 'author-verify' })
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra.'
    toast.add({ severity: 'error', summary: 'Lỗi đăng ký', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  checkStatus()
})
</script>

<template>
  <div class="author-onboarding min-h-screen bg-slate-50 py-12 px-4 md:px-8">
    <Toast />
    
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Left Column: Value Prop -->
      <div class="lg:col-span-5 lg:sticky lg:top-24 flex flex-col gap-6">
        <div class="mb-4">
          <span class="inline-block bg-rose-100 text-rose-700 text-xs font-semibold px-3 py-1 rounded-full mb-3">
            Komibook Premium Creator
          </span>
          <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">
            Chia sẻ câu chuyện của bạn với thế giới.
          </h1>
          <p class="text-slate-600 text-base md:text-lg leading-relaxed">
            Trở thành Tác giả được xác thực trên nền tảng Komibook. Bắt đầu xuất bản tác phẩm, xây dựng cộng đồng độc giả và tạo nguồn thu nhập từ đam mê của bạn.
          </p>
        </div>

        <!-- Value Props -->
        <div class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200 flex gap-4 shadow-sm">
            <div class="bg-indigo-100 text-indigo-700 p-3 rounded-lg h-12 w-12 flex items-center justify-center flex-shrink-0">
              <i class="pi pi-wallet text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-slate-900 text-base">Nhuận bút hấp dẫn</h3>
              <p class="text-slate-500 text-sm mt-1">Nhận tỷ lệ chia sẻ doanh thu cao nhất thị trường. Thanh toán minh bạch hàng tháng qua tài khoản ngân hàng của bạn.</p>
            </div>
          </div>
          
          <div class="bg-white p-4 rounded-xl border border-slate-200 flex gap-4 shadow-sm">
            <div class="bg-indigo-100 text-indigo-700 p-3 rounded-lg h-12 w-12 flex items-center justify-center flex-shrink-0">
              <i class="pi pi-globe text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-slate-900 text-base">Xuất bản đa năng</h3>
              <p class="text-slate-500 text-sm mt-1">Tự sáng tác và phát hành ebook mới từng chương trực tiếp, đồng thời dễ dàng đăng bán những cuốn sách cũ của bạn.</p>
            </div>
          </div>

          <div class="bg-white p-4 rounded-xl border border-slate-200 flex gap-4 shadow-sm">
            <div class="bg-indigo-100 text-indigo-700 p-3 rounded-lg h-12 w-12 flex items-center justify-center flex-shrink-0">
              <i class="pi pi-shield text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-slate-900 text-base">Bảo vệ bản quyền (Social DRM)</h3>
              <p class="text-slate-500 text-sm mt-1">Nhúng chéo thông tin người mua (tên, email) dưới trang sách, giúp ngăn chặn sao chép trái phép hiệu quả.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Registration Card -->
      <div class="lg:col-span-7">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-md overflow-hidden">
          <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">Hồ sơ đăng ký Tác giả</h2>
            <span class="text-xs text-slate-500 font-medium">Hoàn thiện 3 bước</span>
          </div>

          <!-- Pending State Display -->
          <div v-if="registrationStatus === 'pending'" class="p-6 text-center">
            <i class="pi pi-hourglass text-amber-500 text-5xl mb-4"></i>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Hồ sơ đang chờ phê duyệt</h3>
            <p class="text-slate-600 mb-4 max-w-md mx-auto">
              Cảm ơn bạn đã gửi hồ sơ đăng ký. Ban quản trị KomiBook đang thẩm định hồ sơ định danh và thông tin thanh toán của bạn. Kết quả sẽ được phản hồi trong vòng 24h.
            </p>
            <Button label="Xác thực Số điện thoại" class="p-button-outlined p-button-sm" @click="router.push({ name: 'author-verify' })" />
          </div>

          <form v-else class="p-6 space-y-6" @submit.prevent="registerAuthor">
            <!-- Step 1: Profile -->
            <div>
              <div class="flex items-center gap-3 mb-4">
                <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center text-sm font-bold">1</span>
                <h3 class="text-base font-bold text-slate-800">Thông tin cá nhân</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-slate-700">Họ và tên (Pháp lý) <span class="text-rose-500">*</span></label>
                  <InputText v-model="form.bank_holder_name" placeholder="Trùng với thẻ ngân hàng" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-slate-700">Bút danh (Tác giả) <span class="text-rose-500">*</span></label>
                  <InputText v-model="form.pen_name" placeholder="Tên hiển thị công khai" class="w-full" />
                </div>
              </div>
              <div class="flex flex-col gap-2 mt-4">
                <label class="text-sm font-semibold text-slate-700">Tiểu sử tác giả</label>
                <Textarea v-model="form.bio" placeholder="Giới thiệu hành trình viết lách và phong cách sáng tác của bạn..." rows="3" autoResize class="w-full" />
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- Step 2: Identification -->
            <div>
              <div class="flex items-center gap-3 mb-4">
                <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center text-sm font-bold">2</span>
                <h3 class="text-base font-bold text-slate-800">Xác thực danh tính (KYC)</h3>
              </div>
              <p class="text-xs text-slate-500 mb-3">Tải lên ảnh chụp rõ nét CMND/CCCD hoặc Hộ chiếu để kích hoạt tài khoản xuất bản.</p>
              <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-slate-700">Tài liệu định danh CCCD <span class="text-rose-500">*</span></label>
                <FileUpload mode="basic" accept="image/*" :maxFileSize="5242880" @select="onFileSelect" chooseLabel="Chọn ảnh CCCD" class="w-full" />
                <span v-if="identityDocument" class="text-xs text-green-600 font-semibold mt-1">
                  <i class="pi pi-check mr-1"></i> Đã chọn file: {{ identityDocument.name }}
                </span>
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- Step 3: Payment Settings -->
            <div>
              <div class="flex items-center gap-3 mb-4">
                <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center text-sm font-bold">3</span>
                <h3 class="text-base font-bold text-slate-800">Cài đặt thanh toán đối soát</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-slate-700">Tên ngân hàng <span class="text-rose-500">*</span></label>
                  <Select v-model="form.bank_name" :options="bankOptions" optionLabel="label" optionValue="value" placeholder="Chọn ngân hàng" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-sm font-semibold text-slate-700">Số tài khoản ngân hàng <span class="text-rose-500">*</span></label>
                  <InputText v-model="form.bank_account_number" placeholder="Nhập số tài khoản thụ hưởng" class="w-full" />
                </div>
              </div>
            </div>

            <!-- Policy Terms and Button -->
            <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-4">
              <div class="flex items-start gap-2">
                <input v-model="termsAccepted" id="terms" type="checkbox" class="mt-1 w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                <label for="terms" class="text-xs text-slate-600 leading-normal">
                  Bằng việc đăng ký, tôi xác nhận đã đọc, hiểu rõ và đồng ý với 
                  <a href="#" class="text-indigo-600 hover:underline font-semibold">Điều khoản Tác giả</a> và 
                  <a href="#" class="text-indigo-600 hover:underline font-semibold">Chính sách Bản quyền</a> của Komibook.
                </label>
              </div>

              <Button type="submit" label="Gửi hồ sơ đăng ký tác giả" icon="pi pi-user-plus" class="w-full p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white" :loading="saving" />
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.author-onboarding {
  font-family: 'Inter', sans-serif;
}
:deep(.p-select) {
  border-radius: 8px;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>
