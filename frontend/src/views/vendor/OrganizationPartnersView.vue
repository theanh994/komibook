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

const businessModelLabels = {
  direct_publisher: 'NXB Trực Tiếp Bán',
  publisher: 'Nhà Xuất Bản',
  distributor: 'Nhà Phân Phối',
  supplier: 'Nhà Cung Cấp',
  bookstore: 'Gian Hàng Bán Lẻ',
  retailer: 'Gian Hàng Bán Lẻ',
  hybrid: 'Mô Hình Hỗn Hợp',
}

const roleLabels = {
  self_legal_entity: 'Pháp nhân trực tiếp của gian hàng',
  publisher_partner: 'Đối tác xuất bản ủy quyền',
  supplier_partner: 'Đối tác cung ứng nguồn sách',
  authorized_distributor: 'Nhà phân phối được ủy quyền',
}

const statusMeta = (status) => ({
  demo_accepted: { label: 'Đã duyệt Demo', className: 'border-emerald-300 bg-emerald-50 text-[#00b14f] font-extrabold' },
  verified: { label: 'Đã xác minh', className: 'border-emerald-300 bg-emerald-50 text-[#00b14f] font-extrabold' },
  active: { label: 'Hoạt động', className: 'border-emerald-300 bg-emerald-50 text-[#00b14f] font-extrabold' },
  approved: { label: 'Đã duyệt', className: 'border-emerald-300 bg-emerald-50 text-[#00b14f] font-extrabold' },
  pending_review: { label: 'Đang chờ duyệt', className: 'border-sky-300 bg-sky-50 text-sky-900' },
  submitted: { label: 'Đang chờ duyệt', className: 'border-sky-300 bg-sky-50 text-sky-900' },
  changes_requested: { label: 'Cần bổ sung thông tin', className: 'border-orange-300 bg-orange-50 text-orange-900' },
  suspended: { label: 'Đang tạm dừng', className: 'border-rose-300 bg-rose-50 text-rose-900' },
}[status] || { label: status || 'Chưa xác định', className: 'border-outline-variant bg-surface-container text-on-surface-variant' })

const missingRoleLabels = { publisher: 'Nhà xuất bản', supplier: 'Nhà cung cấp', responsible_organization: 'Đơn vị chịu trách nhiệm' }

const primaryRelationship = computed(() => (
  data.value.relationships?.find((item) =>
    item.role === 'self_legal_entity' && item.organization?.id === data.value.primary_organization_id,
  )
  || data.value.relationships?.find((item) => item.role === 'self_legal_entity')
  || data.value.relationships?.[0]
  || null
))
const primaryOrganization = computed(() => primaryRelationship.value?.organization || null)
const isOperational = computed(() => ['verified', 'demo_accepted', 'active', 'approved'].includes(primaryOrganization.value?.status))
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

const populateForm = (org) => {
  if (!org) return
  editingOrganizationId.value = org.id
  Object.keys(form.value).forEach((key) => {
    form.value[key] = org[key] ?? (key === 'organization_types' ? [] : '')
  })
  slugManuallyEdited.value = true
}

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor/organizations')
    data.value = response.data.data
    if (primaryOrganization.value) {
      populateForm(primaryOrganization.value)
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
      detail: data.value.is_demo ? 'Hồ sơ demo đã được lưu cập nhật thành công.' : 'Hồ sơ đã được lưu thành công.',
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
  <main id="main-content" class="mx-auto max-w-6xl space-y-8" tabindex="-1">
    <!-- PAGE TITLE HEADER -->
    <header class="border-b border-slate-200/80 pb-4">
      <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-[#00b14f] border border-emerald-200/60 mb-2">
        <span class="material-symbols-outlined text-sm">corporate_fare</span>
        <span>Hồ sơ Pháp nhân & Nguồn sách</span>
      </div>
      <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Quản lý NXB & Nhà cung cấp</h1>
      <p class="mt-1 text-xs sm:text-sm text-slate-500 leading-relaxed max-w-3xl">
        Xem và cập nhật Hồ sơ Pháp lý đại diện cho Gian hàng/NXB. Cấu hình liên kết chuỗi cung ứng cho các xuất bản phẩm sách.
      </p>
    </header>

    <!-- LOADING STATE -->
    <div v-if="loading" class="h-80 animate-pulse rounded-3xl bg-slate-200/60" role="status" aria-live="polite"></div>

    <template v-else>
      <!-- PRIMARY ORGANIZATION LEGAL PROFILE CARD (HARMONIZED & RICH DESIGN) -->
      <section v-if="primaryOrganization" class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm space-y-6" aria-labelledby="profile-status-title">
        <div class="p-6 sm:p-8 space-y-6">
          <!-- Top Row: Identity Avatar + Badges + Action Buttons -->
          <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between border-b border-slate-100 pb-6">
            <div class="flex items-start gap-4">
              <!-- Organization Avatar / Logo -->
              <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-[#00b14f] border border-emerald-100 shadow-2xs">
                <img v-if="primaryOrganization.logo" :src="`/storage/${primaryOrganization.logo}`" :alt="primaryOrganization.display_name" class="h-full w-full object-contain rounded-2xl" />
                <span v-else class="material-symbols-outlined text-3xl">apartment</span>
              </div>

              <!-- Title & Badges Group -->
              <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <!-- Verification Badge -->
                  <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-extrabold border shrink-0" :class="statusMeta(primaryOrganization.status).className">
                    <span class="w-2 h-2 rounded-full bg-[#00b14f] animate-pulse"></span>
                    {{ statusMeta(primaryOrganization.status).label }}
                  </span>

                  <!-- Demo Badge -->
                  <span v-if="primaryOrganization.data_mode === 'demo'" class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-900">
                    <span class="material-symbols-outlined text-sm text-amber-600">info</span>
                    <span>Dữ liệu mô phỏng</span>
                  </span>

                  <!-- Organization Type Badges -->
                  <span v-for="type in primaryOrganization.organization_types" :key="type" class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                    {{ typeLabel(type) }}
                  </span>
                </div>

                <h2 id="profile-status-title" class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                  {{ primaryOrganization.display_name }}
                </h2>
                <p class="text-xs font-mono text-slate-400">Slug công khai: {{ primaryOrganization.slug }}</p>
              </div>
            </div>

            <!-- Action Buttons (Harmonized Styling) -->
            <div class="flex flex-wrap items-center gap-3 shrink-0">
              <router-link
                :to="{ name: 'organization-public', params: { slug: primaryOrganization.slug } }"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:border-[#00b14f] hover:text-[#00b14f] transition-all no-underline shadow-2xs"
              >
                <span class="material-symbols-outlined text-base">visibility</span>
                <span>Xem hồ sơ công khai</span>
              </router-link>
              <button
                type="button"
                @click="updateOpen = !updateOpen"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#00b14f] px-5 text-xs font-extrabold text-white hover:bg-[#009e46] transition-all shadow-md cursor-pointer border-none"
              >
                <span class="material-symbols-outlined text-base">{{ updateOpen ? 'close' : 'edit' }}</span>
                <span>{{ updateOpen ? 'Đóng chỉnh sửa' : 'Cập nhật thông tin hồ sơ' }}</span>
              </button>
            </div>
          </div>

          <!-- Legal Profile Data Grid (Polished Labels & Values) -->
          <div class="grid gap-5 rounded-2xl bg-slate-50/90 p-5 sm:p-6 border border-slate-100 sm:grid-cols-2 lg:grid-cols-3 text-xs">
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Tên pháp lý đầy đủ:</span>
              <p class="font-extrabold text-slate-900 text-sm">{{ primaryOrganization.legal_name || 'Chưa cập nhật' }}</p>
            </div>
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Mã số thuế (MST):</span>
              <p v-if="primaryOrganization.tax_code" class="font-mono font-extrabold text-slate-900 text-sm">{{ primaryOrganization.tax_code }}</p>
              <p v-else class="text-slate-400 font-normal italic">Chưa khai báo</p>
            </div>
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Số giấy phép thành lập:</span>
              <p v-if="primaryOrganization.license_number" class="font-mono font-extrabold text-slate-900 text-sm">{{ primaryOrganization.license_number }}</p>
              <p v-else class="text-slate-400 font-normal italic">Chưa khai báo</p>
            </div>
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Website chính thức:</span>
              <p v-if="primaryOrganization.website" class="font-bold text-[#00b14f] truncate">
                <a :href="primaryOrganization.website" target="_blank" rel="noopener" class="hover:underline">{{ primaryOrganization.website }}</a>
              </p>
              <p v-else class="text-slate-400 font-normal italic">Chưa khai báo</p>
            </div>
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Mô hình kinh doanh:</span>
              <p class="font-extrabold text-slate-800">{{ businessModelLabels[data.business_model] || data.business_model || 'Bán lẻ' }}</p>
            </div>
            <div class="space-y-1">
              <span class="text-slate-500 font-semibold block">Trạng thái vận hành:</span>
              <p class="font-extrabold text-[#00b14f] flex items-center gap-1">
                <span class="material-symbols-outlined text-base">verified</span>
                <span>Sẵn sàng gắn vào sách</span>
              </p>
            </div>
            <div v-if="primaryOrganization.description" class="sm:col-span-2 lg:col-span-3 pt-3 border-t border-slate-200/60 space-y-1">
              <span class="text-slate-500 font-semibold block">Giới thiệu công khai:</span>
              <p class="text-slate-700 leading-relaxed">{{ primaryOrganization.description }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION: Supply Chain Next Steps -->
      <section v-if="primaryOrganization" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-4" aria-labelledby="supply-chain-next-title">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between border-b border-slate-100 pb-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#00b14f]">Bước tiếp theo</p>
            <h2 id="supply-chain-next-title" class="mt-0.5 text-xl font-black text-slate-900">Gắn hồ sơ vào sách</h2>
            <p v-if="unlinkedBooksCount" class="mt-1 text-xs text-slate-500">Có <strong>{{ unlinkedBooksCount }} sách</strong> chưa có đủ NXB, Nhà cung cấp hoặc Đơn vị chịu trách nhiệm.</p>
            <p v-else class="mt-1 text-xs text-slate-500">Tất cả sách mới của gian hàng đã có đủ chuỗi cung ứng đang hoạt động.</p>
          </div>
          <span class="inline-flex w-fit rounded-full px-3.5 py-1 text-xs font-extrabold shrink-0" :class="unlinkedBooksCount ? 'bg-amber-100 text-amber-950 border border-amber-200' : 'bg-emerald-100 text-emerald-950 border border-emerald-200'">
            {{ unlinkedBooksCount ? `${unlinkedBooksCount} sách cần gắn` : 'Đã sẵn sàng' }}
          </span>
        </div>

        <div v-if="unlinkedBooks.length" class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200/90 bg-white">
          <article v-for="book in unlinkedBooks" :key="book.id" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="font-bold text-slate-900 text-sm">{{ book.title }}</h3>
              <p class="mt-0.5 text-xs text-slate-500">Còn thiếu: {{ book.missing_roles.map((role) => missingRoleLabels[role] || role).join(' · ') }}</p>
            </div>
            <router-link :to="{ name: 'vendor-book-publishing', params: { bookId: book.id } }" class="ui-btn ui-btn-primary min-h-10 px-4 text-xs font-bold shrink-0 no-underline">
              Gắn chuỗi cung ứng
            </router-link>
          </article>
        </div>
      </section>

      <!-- EDIT ORGANIZATION FORM SECTION -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform opacity-0 -translate-y-2"
        enter-to-class="transform opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform opacity-100 translate-y-0"
        leave-to-class="transform opacity-0 -translate-y-2"
      >
        <section id="organization-edit-form" v-if="updateOpen" class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-md space-y-6" aria-labelledby="organization-edit-title">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between border-b border-slate-100 pb-4">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-[#00b14f]">Cập nhật thông tin</p>
              <h2 id="organization-edit-title" class="mt-1 text-xl font-black text-slate-900">{{ editingOrganizationId ? 'Cập nhật thông tin Hồ sơ Pháp nhân' : 'Đăng ký Hồ sơ Pháp nhân của gian hàng' }}</h2>
              <p class="mt-1 text-xs text-slate-500">Các trường bên dưới đã được nạp tự động từ hồ sơ hiện có. Bạn có thể thay đổi và bấm lưu.</p>
            </div>
            <Button label="Đóng form" icon="pi pi-times" severity="secondary" class="min-h-10 font-bold text-xs" @click="updateOpen = false" />
          </div>

          <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
            <div>
              <label for="org-legal-name" class="mb-1.5 block text-xs font-bold text-slate-700">Tên pháp lý *</label>
              <InputText id="org-legal-name" v-model="form.legal_name" required class="min-h-11 w-full text-xs" />
            </div>
            <div>
              <label for="org-display-name" class="mb-1.5 block text-xs font-bold text-slate-700">Tên hiển thị *</label>
              <InputText id="org-display-name" v-model="form.display_name" required class="min-h-11 w-full text-xs" />
            </div>
            <div>
              <label for="org-slug" class="mb-1.5 block text-xs font-bold text-slate-700">Đường dẫn hồ sơ công khai *</label>
              <InputText id="org-slug" v-model="form.slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" class="min-h-11 w-full text-xs font-mono" @input="slugManuallyEdited = true" />
            </div>
            <div>
              <label for="org-types" class="mb-1.5 block text-xs font-bold text-slate-700">Loại tổ chức *</label>
              <MultiSelect id="org-types" v-model="form.organization_types" :options="typeOptions" optionLabel="label" optionValue="value" required class="min-h-11 w-full text-xs" />
            </div>
            <div>
              <label for="org-tax-code" class="mb-1.5 block text-xs font-bold text-slate-700">Mã số thuế</label>
              <InputText id="org-tax-code" v-model="form.tax_code" class="min-h-11 w-full text-xs font-mono" />
            </div>
            <div>
              <label for="org-license" class="mb-1.5 block text-xs font-bold text-slate-700">Số giấy phép thành lập</label>
              <InputText id="org-license" v-model="form.license_number" class="min-h-11 w-full text-xs font-mono" />
            </div>
            <div>
              <label for="org-website" class="mb-1.5 block text-xs font-bold text-slate-700">Website chính thức</label>
              <InputText id="org-website" v-model="form.website" type="url" placeholder="https://example.com" class="min-h-11 w-full text-xs" />
            </div>
            <div>
              <label for="org-public-source" class="mb-1.5 block text-xs font-bold text-slate-700">Nguồn thông tin công khai</label>
              <InputText id="org-public-source" v-model="form.public_source_url" type="url" placeholder="https://..." class="min-h-11 w-full text-xs" />
            </div>
            <div class="md:col-span-2">
              <span class="mb-1.5 block text-xs font-bold text-slate-700">Logo tổ chức</span>
              <FileUpload ref="logoUploader" mode="basic" name="logo" accept="image/png,image/jpeg,image/webp" :maxFileSize="2097152" chooseLabel="Chọn logo" customUpload class="min-h-11" @select="onLogoSelect" />
              <p v-if="logoFile" class="mt-1 text-xs font-bold text-[#00b14f]">Đã chọn: {{ logoFile.name }}</p>
            </div>
            <div class="md:col-span-2">
              <span class="mb-1.5 block text-xs font-bold text-slate-700">Tài liệu xác minh pháp nhân</span>
              <FileUpload ref="verificationUploader" mode="basic" name="verification_document" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tài liệu" customUpload class="min-h-11" @select="onVerificationSelect" />
              <p v-if="verificationDocument" class="mt-1 text-xs font-bold text-[#00b14f]">Đã chọn: {{ verificationDocument.name }}</p>
            </div>
            <div class="md:col-span-2">
              <label for="org-description" class="mb-1.5 block text-xs font-bold text-slate-700">Giới thiệu công khai</label>
              <Textarea id="org-description" v-model="form.description" rows="3" class="w-full text-xs" />
            </div>
            <div class="md:col-span-2 flex justify-end">
              <Button type="submit" :label="editingOrganizationId ? 'Lưu thay đổi hồ sơ' : 'Gửi hồ sơ'" icon="pi pi-save" :loading="saving" class="min-h-11 font-extrabold text-xs" />
            </div>
          </form>
        </section>
      </Transition>

      <!-- SECTION: RELATIONSHIPS GRID (BALANCED CARDS) -->
      <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-4" aria-labelledby="relationships-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-[#00b14f]">Quan hệ đối tác</p>
            <h2 id="relationships-title" class="mt-0.5 text-xl font-black text-slate-900 flex items-center gap-2">
              <span class="material-symbols-outlined text-[#00b14f]">handshake</span>
              <span>Danh sách Hồ sơ & NXB Đối tác trong Chuỗi cung ứng</span>
            </h2>
          </div>

          <router-link
            :to="{ name: 'organization-portal' }"
            class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-50 px-4 text-xs font-extrabold text-[#00b14f] border border-emerald-200/80 hover:bg-emerald-100 hover:border-emerald-300 transition-all no-underline shrink-0 shadow-2xs"
          >
            <span class="material-symbols-outlined text-base">corporate_fare</span>
            <span>Tổ chức & Thỏa thuận phân phối</span>
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>

        <div v-if="data.relationships?.length" class="mt-4 grid gap-4 md:grid-cols-2">
          <article v-for="relationship in data.relationships" :key="relationship.id" class="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-slate-50/80 p-5 space-y-3 hover:border-[#00b14f]/50 transition-all">
            <div class="space-y-2">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#00b14f] border border-slate-200 shadow-2xs">
                    <span class="material-symbols-outlined text-xl">apartment</span>
                  </div>
                  <div>
                    <h3 class="font-extrabold text-slate-900 text-sm leading-snug">{{ relationship.organization?.display_name }}</h3>
                    <p class="text-xs font-medium text-slate-500">{{ roleLabels[relationship.role] || relationship.role }}</p>
                  </div>
                </div>

                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-extrabold shrink-0" :class="statusMeta(relationship.status).className">
                  {{ statusMeta(relationship.status).label }}
                </span>
              </div>

              <p v-if="relationship.status === 'demo_accepted'" class="text-xs text-slate-600 leading-relaxed pt-1 border-t border-slate-200/60">
                Sẵn sàng chọn làm bên xuất bản/cung cấp trong mô phỏng chuỗi cung ứng.
              </p>
              <p v-else-if="relationship.status === 'verified'" class="text-xs text-slate-600 leading-relaxed pt-1 border-t border-slate-200/60">
                Đã xác minh. Có thể chọn trong chuỗi cung ứng của các sách đang bán.
              </p>
            </div>

            <div class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200/60 mt-2">
              <router-link :to="{ name: 'organization-portal' }" class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-600 hover:text-[#00b14f] no-underline">
                <span class="material-symbols-outlined text-xs">description</span>
                <span>Thỏa thuận phân phối</span>
              </router-link>
              <router-link v-if="relationship.organization?.slug" :to="{ name: 'organization-public', params: { slug: relationship.organization.slug } }" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#00b14f] hover:underline no-underline">
                <span>Xem trang công khai</span>
                <span class="material-symbols-outlined text-xs">arrow_forward</span>
              </router-link>
            </div>
          </article>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center space-y-3">
          <p class="text-xs text-slate-500">Chưa có quan hệ đối tác chuỗi cung ứng nào được khai báo.</p>
          <router-link
            :to="{ name: 'organization-portal' }"
            class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-[#00b14f] px-4 text-xs font-bold text-white shadow-2xs hover:bg-[#009e46] no-underline"
          >
            <span class="material-symbols-outlined text-base">add_business</span>
            <span>Chuyển sang trang Tổ chức & Thỏa thuận phân phối</span>
          </router-link>
        </div>
      </section>
    </template>
  </main>
</template>
