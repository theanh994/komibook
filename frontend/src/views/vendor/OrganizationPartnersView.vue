<script setup>
import { onMounted, ref, watch } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const data = ref({ relationships: [], business_model: 'bookstore' })
const logoFile = ref(null)
const verificationDocument = ref(null)
const logoUploader = ref(null)
const verificationUploader = ref(null)
const slugManuallyEdited = ref(false)
const editingOrganizationId = ref(null)
const form = ref({
  legal_name: '',
  display_name: '',
  slug: '',
  organization_types: [],
  tax_code: '',
  license_number: '',
  description: '',
  website: '',
  public_source_url: '',
  public_source_checked_at: '',
})
const typeOptions = [
  { label: 'Nhà xuất bản', value: 'publisher' },
  { label: 'Nhà cung cấp', value: 'supplier' },
  { label: 'Nhà phân phối', value: 'distributor' },
  { label: 'Nhà sách / Hiệu sách', value: 'bookstore' },
]

const slugify = (value) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/đ/g, 'd')
  .replace(/Đ/g, 'D')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '')

watch(() => form.value.display_name, (displayName) => {
  if (!slugManuallyEdited.value) {
    form.value.slug = slugify(displayName)
  }
})

const onLogoSelect = (event) => {
  logoFile.value = event.files?.[0] ?? null
}

const onVerificationSelect = (event) => {
  verificationDocument.value = event.files?.[0] ?? null
}

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor/organizations')
    data.value = response.data.data
    const selfRelationship = data.value.relationships?.find((item) =>
      item.role === 'self_legal_entity' && item.organization?.id === data.value.primary_organization_id,
    )
    if (selfRelationship?.organization) {
      editingOrganizationId.value = selfRelationship.organization.id
      const organization = selfRelationship.organization
      Object.keys(form.value).forEach((key) => {
        form.value[key] = organization[key] ?? (key === 'organization_types' ? [] : '')
      })
      slugManuallyEdited.value = true
    }
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  saving.value = true
  try {
    const payload = new FormData()
    Object.entries(form.value).forEach(([key, value]) => {
      if (key === 'organization_types') {
        value.forEach((type) => payload.append('organization_types[]', type))
      } else if (value !== null && value !== '') {
        payload.append(key, value)
      }
    })
    if (logoFile.value) payload.append('logo', logoFile.value)
    if (verificationDocument.value) payload.append('verification_document', verificationDocument.value)

    if (editingOrganizationId.value) payload.append('_method', 'PATCH')
    const endpoint = editingOrganizationId.value
      ? `/api/vendor/organizations/${editingOrganizationId.value}`
      : '/api/vendor/organizations'
    await apiClient.post(endpoint, payload)
    toast.add({
      severity: 'success',
      summary: editingOrganizationId.value ? 'Đã cập nhật hồ sơ' : 'Đã gửi hồ sơ',
      detail: data.value.is_demo
        ? 'Dữ liệu demo đã được lưu và tiếp tục mang nhãn mô phỏng.'
        : 'Tổ chức và quan hệ pháp nhân đang chờ Admin xác minh.',
      life: 4000,
    })
    logoFile.value = null
    verificationDocument.value = null
    logoUploader.value?.clear()
    verificationUploader.value?.clear()
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
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Giải thích dữ liệu xác minh">
      <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4"><h2 class="font-bold text-primary">Pháp nhân</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Tên pháp lý, mã số thuế và giấy phép dùng để Admin đối chiếu. Mã số thuế và chứng từ không công khai cho khách hàng.</p></article>
      <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4"><h2 class="font-bold text-primary">Nhà xuất bản</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Đơn vị xuất bản hoặc phát hành ấn phẩm. Thông tin này giúp khách hàng nhận biết nguồn xuất bản của cuốn sách.</p></article>
      <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4"><h2 class="font-bold text-primary">Nhà cung cấp</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Đơn vị trực tiếp cung ứng hàng cho gian hàng. Nếu Nhà bán chính là NXB, một pháp nhân có thể đảm nhiệm cả hai vai trò.</p></article>
      <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4"><h2 class="font-bold text-primary">Quan hệ đã xác minh</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Xác minh pháp nhân không tự động xác minh quyền hợp tác. Mỗi quan hệ NXB, cung cấp hoặc phân phối vẫn phải được duyệt riêng.</p></article>
    </section>
    <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
      <h2 class="text-xl font-bold">{{ editingOrganizationId ? 'Cập nhật pháp nhân của gian hàng' : 'Đăng ký pháp nhân của gian hàng' }}</h2>
      <p class="mt-2 text-sm text-on-surface-variant">Mô hình hiện tại: <strong>{{ data.business_model }}</strong>. Thông tin thuế, giấy phép và chứng từ không hiển thị công khai.</p>
      <div v-if="data.is_demo" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="note">
        <strong>Dữ liệu mô phỏng – không có giá trị pháp lý.</strong>
        Chỉ nhập thông tin doanh nghiệp có thể đối chiếu công khai. Không tạo số tài khoản, giấy tờ tùy thân, con dấu hoặc hợp đồng giả. Ví đối soát <strong>{{ data.demo_wallet_code }}</strong> không thể rút tiền thật.
      </div>
      <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <div><label for="org-legal-name" class="mb-2 block text-sm font-semibold">Tên pháp lý *</label><InputText id="org-legal-name" v-model="form.legal_name" required class="min-h-11 w-full" /></div>
        <div><label for="org-display-name" class="mb-2 block text-sm font-semibold">Tên hiển thị *</label><InputText id="org-display-name" v-model="form.display_name" required class="min-h-11 w-full" /></div>
        <div>
          <label for="org-slug" class="mb-2 block text-sm font-semibold">Đường dẫn hồ sơ công khai *</label>
          <InputText id="org-slug" v-model="form.slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-h-11 w-full" aria-describedby="org-slug-help" @input="slugManuallyEdited = true" />
          <p id="org-slug-help" class="mt-1 text-xs leading-5 text-on-surface-variant">Dùng trong địa chỉ /organizations/{{ form.slug || 'ten-gian-hang' }}. Chỉ gồm chữ thường, số và dấu gạch ngang.</p>
        </div>
        <div><label for="org-types" class="mb-2 block text-sm font-semibold">Loại tổ chức *</label><MultiSelect id="org-types" v-model="form.organization_types" :options="typeOptions" optionLabel="label" optionValue="value" required class="min-h-11 w-full" /></div>
        <div><label for="org-tax-code" class="mb-2 block text-sm font-semibold">Mã số thuế</label><InputText id="org-tax-code" v-model="form.tax_code" class="min-h-11 w-full" /></div>
        <div><label for="org-license" class="mb-2 block text-sm font-semibold">Số giấy phép</label><InputText id="org-license" v-model="form.license_number" class="min-h-11 w-full" /></div>
        <div>
          <label for="org-website" class="mb-2 block text-sm font-semibold">Website chính thức</label>
          <InputText id="org-website" v-model="form.website" type="url" placeholder="https://example.com" class="min-h-11 w-full" aria-describedby="org-website-help" />
          <p id="org-website-help" class="mt-1 text-xs leading-5 text-on-surface-variant">Đường dẫn website chính thức sẽ được hiển thị công khai sau khi hồ sơ được duyệt.</p>
        </div>
        <div>
          <label for="org-public-source" class="mb-2 block text-sm font-semibold">Nguồn thông tin công khai</label>
          <InputText id="org-public-source" v-model="form.public_source_url" type="url" placeholder="https://..." class="min-h-11 w-full" aria-describedby="org-public-source-help" />
          <p id="org-public-source-help" class="mt-1 text-xs leading-5 text-on-surface-variant">Liên kết trang chính thức hoặc trang tra cứu dùng để đối chiếu tên pháp lý và mã số thuế.</p>
        </div>
        <div>
          <label for="org-source-date" class="mb-2 block text-sm font-semibold">Ngày kiểm tra nguồn</label>
          <InputText id="org-source-date" v-model="form.public_source_checked_at" type="date" class="min-h-11 w-full" />
        </div>
        <div>
          <span class="mb-2 block text-sm font-semibold">Logo tổ chức</span>
          <FileUpload ref="logoUploader" mode="basic" name="logo" accept="image/png,image/jpeg,image/webp" :maxFileSize="2097152" chooseLabel="Chọn logo" customUpload class="min-h-11" @select="onLogoSelect" />
          <p class="mt-1 text-xs leading-5 text-on-surface-variant">PNG, JPG hoặc WEBP, tối đa 2 MB. Logo sẽ xuất hiện trên hồ sơ công khai.</p>
          <p v-if="logoFile" class="mt-1 text-xs font-semibold text-primary">Đã chọn: {{ logoFile.name }}</p>
        </div>
        <div class="md:col-span-2">
          <span class="mb-2 block text-sm font-semibold">Tài liệu xác minh pháp nhân</span>
          <FileUpload ref="verificationUploader" mode="basic" name="verification_document" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tài liệu" customUpload class="min-h-11" @select="onVerificationSelect" />
          <p class="mt-1 text-xs leading-5 text-on-surface-variant">PDF, PNG hoặc JPG, tối đa 5 MB. Chỉ tải tài liệu thật mà bạn có quyền sử dụng; tài khoản demo có thể để trống và dùng nguồn công khai ở trên. Tài liệu riêng tư, khách hàng không thể xem hoặc tải xuống.</p>
          <p v-if="verificationDocument" class="mt-1 text-xs font-semibold text-primary">Đã chọn: {{ verificationDocument.name }}</p>
        </div>
        <div class="md:col-span-2"><label for="org-description" class="mb-2 block text-sm font-semibold">Giới thiệu công khai</label><Textarea id="org-description" v-model="form.description" rows="3" class="w-full" /></div>
        <div class="md:col-span-2 flex justify-end"><Button type="submit" :label="editingOrganizationId ? 'Lưu thông tin hồ sơ' : 'Gửi hồ sơ xác minh'" icon="pi pi-send" :loading="saving" class="min-h-11" /></div>
      </form>
    </section>
    <div v-if="loading" class="h-52 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="!data.relationships?.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center"><h2 class="text-xl font-bold">Chưa có quan hệ tổ chức</h2><p class="mt-2 text-on-surface-variant">Hãy đăng ký pháp nhân của gian hàng trước, sau đó bổ sung đối tác cung ứng.</p></section>
    <section v-else class="grid gap-4 lg:grid-cols-2">
      <article v-for="relationship in data.relationships" :key="relationship.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold">{{ relationship.organization?.display_name }}</h2><p class="mt-1 text-sm text-on-surface-variant">{{ relationship.role }}</p><p v-if="relationship.is_demo" class="mt-2 text-xs font-semibold text-amber-800">Dữ liệu mô phỏng · {{ relationship.demo_reference }}</p></div><span class="rounded-full bg-secondary-container px-3 py-1 text-sm font-semibold text-on-secondary-container">{{ relationship.status }}</span></div>
        <p v-if="relationship.last_review_reason" class="mt-4 rounded-lg bg-surface-container p-3 text-sm">{{ relationship.last_review_reason }}</p>
      </article>
    </section>
  </main>
</template>
