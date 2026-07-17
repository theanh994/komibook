<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Select from 'primevue/select'
import Slider from 'primevue/slider'
import Message from 'primevue/message'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = route.params.bookId
const bookTitle = ref('Tác phẩm tự viết')
const loading = ref(true)
const saving = ref(false)

const drmSettings = ref({
  copyright_number: '',
  copyright_owner: '',
  social_drm: true,
  hard_drm: false,
  copy_limit_percent: 10,
  allow_printing: false,
  license_type: 'all_rights_reserved',
})

const licenseOptions = [
  { label: 'Giữ toàn quyền bản quyền (All Rights Reserved)', value: 'all_rights_reserved' },
  { label: 'Cấp phép CC BY (Ghi nhận tác giả)', value: 'cc_by' },
  { label: 'Cấp phép CC BY-NC (Phi thương mại)', value: 'cc_by_nc' },
  { label: 'Cấp phép CC BY-ND (Không phái sinh)', value: 'cc_by_nd' },
]

const fetchDrm = async () => {
  loading.value = true
  try {
    const bookRes = await apiClient.get(`/api/books`)
    const b = bookRes.data?.data?.find(item => item.id == bookId)
    if (b) {
      bookTitle.value = b.title
    }

    const res = await apiClient.get(`/api/vendor/books/${bookId}/drm-settings`)
    if (res.data?.status === 'success' && res.data.data) {
      // populate
      drmSettings.value = {
        ...res.data.data,
        social_drm: !!res.data.data.social_drm,
        hard_drm: !!res.data.data.hard_drm,
        allow_printing: !!res.data.data.allow_printing,
      }
    }
  } catch (e) {
    console.error('Không tải được cấu hình DRM', e)
    toast.add({ severity: 'error', summary: 'Lỗi tải dữ liệu', detail: 'Không thể kết nối API cấu hình bản quyền.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const saveDrm = async () => {
  saving.value = true
  try {
    const res = await apiClient.put(`/api/vendor/books/${bookId}/drm-settings`, {
      copyright_number: drmSettings.value.copyright_number,
      copyright_owner: drmSettings.value.copyright_owner,
      social_drm: drmSettings.value.social_drm,
      hard_drm: drmSettings.value.hard_drm,
      copy_limit_percent: drmSettings.value.copy_limit_percent,
      allow_printing: drmSettings.value.allow_printing,
      license_type: drmSettings.value.license_type,
    })

    if (res.data?.status === 'success') {
      toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Cập nhật cấu hình DRM & Bản quyền thành công.', life: 3000 })
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Có lỗi xảy ra.'
    toast.add({ severity: 'error', summary: 'Không thể lưu', detail: msg, life: 3500 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchDrm()
})
</script>

<template>
  <div class="drm-settings min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" class="p-button-text p-button-secondary p-button-sm" @click="router.push({ name: 'author-dashboard' })" />
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Thiết lập bản quyền & DRM</h1>
          <p class="text-slate-500 text-sm mt-1">Cấu hình các lớp bảo vệ tệp tin và quyền hạn đọc thử đối với tác phẩm: <strong class="text-indigo-600 font-bold">{{ bookTitle }}</strong></p>
        </div>
      </div>
      <div>
        <Button label="Lưu thiết lập" icon="pi pi-save" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" :loading="saving" @click="saveDrm" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Left Column: Protection Features -->
      <div class="lg:col-span-8 space-y-6">
        
        <!-- DRM Protection -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
          <div class="flex items-center gap-3 mb-2 border-b border-slate-100 pb-4">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
              <i class="pi pi-shield"></i>
            </div>
            <h3 class="font-bold text-slate-800 text-base">Lớp bảo vệ kỹ thuật số (DRM)</h3>
          </div>

          <!-- Social DRM -->
          <div class="flex justify-between items-start gap-4">
            <div class="flex-grow">
              <h4 class="font-bold text-slate-800 text-sm">Đóng dấu bản quyền ngầm (Social DRM)</h4>
              <p class="text-slate-500 text-xs mt-1 leading-relaxed">
                Tự động nhúng thông tin mua hàng (họ tên, email) chìm dưới các trang sách. Giúp truy vết nguồn gốc và răn đe chia sẻ lậu mà không làm ảnh hưởng đến trải nghiệm của người dùng.
              </p>
            </div>
            <ToggleSwitch v-model="drmSettings.social_drm" />
          </div>

          <hr class="border-slate-100" />

          <!-- Hard DRM -->
          <div class="flex justify-between items-start gap-4">
            <div class="flex-grow">
              <h4 class="font-bold text-slate-800 text-sm">Mã hóa tệp tin (Hard DRM)</h4>
              <p class="text-slate-500 text-xs mt-1 leading-relaxed">
                Mã hóa bảo vệ tệp tin gốc cấp độ cao. Độc giả chỉ có thể đọc sách trên ứng dụng Komibook hoặc các trình duyệt được ủy quyền. Ngăn chặn triệt để hành vi tải xuống tệp PDF thô.
              </p>
              <Message v-if="drmSettings.hard_drm" severity="warn" class="mt-2 text-xs" :closable="false">
                Lưu ý: Có thể làm giảm tính tiện ích đối với một số dòng máy đọc sách ngoại tuyến cũ.
              </Message>
            </div>
            <ToggleSwitch v-model="drmSettings.hard_drm" />
          </div>
        </div>

        <!-- Interactive Restrictions -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
          <div class="flex items-center gap-3 mb-2 border-b border-slate-100 pb-4">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
              <i class="pi pi-lock-open"></i>
            </div>
            <h3 class="font-bold text-slate-800 text-base">Giới hạn tương tác độc giả</h3>
          </div>

          <!-- Allow Printing -->
          <div class="flex justify-between items-start gap-4">
            <div class="flex-grow">
              <h4 class="font-bold text-slate-800 text-sm">Cho phép in ấn trang sách</h4>
              <p class="text-slate-500 text-xs mt-1 leading-relaxed">
                Cho phép người mua thực hiện in ấn các trang của tác phẩm. Khi bật tùy chọn này, độc giả phải đồng ý với bản cam kết tôn trọng quyền sở hữu trí tuệ trước khi in.
              </p>
            </div>
            <ToggleSwitch v-model="drmSettings.allow_printing" />
          </div>

          <hr class="border-slate-100" />

          <!-- Copy Limit -->
          <div>
            <h4 class="font-bold text-slate-800 text-sm mb-2">Giới hạn sao chép nội dung ({{ drmSettings.copy_limit_percent }}%)</h4>
            <p class="text-slate-500 text-xs leading-relaxed mb-4">
              Tỷ lệ phần trăm tối đa văn bản mà độc giả được phép bôi đen sao chép trong suốt thời gian đọc tác phẩm.
            </p>
            <Slider v-model="drmSettings.copy_limit_percent" :min="0" :max="100" class="w-full h-2 bg-slate-200 rounded-lg" />
          </div>
        </div>
      </div>

      <!-- Right Column: Copyright Legal Registration -->
      <div class="lg:col-span-4 space-y-6">
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-6">
          <div class="flex items-center gap-3 mb-2 border-b border-slate-100 pb-4">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
              <i class="pi pi-file"></i>
            </div>
            <h3 class="font-bold text-slate-800 text-base">Đăng ký Bản quyền</h3>
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-slate-600">Mã số đăng ký sở hữu trí tuệ <span class="text-rose-500">*</span></label>
            <InputText v-model="drmSettings.copyright_number" placeholder="Nhập số đăng ký bản quyền chính thức" class="w-full text-sm" />
            <span class="text-[10px] text-slate-400">Hệ thống chỉ phê duyệt mở bán sách ebook tự viết khi đã được khai báo số đăng ký bản quyền.</span>
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-slate-600">Chủ sở hữu quyền tác giả</label>
            <InputText v-model="drmSettings.copyright_owner" placeholder="Tên tác giả hoặc tổ chức bảo hộ" class="w-full text-sm" />
          </div>

          <div class="flex flex-col gap-2">
            <label class="text-xs font-semibold text-slate-600">Loại giấy phép phân phối</label>
            <Select v-model="drmSettings.license_type" :options="licenseOptions" optionLabel="label" optionValue="value" placeholder="Chọn giấy phép" class="w-full" />
          </div>

          <div class="bg-slate-50 p-4 border border-slate-200 rounded-xl">
            <h4 class="text-xs font-bold text-slate-700 mb-1 flex items-center gap-1">
              <i class="pi pi-info-circle text-indigo-600"></i> Khước từ trách nhiệm pháp lý
            </h4>
            <p class="text-[10px] text-slate-500 leading-normal">
              Các thiết lập DRM và số đăng ký bản quyền được lưu trữ nhằm phục vụ việc kiểm tra sở hữu tác phẩm nội bộ trên Komibook. Tác giả tự chịu trách nhiệm pháp lý trước mọi tranh chấp bản quyền thực tế phát sinh.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.drm-settings {
  font-family: 'Inter', sans-serif;
}
:deep(.p-select) {
  border-radius: 8px;
}
:deep(.p-inputtext) {
  border-radius: 8px;
}
</style>
