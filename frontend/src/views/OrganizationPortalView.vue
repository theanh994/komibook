<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const authStore = useAuthStore()
const loading = ref(true)
const savingOrganization = ref(false)
const savingAgreement = ref(false)
const memberships = ref([])
const agreements = ref([])
const publicOrganizations = ref([])
const verificationDocument = ref(null)
const agreementDocument = ref(null)

const organizationTypes = [
  { label: 'Nhà xuất bản', value: 'publisher' },
  { label: 'Nhà cung cấp', value: 'supplier' },
  { label: 'Nhà phân phối', value: 'distributor' },
  { label: 'Nhà sách / gian hàng', value: 'bookstore' },
]
const coverageOptions = [
  { label: 'Toàn bộ catalog được hai bên thỏa thuận', value: 'catalog' },
  { label: 'Danh sách sách cụ thể', value: 'books' },
]
const organizationForm = ref({
  legal_name: '', display_name: '', slug: '', organization_types: [], tax_code: '',
  license_number: '', website: '', description: '',
})
const agreementForm = ref({
  publisher_organization_id: null,
  distributor_organization_id: null,
  coverage: 'catalog',
  book_ids_text: '',
  effective_from: '',
  effective_until: '',
})

const managedOrganizations = computed(() => memberships.value.map((item) => item.organization).filter(Boolean))
const selectableOrganizations = computed(() => {
  const merged = [...publicOrganizations.value, ...managedOrganizations.value]
  return [...new Map(merged.map((item) => [item.id, item])).values()]
})

const load = async () => {
  loading.value = true
  try {
    const [portalResponse, organizationsResponse] = await Promise.all([
      apiClient.get('/api/organization-portal'),
      apiClient.get('/api/organizations'),
    ])
    memberships.value = portalResponse.data.data.memberships || []
    agreements.value = portalResponse.data.data.agreements || []
    publicOrganizations.value = organizationsResponse.data.data?.data || organizationsResponse.data.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể tải cổng đối tác', detail: error.response?.data?.message || 'Hãy thử lại.', life: 3500 })
  } finally {
    loading.value = false
  }
}

const submitOrganization = async () => {
  savingOrganization.value = true
  try {
    const payload = new FormData()
    Object.entries(organizationForm.value).forEach(([key, value]) => {
      if (key === 'organization_types') value.forEach((type) => payload.append('organization_types[]', type))
      else if (value) payload.append(key, value)
    })
    if (verificationDocument.value) payload.append('verification_document', verificationDocument.value)
    await apiClient.post('/api/organization-portal/organizations', payload)
    await authStore.fetchUser()
    toast.add({ severity: 'success', summary: 'Đã gửi hồ sơ tổ chức', detail: 'Admin sẽ kiểm tra trước khi tổ chức được dùng cho listing.', life: 3000 })
    await load()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể gửi hồ sơ', detail: error.response?.data?.message || 'Kiểm tra lại các trường bắt buộc.', life: 3500 })
  } finally {
    savingOrganization.value = false
  }
}

const submitAgreement = async () => {
  savingAgreement.value = true
  try {
    const payload = new FormData()
    payload.append('publisher_organization_id', agreementForm.value.publisher_organization_id)
    payload.append('distributor_organization_id', agreementForm.value.distributor_organization_id)
    payload.append('scope[coverage]', agreementForm.value.coverage)
    if (agreementForm.value.coverage === 'books') {
      agreementForm.value.book_ids_text.split(',').map((item) => item.trim()).filter(Boolean)
        .forEach((bookId) => payload.append('scope[book_ids][]', bookId))
    }
    if (agreementForm.value.effective_from) payload.append('effective_from', agreementForm.value.effective_from)
    if (agreementForm.value.effective_until) payload.append('effective_until', agreementForm.value.effective_until)
    if (agreementDocument.value) payload.append('evidence_document', agreementDocument.value)
    await apiClient.post('/api/organization-portal/distribution-agreements', payload)
    toast.add({ severity: 'success', summary: 'Đã gửi thỏa thuận', detail: 'Thỏa thuận cần được Admin duyệt trước khi gắn vào sách.', life: 3000 })
    await load()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể gửi thỏa thuận', detail: error.response?.data?.message || 'Kiểm tra phạm vi và tài liệu chứng minh.', life: 3500 })
  } finally {
    savingAgreement.value = false
  }
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="min-h-screen bg-background px-4 py-8 sm:px-6 lg:px-8" tabindex="-1">
    <div class="mx-auto max-w-7xl space-y-8">
      <header class="max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-wider text-secondary">Kênh pháp nhân & phân phối</p>
        <h1 class="mt-2 text-3xl font-black text-on-surface sm:text-4xl">Tổ chức và quyền phân phối sách</h1>
        <p class="mt-3 leading-7 text-on-surface-variant">Quản lý hồ sơ Nhà xuất bản, Nhà cung cấp hoặc Nhà phân phối độc lập với gian hàng. Chỉ Nhà bán đã duyệt mới vận hành đơn hàng và nhận doanh thu.</p>
      </header>

      <div v-if="loading" class="h-56 animate-pulse rounded-2xl bg-surface-container" role="status" aria-label="Đang tải cổng đối tác"></div>
      <template v-else>
        <section class="space-y-4" aria-labelledby="managed-organizations-title">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 id="managed-organizations-title" class="text-2xl font-black text-on-surface">Tổ chức bạn quản lý</h2><p class="mt-1 text-sm text-on-surface-variant">Quyền này không tự cấp quyền bán hàng.</p></div>
            <RouterLink to="/vendor/register" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-bold text-on-primary no-underline transition-opacity hover:opacity-90">Đăng ký mở gian hàng</RouterLink>
          </div>
          <div v-if="managedOrganizations.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="membership in memberships" :key="membership.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
              <div class="flex items-start justify-between gap-3"><div><h3 class="font-black text-on-surface">{{ membership.organization.display_name }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ membership.organization.organization_types?.join(' · ') }}</p></div><span class="rounded-full bg-surface-container px-3 py-1 text-xs font-bold">{{ membership.organization.status }}</span></div>
              <p class="mt-4 text-sm text-on-surface-variant">Quyền: {{ membership.role }}</p>
            </article>
          </div>
          <p v-else class="rounded-xl border border-dashed border-outline-variant p-6 text-on-surface-variant">Bạn chưa quản lý tổ chức nào. Hãy gửi hồ sơ bên dưới.</p>
        </section>

        <section class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 sm:p-7" aria-labelledby="organization-form-title">
          <h2 id="organization-form-title" class="text-2xl font-black text-on-surface">Đăng ký tổ chức</h2>
          <p class="mt-2 text-sm leading-6 text-on-surface-variant">Bước 1/2 · Khai báo pháp nhân. Hồ sơ chưa được Admin xác minh sẽ không xuất hiện trong lựa chọn của Nhà bán.</p>
          <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitOrganization">
            <div><label for="partner-legal-name" class="mb-2 block text-sm font-bold">Tên pháp lý *</label><InputText id="partner-legal-name" v-model.trim="organizationForm.legal_name" class="min-h-11 w-full" required /></div>
            <div><label for="partner-display-name" class="mb-2 block text-sm font-bold">Tên hiển thị *</label><InputText id="partner-display-name" v-model.trim="organizationForm.display_name" class="min-h-11 w-full" required /></div>
            <div><label for="partner-slug" class="mb-2 block text-sm font-bold">Đường dẫn *</label><InputText id="partner-slug" v-model.trim="organizationForm.slug" class="min-h-11 w-full" required /><p class="mt-1 text-xs text-on-surface-variant">Chữ thường, số và dấu gạch ngang; dùng cho trang hồ sơ công khai.</p></div>
            <div><label for="partner-types" class="mb-2 block text-sm font-bold">Vai trò tổ chức *</label><MultiSelect id="partner-types" v-model="organizationForm.organization_types" :options="organizationTypes" optionLabel="label" optionValue="value" class="min-h-11 w-full" required /></div>
            <div><label for="partner-tax-code" class="mb-2 block text-sm font-bold">Mã số thuế</label><InputText id="partner-tax-code" v-model.trim="organizationForm.tax_code" class="min-h-11 w-full" /></div>
            <div><label for="partner-license" class="mb-2 block text-sm font-bold">Số giấy phép</label><InputText id="partner-license" v-model.trim="organizationForm.license_number" class="min-h-11 w-full" /></div>
            <div class="md:col-span-2"><label for="partner-description" class="mb-2 block text-sm font-bold">Giới thiệu công khai</label><Textarea id="partner-description" v-model.trim="organizationForm.description" rows="3" class="w-full" /></div>
            <div class="md:col-span-2"><span class="mb-2 block text-sm font-bold">Tài liệu xác minh</span><FileUpload mode="basic" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tài liệu" customUpload @select="verificationDocument = $event.files?.[0] || null" /><p class="mt-1 text-xs text-on-surface-variant">Chỉ Admin được truy cập; không công khai cho khách hàng.</p></div>
            <div class="md:col-span-2 flex justify-end"><Button type="submit" label="Gửi hồ sơ tổ chức" icon="pi pi-send" :loading="savingOrganization" class="min-h-11" /></div>
          </form>
        </section>

        <section v-if="managedOrganizations.length" class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 sm:p-7" aria-labelledby="agreement-form-title">
          <h2 id="agreement-form-title" class="text-2xl font-black text-on-surface">Đề nghị thỏa thuận phân phối</h2>
          <p class="mt-2 text-sm leading-6 text-on-surface-variant">Bước 2/2 · Chọn NXB và Nhà phân phối. Một trong hai tổ chức phải thuộc quyền quản lý của bạn.</p>
          <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitAgreement">
            <div><label for="agreement-publisher" class="mb-2 block text-sm font-bold">Nhà xuất bản *</label><Select id="agreement-publisher" v-model="agreementForm.publisher_organization_id" :options="selectableOrganizations.filter(item => item.organization_types?.includes('publisher'))" optionLabel="display_name" optionValue="id" filter class="min-h-11 w-full" required /></div>
            <div><label for="agreement-distributor" class="mb-2 block text-sm font-bold">Nhà phân phối/Nhà cung cấp *</label><Select id="agreement-distributor" v-model="agreementForm.distributor_organization_id" :options="selectableOrganizations.filter(item => item.organization_types?.some(type => ['supplier', 'distributor'].includes(type)))" optionLabel="display_name" optionValue="id" filter class="min-h-11 w-full" required /></div>
            <div><label for="agreement-coverage" class="mb-2 block text-sm font-bold">Phạm vi *</label><Select id="agreement-coverage" v-model="agreementForm.coverage" :options="coverageOptions" optionLabel="label" optionValue="value" class="min-h-11 w-full" /></div>
            <div v-if="agreementForm.coverage === 'books'"><label for="agreement-books" class="mb-2 block text-sm font-bold">ID sách, phân cách bằng dấu phẩy *</label><InputText id="agreement-books" v-model.trim="agreementForm.book_ids_text" class="min-h-11 w-full" required /></div>
            <div><label for="agreement-from" class="mb-2 block text-sm font-bold">Có hiệu lực từ</label><input id="agreement-from" v-model="agreementForm.effective_from" type="date" class="min-h-11 w-full rounded-lg border border-outline px-3" /></div>
            <div><label for="agreement-until" class="mb-2 block text-sm font-bold">Hết hiệu lực</label><input id="agreement-until" v-model="agreementForm.effective_until" type="date" class="min-h-11 w-full rounded-lg border border-outline px-3" /></div>
            <div class="md:col-span-2"><span class="mb-2 block text-sm font-bold">Chứng từ ủy quyền *</span><FileUpload mode="basic" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn chứng từ" customUpload required @select="agreementDocument = $event.files?.[0] || null" /></div>
            <div class="md:col-span-2 flex justify-end"><Button type="submit" label="Gửi thỏa thuận để duyệt" icon="pi pi-send" :loading="savingAgreement" class="min-h-11" /></div>
          </form>
        </section>

        <section class="space-y-4" aria-labelledby="agreements-title">
          <h2 id="agreements-title" class="text-2xl font-black text-on-surface">Lịch sử thỏa thuận</h2>
          <p v-if="!agreements.length" class="rounded-xl border border-dashed border-outline-variant p-6 text-on-surface-variant">Chưa có thỏa thuận phân phối.</p>
          <article v-for="agreement in agreements" :key="agreement.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-black text-on-surface">{{ agreement.publisher?.display_name }} → {{ agreement.distributor?.display_name }}</h3><p class="mt-1 text-sm text-on-surface-variant">Phạm vi: {{ agreement.scope?.coverage === 'books' ? 'Sách cụ thể' : 'Catalog' }}</p></div><span class="rounded-full bg-surface-container px-3 py-1 text-xs font-bold">{{ agreement.status }}</span></div>
            <p v-if="agreement.last_review_reason" class="mt-3 rounded-lg bg-surface-container p-3 text-sm" role="status">{{ agreement.last_review_reason }}</p>
          </article>
        </section>
      </template>
    </div>
  </main>
</template>
