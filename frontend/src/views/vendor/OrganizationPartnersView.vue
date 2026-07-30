<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const data = ref({ relationships: [], business_model: 'bookstore' })
const form = ref({
  legal_name: '',
  display_name: '',
  slug: '',
  organization_types: [],
  tax_code: '',
  license_number: '',
  description: '',
  website: '',
})
const typeOptions = [
  { label: 'Nhà xuất bản', value: 'publisher' },
  { label: 'Nhà cung cấp', value: 'supplier' },
  { label: 'Nhà phân phối', value: 'distributor' },
  { label: 'Nhà sách / Hiệu sách', value: 'bookstore' },
]

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor/organizations')
    data.value = response.data.data
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  saving.value = true
  try {
    await apiClient.post('/api/vendor/organizations', form.value)
    toast.add({ severity: 'success', summary: 'Đã gửi hồ sơ', detail: 'Tổ chức và quan hệ pháp nhân đang chờ Admin xác minh.', life: 4000 })
    form.value = { legal_name: '', display_name: '', slug: '', organization_types: [], tax_code: '', license_number: '', description: '', website: '' }
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể gửi', detail: exception.response?.data?.message || 'Vui lòng kiểm tra các trường bắt buộc.', life: 4000 })
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="space-y-6" tabindex="-1">
    <header><p class="text-sm font-semibold uppercase tracking-wider text-primary">Nguồn sách & pháp nhân</p><h1 class="mt-1 text-3xl font-bold text-on-surface">Nhà xuất bản & Nhà cung cấp</h1><p class="mt-2 max-w-3xl text-on-surface-variant">Mỗi sản phẩm mới phải dùng tổ chức và quan hệ đã được xác minh. Nhà bán không được tự đánh dấu hồ sơ là đã duyệt.</p></header>
    <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
      <h2 class="text-xl font-bold">Đăng ký pháp nhân của gian hàng</h2>
      <p class="mt-2 text-sm text-on-surface-variant">Mô hình hiện tại: <strong>{{ data.business_model }}</strong>. Thông tin thuế, giấy phép và chứng từ không hiển thị công khai.</p>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <div><label for="org-legal-name" class="mb-2 block text-sm font-semibold">Tên pháp lý *</label><InputText id="org-legal-name" v-model="form.legal_name" required class="min-h-11 w-full" /></div>
        <div><label for="org-display-name" class="mb-2 block text-sm font-semibold">Tên hiển thị *</label><InputText id="org-display-name" v-model="form.display_name" required class="min-h-11 w-full" /></div>
        <div><label for="org-slug" class="mb-2 block text-sm font-semibold">Đường dẫn *</label><InputText id="org-slug" v-model="form.slug" required class="min-h-11 w-full" /></div>
        <div><label for="org-types" class="mb-2 block text-sm font-semibold">Loại tổ chức *</label><MultiSelect id="org-types" v-model="form.organization_types" :options="typeOptions" optionLabel="label" optionValue="value" required class="min-h-11 w-full" /></div>
        <div><label for="org-tax-code" class="mb-2 block text-sm font-semibold">Mã số thuế</label><InputText id="org-tax-code" v-model="form.tax_code" class="min-h-11 w-full" /></div>
        <div><label for="org-license" class="mb-2 block text-sm font-semibold">Số giấy phép</label><InputText id="org-license" v-model="form.license_number" class="min-h-11 w-full" /></div>
        <div class="md:col-span-2"><label for="org-description" class="mb-2 block text-sm font-semibold">Giới thiệu công khai</label><Textarea id="org-description" v-model="form.description" rows="3" class="w-full" /></div>
        <div class="md:col-span-2 flex justify-end"><Button type="submit" label="Gửi hồ sơ xác minh" icon="pi pi-send" :loading="saving" class="min-h-11" /></div>
      </form>
    </section>
    <div v-if="loading" class="h-52 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="!data.relationships?.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center"><h2 class="text-xl font-bold">Chưa có quan hệ tổ chức</h2><p class="mt-2 text-on-surface-variant">Hãy đăng ký pháp nhân của gian hàng trước, sau đó bổ sung đối tác cung ứng.</p></section>
    <section v-else class="grid gap-4 lg:grid-cols-2">
      <article v-for="relationship in data.relationships" :key="relationship.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold">{{ relationship.organization?.display_name }}</h2><p class="mt-1 text-sm text-on-surface-variant">{{ relationship.role }}</p></div><span class="rounded-full bg-secondary-container px-3 py-1 text-sm font-semibold text-on-secondary-container">{{ relationship.status }}</span></div>
        <p v-if="relationship.last_review_reason" class="mt-4 rounded-lg bg-surface-container p-3 text-sm">{{ relationship.last_review_reason }}</p>
      </article>
    </section>
  </main>
</template>
