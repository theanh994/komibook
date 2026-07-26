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
  <main class="min-h-screen bg-slate-50 p-6 md:p-10">
    <section class="mx-auto max-w-4xl space-y-6">
      <header><h1 class="text-3xl font-black text-slate-900">Bảo vệ kỹ thuật DRM</h1><p class="mt-2 text-sm text-slate-600">DRM chỉ kiểm soát kỹ thuật đọc; không phải bằng chứng hoặc phê duyệt bản quyền.</p></header>
      <div v-if="loading" class="text-slate-500">Đang tải...</div>
      <div v-else-if="error" class="rounded-xl border border-rose-200 bg-rose-50 p-6 text-rose-800">{{ error }} <Button label="Thử lại" class="ml-3 p-button-sm" @click="fetchDrm" /></div>
      <div v-else class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="flex justify-between gap-4"><span><strong>Social DRM</strong><small class="mt-1 block text-slate-500">Watermark truy vết bản đọc.</small></span><ToggleSwitch v-model="settings.social_drm" /></label>
        <label class="flex justify-between gap-4"><span><strong>Mã hóa tệp</strong><small class="mt-1 block text-slate-500">Giới hạn đọc trong ứng dụng.</small></span><ToggleSwitch v-model="settings.hard_drm" /></label>
        <label class="flex justify-between gap-4"><span><strong>Cho phép in</strong></span><ToggleSwitch v-model="settings.allow_printing" /></label>
        <div><strong>Giới hạn sao chép: {{ settings.copy_limit_percent }}%</strong><Slider v-model="settings.copy_limit_percent" :min="0" :max="100" class="mt-4" /></div>
        <Select v-model="settings.license_type" :options="licenseOptions" optionLabel="label" optionValue="value" class="w-full" />
        <Button label="Lưu cấu hình kỹ thuật" icon="pi pi-save" :loading="saving" @click="saveDrm" />
      </div>
    </section>
  </main>
</template>
