<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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
const updateOpen = ref(false)
const data = ref({ relationships: [], business_model: 'bookstore', supply_chain: { unlinked_books_count: 0, unlinked_books: [] } })
const logoFile = ref(null)
const verificationDocument = ref(null)
const logoUploader = ref(null)
const verificationUploader = ref(null)
const slugManuallyEdited = ref(false)
const editingOrganizationId = ref(null)
const form = ref({
  legal_name: '', display_name: '', slug: '', organization_types: [], tax_code: '', license_number: '',
  description: '', website: '', public_source_url: '', public_source_checked_at: '',
})

const typeOptions = [
  { label: 'Nhà xuất bản', value: 'publisher' },
  { label: 'Nhà cung cấp', value: 'supplier' },
  { label: 'Nhà phân phối', value: 'distributor' },
  { label: 'Nhà sách / Hiệu sách', value: 'bookstore' },
]
const typeLabel = (type) => typeOptions.find((item) => item.value === type)?.label || type
const roleLabels = {
  self_legal_entity: 'Pháp nhân của gian hàng',
  publisher_partner: 'Đối tác xuất bản',
  supplier_partner: 'Đối tác cung ứng',
  authorized_distributor: 'Nhà phân phối được ủy quyền',
}
const statusMeta = (status) => ({
  demo_accepted: { label: 'Đã chấp nhận mô phỏng', className: 'border-amber-300 bg-amber-50 text-amber-950' },
  verified: { label: 'Đã xác minh', className: 'border-emerald-300 bg-emerald-50 text-emerald-900' },
  pending_review: { label: 'Đang chờ duyệt', className: 'border-sky-300 bg-sky-50 text-sky-900' },
  submitted: { label: 'Đang chờ duyệt', className: 'border-sky-300 bg-sky-50 text-sky-900' },
  changes_requested: { label: 'Cần bổ sung thông tin', className: 'border-orange-300 bg-orange-50 text-orange-900' },
  suspended: { label: 'Đang tạm dừng', className: 'border-rose-300 bg-rose-50 text-rose-900' },
}[status] || { label: status || 'Chưa xác định', className: 'border-outline-variant bg-surface-container text-on-surface-variant' })
const missingRoleLabels = { publisher: 'Nhà xuất bản', supplier: 'Nhà cung cấp', responsible_organization: 'Đơn vị chịu trách nhiệm' }

const primaryRelationship = computed(() => data.value.relationships?.find((item) =>
  item.role === 'self_legal_entity' && item.organization?.id === data.value.primary_organization_id,
))
const primaryOrganization = computed(() => primaryRelationship.value?.organization || null)
const isOperational = computed(() => ['verified', 'demo_accepted'].includes(primaryOrganization.value?.status) && ['verified', 'demo_accepted'].includes(primaryRelationship.value?.status))
const unlinkedBooks = computed(() => data.value.supply_chain?.unlinked_books || [])
const unlinkedBooksCount = computed(() => data.value.supply_chain?.unlinked_books_count || 0)

const slugify = (value) => value
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/đ/g, 'd')
  .replace(/Đ/g, 'D')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '')

watch(() => form.value.display_name, (displayName) => {
  if (!slugManuallyEdited.value) form.value.slug = slugify(displayName)
})

const onLogoSelect = (event) => { logoFile.value = event.files?.[0] ?? null }
const onVerificationSelect = (event) => { verificationDocument.value = event.files?.[0] ?? null }

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor/organizations')
    data.value = response.data.data
    if (primaryRelationship.value?.organization) {
      editingOrganizationId.value = primaryRelationship.value.organization.id
      Object.keys(form.value).forEach((key) => {
        form.value[key] = primaryRelationship.value.organization[key] ?? (key === 'organization_types' ? [] : '')
      })
      slugManuallyEdited.value = true
    } else {
      updateOpen.value = true
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
      if (key === 'organization_types') value.forEach((type) => payload.append('organization_types[]', type))
      else if (value !== null && value !== '') payload.append(key, value)
    })
    if (logoFile.value) payload.append('logo', logoFile.value)
    if (verificationDocument.value) payload.append('verification_document', verificationDocument.value)
    if (editingOrganizationId.value) payload.append('_method', 'PATCH')
    const endpoint = editingOrganizationId.value ? `/api/vendor/organizations/${editingOrganizationId.value}` : '/api/vendor/organizations'
    await apiClient.post(endpoint, payload)
    toast.add({
      severity: 'success',
      summary: editingOrganizationId.value ? 'Đã cập nhật hồ sơ' : 'Đã gửi hồ sơ',
      detail: data.value.is_demo ? 'Hồ sơ demo vẫn được nhận diện là dữ liệu mô phỏng.' : 'Hồ sơ đang chờ Admin kiểm duyệt.',
      life: 4000,
    })
    logoFile.value = null
    verificationDocument.value = null
    logoUploader.value?.clear()
    verificationUploader.value?.clear()
    updateOpen.value = false
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể lưu hồ sơ', detail: exception.response?.data?.message || 'Vui lòng kiểm tra các trường bắt buộc.', life: 4000 })
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <main id="main-content" class="mx-auto max-w-6xl space-y-6" tabindex="-1">
    <header>
      <p class="text-sm font-semibold uppercase tracking-wider text-primary">Nguồn sách & pháp nhân</p>
      <h1 class="mt-1 text-3xl font-bold text-on-surface">Nhà xuất bản & Nhà cung cấp</h1>
      <p class="mt-2 max-w-3xl leading-6 text-on-surface-variant">Kiểm tra hồ sơ đã dùng được, sau đó gắn hồ sơ đó vào từng cuốn sách. Việc đã chấp nhận hồ sơ không tự động thay đổi các sách cũ.</p>
    </header>

    <div v-if="loading" class="h-80 animate-pulse rounded-2xl bg-surface-container" role="status" aria-live="polite">Đang tải hồ sơ đối tác…</div>

    <template v-else>
      <section v-if="primaryOrganization" class="overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-sm" aria-labelledby="profile-status-title">
        <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:p-8">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full border px-3 py-1 text-sm font-bold" :class="statusMeta(primaryOrganization.status).className">{{ statusMeta(primaryOrganization.status).label }}</span>
              <span v-if="primaryOrganization.data_mode === 'demo'" class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-950">Dữ liệu mô phỏng</span>
              <span v-for="type in primaryOrganization.organization_types" :key="type" class="rounded-full bg-surface-container px-3 py-1 text-sm font-semibold text-on-surface-variant">{{ typeLabel(type) }}</span>
            </div>
            <h2 id="profile-status-title" class="mt-4 text-2xl font-black text-on-surface">{{ primaryOrganization.display_name }}</h2>
            <p v-if="isOperational" class="mt-3 max-w-3xl rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950">
              <strong>Hồ sơ đã dùng được.</strong>
              <span v-if="primaryOrganization.data_mode === 'demo'"> Đây là hồ sơ mô phỏng đã được chấp nhận để gắn vào sách trong luồng demo; bạn không cần nhập lại thông tin này. Dữ liệu mô phỏng – không có giá trị pháp lý.</span>
              <span v-else> Hồ sơ và quan hệ pháp nhân đã được xác minh, có thể chọn khi gắn chuỗi cung ứng cho sách.</span>
            </p>
            <p v-else class="mt-3 max-w-3xl rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">Hồ sơ đang được xử lý. Khi trạng thái chuyển sang “Đã xác minh” hoặc “Đã chấp nhận mô phỏng”, hồ sơ sẽ xuất hiện để gắn vào sách.</p>
          </div>
          <div class="flex flex-col gap-3 lg:items-end">
            <router-link :to="{ name: 'organization-public', params: { slug: primaryOrganization.slug } }" class="ui-btn ui-btn-secondary min-h-11 no-underline">Xem hồ sơ công khai</router-link>
            <Button :label="updateOpen ? 'Đóng chỉnh sửa' : 'Cập nhật thông tin hồ sơ'" :icon="updateOpen ? 'pi pi-times' : 'pi pi-pencil'" class="min-h-11" severity="secondary" :aria-expanded="updateOpen" aria-controls="organization-edit-form" @click="updateOpen = !updateOpen" />
          </div>
        </div>
      </section>

      <section v-if="primaryOrganization" class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm" aria-labelledby="supply-chain-next-title">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div>
            <p class="text-sm font-bold uppercase tracking-wider text-primary">Bước tiếp theo</p>
            <h2 id="supply-chain-next-title" class="mt-1 text-xl font-black text-on-surface">Gắn hồ sơ vào sách</h2>
            <p v-if="unlinkedBooksCount" class="mt-2 max-w-3xl text-sm leading-6 text-on-surface-variant">Có <strong>{{ unlinkedBooksCount }} sách</strong> chưa có đủ Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm. Đây là lý do trang sách vẫn ghi “Chưa khai báo chuỗi cung ứng”.</p>
            <p v-else class="mt-2 max-w-3xl text-sm leading-6 text-on-surface-variant">Tất cả sách mới của gian hàng đã có đủ chuỗi cung ứng đang hoạt động.</p>
          </div>
          <span class="inline-flex w-fit rounded-full px-3 py-1 text-sm font-bold" :class="unlinkedBooksCount ? 'bg-amber-100 text-amber-950' : 'bg-emerald-100 text-emerald-950'">{{ unlinkedBooksCount ? `${unlinkedBooksCount} sách cần gắn` : 'Đã sẵn sàng' }}</span>
        </div>
        <div v-if="unlinkedBooks.length" class="mt-5 divide-y divide-outline-variant/50 rounded-xl border border-outline-variant/70">
          <article v-for="book in unlinkedBooks" :key="book.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="font-bold text-on-surface">{{ book.title }}</h3>
              <p class="mt-1 text-sm leading-6 text-on-surface-variant">Còn thiếu: {{ book.missing_roles.map((role) => missingRoleLabels[role] || role).join(' · ') }}</p>
            </div>
            <router-link :to="{ name: 'vendor-book-publishing', params: { bookId: book.id } }" class="ui-btn ui-btn-primary min-h-11 shrink-0 no-underline">Gắn chuỗi cung ứng</router-link>
          </article>
        </div>
        <p v-if="unlinkedBooksCount > unlinkedBooks.length" class="mt-3 text-sm text-on-surface-variant">Đang hiển thị {{ unlinkedBooks.length }} trong {{ unlinkedBooksCount }} sách cần xử lý.</p>
      </section>

      <section class="grid gap-4 md:grid-cols-3" aria-label="Cách sử dụng hồ sơ và quan hệ">
        <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5"><h2 class="font-bold text-primary">1. Hồ sơ pháp nhân</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Tên, logo và vai trò được dùng để nhận diện nguồn sách. Trạng thái ở trên cho biết hồ sơ có dùng được hay chưa.</p></article>
        <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5"><h2 class="font-bold text-primary">2. Quan hệ với gian hàng</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Quan hệ là cầu nối để dùng tổ chức cho sách. Một pháp nhân của chính gian hàng có thể đảm nhiệm nhiều vai trò phù hợp.</p></article>
        <article class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5"><h2 class="font-bold text-primary">3. Liên kết từng cuốn sách</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Chọn hồ sơ đã dùng được cho NXB, nhà cung cấp và đơn vị chịu trách nhiệm của từng sách.</p></article>
      </section>

      <section v-if="data.relationships?.length" class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm" aria-labelledby="relationships-title">
        <div><p class="text-sm font-bold uppercase tracking-wider text-primary">Quan hệ đang có</p><h2 id="relationships-title" class="mt-1 text-xl font-black text-on-surface">Hồ sơ có thể chọn khi gắn sách</h2></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
          <article v-for="relationship in data.relationships" :key="relationship.id" class="rounded-xl border border-outline-variant bg-surface-container-low p-5">
            <div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-on-surface">{{ relationship.organization?.display_name }}</h3><p class="mt-1 text-sm text-on-surface-variant">{{ roleLabels[relationship.role] || relationship.role }}</p></div><span class="rounded-full border px-3 py-1 text-xs font-bold" :class="statusMeta(relationship.status).className">{{ statusMeta(relationship.status).label }}</span></div>
            <p v-if="relationship.status === 'demo_accepted'" class="mt-3 text-sm leading-6 text-amber-950">Dùng được trong luồng mô phỏng. Không phải xác nhận quan hệ pháp lý ngoài đời thực.</p>
            <p v-else-if="relationship.status === 'verified'" class="mt-3 text-sm leading-6 text-on-surface-variant">Có thể chọn trong chuỗi cung ứng của sách còn hiệu lực.</p>
            <p v-else class="mt-3 text-sm leading-6 text-on-surface-variant">Chưa thể gắn vào sách cho tới khi quan hệ được chấp nhận.</p>
            <p v-if="relationship.last_review_reason" class="mt-3 rounded-lg bg-surface-container-high p-3 text-sm">{{ relationship.last_review_reason }}</p>
          </article>
        </div>
      </section>

      <section id="organization-edit-form" v-if="updateOpen" class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm" aria-labelledby="organization-edit-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-sm font-bold uppercase tracking-wider text-primary">Chỉ khi cần thay đổi</p><h2 id="organization-edit-title" class="mt-1 text-xl font-black text-on-surface">{{ editingOrganizationId ? 'Cập nhật thông tin hồ sơ' : 'Đăng ký pháp nhân của gian hàng' }}</h2><p class="mt-2 text-sm leading-6 text-on-surface-variant">Các trường bên dưới đã được nạp từ hồ sơ hiện có. Bạn không cần nhập lại nếu không có thay đổi.</p></div><Button v-if="editingOrganizationId" label="Đóng" icon="pi pi-times" severity="secondary" class="min-h-11" @click="updateOpen = false" /></div>
        <form class="mt-6 grid gap-4 md:grid-cols-2" @submit.prevent="submit">
          <div><label for="org-legal-name" class="mb-2 block text-sm font-semibold">Tên pháp lý *</label><InputText id="org-legal-name" v-model="form.legal_name" required class="min-h-11 w-full" /></div>
          <div><label for="org-display-name" class="mb-2 block text-sm font-semibold">Tên hiển thị *</label><InputText id="org-display-name" v-model="form.display_name" required class="min-h-11 w-full" /></div>
          <div><label for="org-slug" class="mb-2 block text-sm font-semibold">Đường dẫn hồ sơ công khai *</label><InputText id="org-slug" v-model="form.slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-h-11 w-full" aria-describedby="org-slug-help" @input="slugManuallyEdited = true" /><p id="org-slug-help" class="mt-1 text-xs leading-5 text-on-surface-variant">Dùng trong địa chỉ /organizations/{{ form.slug || 'ten-gian-hang' }}.</p></div>
          <div><label for="org-types" class="mb-2 block text-sm font-semibold">Loại tổ chức *</label><MultiSelect id="org-types" v-model="form.organization_types" :options="typeOptions" optionLabel="label" optionValue="value" required class="min-h-11 w-full" /></div>
          <div><label for="org-tax-code" class="mb-2 block text-sm font-semibold">Mã số thuế</label><InputText id="org-tax-code" v-model="form.tax_code" class="min-h-11 w-full" /></div>
          <div><label for="org-license" class="mb-2 block text-sm font-semibold">Số giấy phép</label><InputText id="org-license" v-model="form.license_number" class="min-h-11 w-full" /></div>
          <div><label for="org-website" class="mb-2 block text-sm font-semibold">Website chính thức</label><InputText id="org-website" v-model="form.website" type="url" placeholder="https://example.com" class="min-h-11 w-full" /></div>
          <div><label for="org-public-source" class="mb-2 block text-sm font-semibold">Nguồn thông tin công khai</label><InputText id="org-public-source" v-model="form.public_source_url" type="url" placeholder="https://..." class="min-h-11 w-full" /></div>
          <div><label for="org-source-date" class="mb-2 block text-sm font-semibold">Ngày kiểm tra nguồn</label><InputText id="org-source-date" v-model="form.public_source_checked_at" type="date" class="min-h-11 w-full" /></div>
          <div><span class="mb-2 block text-sm font-semibold">Logo tổ chức</span><FileUpload ref="logoUploader" mode="basic" name="logo" accept="image/png,image/jpeg,image/webp" :maxFileSize="2097152" chooseLabel="Chọn logo" customUpload class="min-h-11" @select="onLogoSelect" /><p v-if="logoFile" class="mt-1 text-xs font-semibold text-primary">Đã chọn: {{ logoFile.name }}</p></div>
          <div class="md:col-span-2"><span class="mb-2 block text-sm font-semibold">Tài liệu xác minh pháp nhân</span><FileUpload ref="verificationUploader" mode="basic" name="verification_document" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tài liệu" customUpload class="min-h-11" @select="onVerificationSelect" /><p class="mt-1 text-xs leading-5 text-on-surface-variant">Dùng tài liệu thật mà bạn có quyền sử dụng. Với demo, có thể để trống và dùng nguồn thông tin công khai. Tài liệu riêng tư, khách hàng không thể xem hoặc tải xuống.</p><p v-if="verificationDocument" class="mt-1 text-xs font-semibold text-primary">Đã chọn: {{ verificationDocument.name }}</p></div>
          <div class="md:col-span-2"><label for="org-description" class="mb-2 block text-sm font-semibold">Giới thiệu công khai</label><Textarea id="org-description" v-model="form.description" rows="3" class="w-full" /></div>
          <div class="md:col-span-2 flex justify-end"><Button type="submit" :label="editingOrganizationId ? 'Lưu thay đổi hồ sơ' : 'Gửi hồ sơ'" icon="pi pi-save" :loading="saving" class="min-h-11" /></div>
        </form>
      </section>
    </template>
  </main>
</template>
