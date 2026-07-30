<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Slider from 'primevue/slider'
import ToggleSwitch from 'primevue/toggleswitch'

const route = useRoute()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const settings = ref({ social_drm: true, hard_drm: false, copy_limit_percent: 10, allow_printing: false, license_type: 'all_rights_reserved' })
const licenseOptions = [
  { label: 'All Rights Reserved', value: 'all_rights_reserved' },
  { label: 'CC BY', value: 'cc_by' },
  { label: 'CC BY-NC', value: 'cc_by_nc' },
  { label: 'CC BY-ND', value: 'cc_by_nd' },
]

const fetchDrm = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await apiClient.get(`/api/vendor/books/${route.params.bookId}/drm-settings`)
    settings.value = { ...settings.value, ...response.data.data }
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải cấu hình DRM.'
  } finally {
    loading.value = false
  }
}

const saveDrm = async () => {
  saving.value = true
  try {
    await apiClient.put(`/api/vendor/books/${route.params.bookId}/drm-settings`, settings.value)
    toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Cấu hình bảo vệ kỹ thuật đã được cập nhật.', life: 3000 })
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: exception.response?.data?.message || 'Không thể lưu DRM.', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(fetchDrm)
</script>

<template>
  <section class="min-w-0 bg-slate-50 p-4 md:p-10" aria-labelledby="drm-title">
    <div class="mx-auto min-w-0 max-w-4xl space-y-6">
      <header><h1 id="drm-title" class="text-2xl font-black text-slate-900 sm:text-3xl">Bảo vệ kỹ thuật DRM</h1><p class="mt-2 text-sm text-slate-600">DRM chỉ kiểm soát kỹ thuật đọc; không phải bằng chứng hoặc phê duyệt bản quyền.</p></header>
      <div v-if="loading" role="status" aria-live="polite" class="text-slate-500">Đang tải...</div>
      <div v-else-if="error" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 sm:p-6">{{ error }} <Button label="Thử lại" class="mt-3 min-h-11 sm:ml-3 sm:mt-0" @click="fetchDrm" /></div>
      <div v-else class="min-w-0 space-y-6 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <label class="flex min-h-11 flex-col justify-between gap-3 sm:flex-row sm:items-center"><span><strong>Social DRM</strong><small class="mt-1 block text-slate-500">Watermark truy vết bản đọc.</small></span><ToggleSwitch v-model="settings.social_drm" /></label>
        <label class="flex min-h-11 flex-col justify-between gap-3 sm:flex-row sm:items-center"><span><strong>Mã hóa tệp</strong><small class="mt-1 block text-slate-500">Giới hạn đọc trong ứng dụng.</small></span><ToggleSwitch v-model="settings.hard_drm" /></label>
        <label class="flex min-h-11 items-center justify-between gap-4"><span><strong>Cho phép in</strong></span><ToggleSwitch v-model="settings.allow_printing" /></label>
        <div><strong>Giới hạn sao chép: {{ settings.copy_limit_percent }}%</strong><Slider v-model="settings.copy_limit_percent" :min="0" :max="100" aria-label="Giới hạn phần trăm sao chép" class="mt-4" /></div>
        <label for="drm-license" class="mb-1 block text-sm font-semibold">Loại giấy phép</label>
        <Select inputId="drm-license" v-model="settings.license_type" :options="licenseOptions" optionLabel="label" optionValue="value" class="w-full min-w-0" />
        <Button label="Lưu cấu hình kỹ thuật" icon="pi pi-save" :loading="saving" @click="saveDrm" />
      </div>
    </div>
  </section>
</template>

<style scoped>
:deep(.p-button), :deep(.p-select) { min-height: 44px; }
@media (max-width: 480px) {
  :global(.p-toast) {
    left: 1rem !important;
    right: 1rem !important;
    width: auto !important;
    max-width: calc(100vw - 2rem) !important;
  }
}
</style>
