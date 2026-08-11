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
const selectableBooks = ref([])
const verificationDocument = ref(null)
const agreementDocument = ref(null)
const showNewOrgForm = ref(false)

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
  selected_book_ids: [],
  effective_from: '',
  effective_until: '',
})

const managedOrganizations = computed(() => memberships.value.map((item) => item.organization).filter(Boolean))
const selectableOrganizations = computed(() => {
  const merged = [...publicOrganizations.value, ...managedOrganizations.value]
  return [...new Map(merged.map((item) => [item.id, item])).values()]
})

const isAlreadyVendor = computed(() => (
  authStore.isVendor || authStore.user?.capabilities?.active_vendor
))

const selectedPublisherOrg = computed(() => (
  selectableOrganizations.value.find((org) => org.id === agreementForm.value.publisher_organization_id)
))

const getOrgTypeBadge = (type) => {
  const map = {
    publisher: { label: 'Nhà xuất bản', bg: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    supplier: { label: 'Nhà cung cấp', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    distributor: { label: 'Nhà phân phối', bg: 'bg-sky-50 text-sky-700 border-sky-200' },
    bookstore: { label: 'Gian hàng', bg: 'bg-purple-50 text-purple-700 border-purple-200' },
  }
  return map[type] || { label: type, bg: 'bg-slate-100 text-slate-700 border-slate-200' }
}

const load = async () => {
  loading.value = true
  try {
    const [portalResponse, organizationsResponse] = await Promise.all([
      apiClient.get('/api/organization-portal'),
      apiClient.get('/api/organizations'),
    ])
    memberships.value = portalResponse.data.data.memberships || []
    agreements.value = portalResponse.data.data.agreements || []
    selectableBooks.value = portalResponse.data.data.selectableBooks || []
    publicOrganizations.value = organizationsResponse.data.data?.data || organizationsResponse.data.data || []

    const vendorData = portalResponse.data.data.vendorData
    if (vendorData) {
      if (!organizationForm.value.tax_code && vendorData.tax_code) {
        organizationForm.value.tax_code = vendorData.tax_code
      }
      if (!organizationForm.value.legal_name && vendorData.legal_name) {
        organizationForm.value.legal_name = vendorData.legal_name
      }
      if (!organizationForm.value.display_name && (vendorData.shop_name || vendorData.legal_name)) {
        organizationForm.value.display_name = vendorData.shop_name || vendorData.legal_name
      }
    }

    // Auto-select publisher organization
    const myPublisherOrg = managedOrganizations.value.find((org) =>
      org.organization_types?.includes('publisher') || org.organization_types?.includes('supplier')
    )
    if (myPublisherOrg && !agreementForm.value.publisher_organization_id) {
      agreementForm.value.publisher_organization_id = myPublisherOrg.id
    }
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
    showNewOrgForm.value = false
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
      const bookIds = agreementForm.value.selected_book_ids.length
        ? agreementForm.value.selected_book_ids
        : agreementForm.value.book_ids_text.split(',').map((item) => item.trim()).filter(Boolean)
      bookIds.forEach((bookId) => payload.append('scope[book_ids][]', bookId))
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
  <main id="main-content" class="min-h-screen bg-slate-50/60 px-4 py-8 sm:px-6 lg:px-8" tabindex="-1">
    <div class="mx-auto max-w-6xl space-y-8">
      <!-- Top Banner / Page Header Card -->
      <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-[#0c1938] p-6 sm:p-8 text-white shadow-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2 max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-300">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Kênh Pháp Nhân & Ủy Quyền Phân Phối
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Quản lý Tổ chức và quyền phân phối sách</h1>
            <p class="text-xs sm:text-sm leading-relaxed text-slate-300">
              Khai báo Nhà xuất bản, Nhà cung cấp hoặc Nhà phân phối độc lập. Cấu hình ủy quyền phân phối để vận hành bán hàng trên KomiBook.
            </p>
          </div>

          <!-- Dynamic Action Button: Show Dashboard Link if already vendor, else Register Link -->
          <div class="shrink-0">
            <RouterLink
              v-if="isAlreadyVendor"
              to="/vendor/dashboard"
              class="inline-flex min-h-12 items-center gap-2.5 rounded-2xl bg-[#00b14f] px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-emerald-900/30 hover:bg-[#009e46] hover:scale-[1.02] transition-all no-underline"
            >
              <span class="material-symbols-outlined text-xl">storefront</span>
              <span>Vào trang Quản lý Gian hàng</span>
            </RouterLink>
            <RouterLink
              v-else
              to="/vendor/register"
              class="inline-flex min-h-12 items-center gap-2.5 rounded-2xl bg-[#00b14f] px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-emerald-900/30 hover:bg-[#009e46] hover:scale-[1.02] transition-all no-underline"
            >
              <span class="material-symbols-outlined text-xl">app_registration</span>
              <span>Đăng ký mở gian hàng</span>
            </RouterLink>
          </div>
        </div>
      </header>

      <!-- Skeleton Loading State -->
      <div v-if="loading" class="h-64 animate-pulse rounded-3xl bg-slate-200/60" role="status" aria-label="Đang tải cổng đối tác"></div>

      <template v-else>
        <!-- SECTION 1: Managed Organizations Grid (Summary Cards) -->
        <section class="space-y-4" aria-labelledby="managed-organizations-title">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-3">
            <div>
              <h2 id="managed-organizations-title" class="text-xl font-black text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#00b14f]">corporate_fare</span>
                <span>Tổ chức pháp nhân bạn quản lý</span>
              </h2>
              <p class="mt-0.5 text-xs text-slate-500">Tóm tắt hồ sơ công ty/nhà xuất bản đại diện pháp lý cho các ấn phẩm sách.</p>
            </div>

            <!-- Action Button to Add New Org if already has orgs -->
            <button
              v-if="managedOrganizations.length"
              type="button"
              @click="showNewOrgForm = !showNewOrgForm"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:border-[#00b14f] hover:text-[#00b14f] transition-all cursor-pointer"
            >
              <span class="material-symbols-outlined text-base">{{ showNewOrgForm ? 'close' : 'add_business' }}</span>
              <span>{{ showNewOrgForm ? 'Hủy khai báo' : 'Khai báo thêm tổ chức mới' }}</span>
            </button>
          </div>

          <!-- Grid of Concise Corporate Cards -->
          <div v-if="managedOrganizations.length" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <article
              v-for="membership in memberships"
              :key="membership.id"
              class="group relative flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs transition-all hover:border-[#00b14f]/50 hover:shadow-md"
            >
              <div class="space-y-3">
                <!-- Header with Title & Verification Status -->
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#00b14f] border border-emerald-100 shadow-2xs">
                      <span class="material-symbols-outlined text-2xl">apartment</span>
                    </div>
                    <div>
                      <h3 class="font-extrabold text-slate-900 text-base leading-tight group-hover:text-[#00b14f] transition-colors">
                        {{ membership.organization.display_name }}
                      </h3>
                      <span class="text-[11px] text-slate-400 font-mono block mt-0.5">slug: {{ membership.organization.slug }}</span>
                    </div>
                  </div>

                  <!-- Status Badge -->
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold border shrink-0"
                    :class="['demo_accepted', 'active', 'approved'].includes(membership.organization.status)
                      ? 'bg-emerald-50 text-[#00b14f] border-emerald-200'
                      : 'bg-amber-50 text-amber-800 border-amber-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00b14f]"></span>
                    {{ membership.organization.status === 'demo_accepted' ? 'Đã duyệt Demo' : (membership.organization.status === 'active' ? 'Hoạt động' : membership.organization.status) }}
                  </span>
                </div>

                <!-- Organization Type Badges -->
                <div class="flex flex-wrap gap-1.5 pt-1">
                  <span
                    v-for="type in (membership.organization.organization_types || [])"
                    :key="type"
                    class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-bold"
                    :class="getOrgTypeBadge(type).bg"
                  >
                    {{ getOrgTypeBadge(type).label }}
                  </span>
                </div>

                <!-- Info Grid (Concise) -->
                <div class="rounded-xl bg-slate-50/80 p-3 space-y-1.5 border border-slate-100 text-xs">
                  <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-400 font-medium">Tên pháp lý:</span>
                    <span class="font-bold text-slate-800 truncate max-w-[180px]">{{ membership.organization.legal_name || 'N/A' }}</span>
                  </div>
                  <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-400 font-medium">Mã số thuế:</span>
                    <span class="font-mono font-bold text-slate-800">{{ membership.organization.tax_code || 'Chưa khai báo' }}</span>
                  </div>
                  <div class="flex items-center justify-between text-slate-600">
                    <span class="text-slate-400 font-medium">Quyền quản trị:</span>
                    <span class="font-bold text-[#00b14f] uppercase text-[11px]">{{ membership.role }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer Link to Full Management Page in Vendor Area -->
              <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                <span class="flex items-center gap-1 font-bold text-emerald-700">
                  <span class="material-symbols-outlined text-sm text-[#00b14f]">verified</span>
                  <span>Đủ điều kiện phân phối</span>
                </span>
                <RouterLink
                  v-if="isAlreadyVendor"
                  to="/vendor/organizations"
                  class="inline-flex items-center gap-1 font-bold text-[#00b14f] hover:underline no-underline"
                >
                  <span>Quản lý chi tiết</span>
                  <span class="material-symbols-outlined text-xs">arrow_forward</span>
                </RouterLink>
              </div>
            </article>

            <!-- Add Organization Action Card -->
            <button
              type="button"
              @click="showNewOrgForm = true"
              class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-white/60 p-6 text-center hover:border-[#00b14f] hover:bg-emerald-50/30 transition-all cursor-pointer min-h-[200px]"
            >
              <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 group-hover:bg-emerald-100 group-hover:text-[#00b14f]">
                <span class="material-symbols-outlined text-2xl">add_business</span>
              </div>
              <span class="text-xs font-extrabold text-slate-700">Khai báo thêm tổ chức mới</span>
              <span class="text-[11px] text-slate-400">Đăng ký thêm pháp nhân NXB hoặc Nhà cung cấp</span>
            </button>
          </div>

          <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center space-y-3">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-[#00b14f]">
              <span class="material-symbols-outlined text-3xl">corporate_fare</span>
            </div>
            <h3 class="text-base font-bold text-slate-900">Bạn chưa quản lý tổ chức pháp nhân nào</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto">Vui lòng hoàn tất biểu mẫu đăng ký thông tin NXB hoặc Nhà cung cấp bên dưới để bắt đầu gửi thỏa thuận phân phối.</p>
          </div>
        </section>

        <!-- SECTION 2: Organization Form (Collapsible when org exists) -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="transform opacity-0 -translate-y-2"
          enter-to-class="transform opacity-100 translate-y-0"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="transform opacity-100 translate-y-0"
          leave-to-class="transform opacity-0 -translate-y-2"
        >
          <section
            v-if="!managedOrganizations.length || showNewOrgForm"
            class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-sm space-y-6"
            aria-labelledby="organization-form-title"
          >
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
              <div>
                <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-extrabold text-[#00b14f] mb-1">
                  <span>Bước 1/2</span>
                </div>
                <h2 id="organization-form-title" class="text-xl font-black text-slate-900 flex items-center gap-2">
                  <span class="material-symbols-outlined text-[#00b14f]">domain_add</span>
                  <span>Đăng ký thông tin Tổ chức Pháp nhân mới</span>
                </h2>
                <p class="mt-1 text-xs text-slate-500">Khai báo thông tin pháp lý cho Nhà xuất bản, Nhà cung cấp hoặc Gian hàng độc lập.</p>
              </div>
              <button
                v-if="managedOrganizations.length"
                type="button"
                @click="showNewOrgForm = false"
                class="rounded-xl px-3 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-800 border-none bg-transparent cursor-pointer transition-colors"
              >
                Đóng form
              </button>
            </div>

            <form class="grid gap-5 md:grid-cols-2" @submit.prevent="submitOrganization">
              <div>
                <label for="partner-legal-name" class="mb-1.5 block text-xs font-bold text-slate-700">Tên pháp lý *</label>
                <InputText id="partner-legal-name" v-model.trim="organizationForm.legal_name" placeholder="Ví dụ: Công ty TNHH Nhà xuất bản Trẻ" class="min-h-11 w-full text-xs" required />
              </div>
              <div>
                <label for="partner-display-name" class="mb-1.5 block text-xs font-bold text-slate-700">Tên hiển thị *</label>
                <InputText id="partner-display-name" v-model.trim="organizationForm.display_name" placeholder="Ví dụ: Nhà xuất bản Trẻ" class="min-h-11 w-full text-xs" required />
              </div>
              <div>
                <label for="partner-slug" class="mb-1.5 block text-xs font-bold text-slate-700">Đường dẫn định danh (Slug) *</label>
                <InputText id="partner-slug" v-model.trim="organizationForm.slug" placeholder="nxb-tre" class="min-h-11 w-full text-xs font-mono" required />
              </div>
              <div>
                <label for="partner-types" class="mb-1.5 block text-xs font-bold text-slate-700">Vai trò tổ chức *</label>
                <MultiSelect id="partner-types" v-model="organizationForm.organization_types" :options="organizationTypes" optionLabel="label" optionValue="value" placeholder="Chọn vai trò" class="min-h-11 w-full text-xs" required />
              </div>
              <div>
                <label for="partner-tax-code" class="mb-1.5 block text-xs font-bold text-slate-700">Mã số thuế</label>
                <InputText id="partner-tax-code" v-model.trim="organizationForm.tax_code" placeholder="0101234567" class="min-h-11 w-full text-xs font-mono" />
              </div>
              <div>
                <label for="partner-license" class="mb-1.5 block text-xs font-bold text-slate-700">Số giấy phép thành lập</label>
                <InputText id="partner-license" v-model.trim="organizationForm.license_number" placeholder="GP-1234/XB" class="min-h-11 w-full text-xs font-mono" />
              </div>
              <div class="md:col-span-2">
                <label for="partner-description" class="mb-1.5 block text-xs font-bold text-slate-700">Giới thiệu ngắn công khai</label>
                <Textarea id="partner-description" v-model.trim="organizationForm.description" rows="2" placeholder="Giới thiệu về đơn vị xuất bản..." class="w-full text-xs" />
              </div>
              <div class="md:col-span-2">
                <span class="mb-1.5 block text-xs font-bold text-slate-700">Tài liệu xác minh (PDF / Hình ảnh)</span>
                <FileUpload mode="basic" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tập tin" customUpload @select="verificationDocument = $event.files?.[0] || null" />
                <p class="mt-1 text-[11px] text-slate-400">Chỉ Admin kiểm duyệt nội bộ; không hiển thị công khai.</p>
              </div>
              <div class="md:col-span-2 flex justify-end">
                <Button type="submit" label="Gửi hồ sơ tổ chức" icon="pi pi-send" :loading="savingOrganization" class="min-h-11 font-bold text-xs" />
              </div>
            </form>
          </section>
        </Transition>

        <!-- SECTION 3: Distribution Agreement Form -->
        <section v-if="managedOrganizations.length" class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-sm space-y-6" aria-labelledby="agreement-form-title">
          <div class="border-b border-slate-100 pb-4">
            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-[#00b14f] border border-emerald-200/60 mb-2">
              <span class="material-symbols-outlined text-sm">handshake</span>
              <span>Bước 2/2: Lập Thỏa thuận Ủy quyền Phân phối</span>
            </div>
            <h2 id="agreement-form-title" class="text-xl font-black text-slate-900">Đề nghị Thỏa thuận Phân phối</h2>
            <p class="mt-1 text-xs text-slate-500">Khai báo ủy quyền phân phối giữa NXB và Gian hàng/Nhà phân phối để gắn vào quyền phát hành sách.</p>
          </div>

          <!-- Auto-selected Publisher Hint Banner -->
          <div v-if="selectedPublisherOrg" class="flex items-center gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/60 p-4 text-xs text-emerald-900">
            <span class="material-symbols-outlined text-xl text-[#00b14f] shrink-0">check_circle</span>
            <div>
              <span class="font-bold block">Đã tự động xác định bên Ủy quyền (Nhà xuất bản):</span>
              <span class="text-slate-700">Tổ chức <strong>{{ selectedPublisherOrg.display_name }}</strong> ({{ selectedPublisherOrg.legal_name || 'Hồ sơ thuộc quyền quản lý của bạn' }}) được chọn làm bên ủy quyền phát hành.</span>
            </div>
          </div>

          <form class="grid gap-5 md:grid-cols-2" @submit.prevent="submitAgreement">
            <div>
              <label for="agreement-publisher" class="mb-1.5 block text-xs font-bold text-slate-700">Nhà xuất bản (Bên ủy quyền) *</label>
              <Select
                id="agreement-publisher"
                v-model="agreementForm.publisher_organization_id"
                :options="selectableOrganizations.filter(item => item.organization_types?.includes('publisher') || item.organization_types?.includes('supplier'))"
                optionLabel="display_name"
                optionValue="id"
                filter
                placeholder="Chọn NXB ủy quyền"
                class="min-h-11 w-full text-xs"
                required
              />
            </div>
            <div>
              <label for="agreement-distributor" class="mb-1.5 block text-xs font-bold text-slate-700">Nhà phân phối / Nhà cung cấp *</label>
              <Select
                id="agreement-distributor"
                v-model="agreementForm.distributor_organization_id"
                :options="selectableOrganizations.filter(item => item.organization_types?.some(type => ['supplier', 'distributor', 'bookstore'].includes(type)))"
                optionLabel="display_name"
                optionValue="id"
                filter
                placeholder="Chọn bên nhận phân phối"
                class="min-h-11 w-full text-xs"
                required
              />
            </div>
            <div>
              <label for="agreement-coverage" class="mb-1.5 block text-xs font-bold text-slate-700">Phạm vi thỏa thuận *</label>
              <Select id="agreement-coverage" v-model="agreementForm.coverage" :options="coverageOptions" optionLabel="label" optionValue="value" class="min-h-11 w-full text-xs" />
            </div>
            <div v-if="agreementForm.coverage === 'books'">
              <label for="agreement-books" class="mb-1.5 block text-xs font-bold text-slate-700">Sách áp dụng thỏa thuận *</label>
              <MultiSelect
                v-if="selectableBooks.length"
                id="agreement-books"
                v-model="agreementForm.selected_book_ids"
                :options="selectableBooks"
                optionLabel="title"
                optionValue="id"
                filter
                placeholder="Chọn các đầu sách áp dụng"
                class="min-h-11 w-full text-xs"
                required
              />
              <InputText v-else id="agreement-books" v-model.trim="agreementForm.book_ids_text" placeholder="101, 102, 103" class="min-h-11 w-full text-xs font-mono" required />
            </div>
            <div>
              <label for="agreement-from" class="mb-1.5 block text-xs font-bold text-slate-700">Ngày bắt đầu có hiệu lực</label>
              <input id="agreement-from" v-model="agreementForm.effective_from" type="date" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 text-xs bg-white" />
            </div>
            <div>
              <label for="agreement-until" class="mb-1.5 block text-xs font-bold text-slate-700">Ngày hết hiệu lực</label>
              <input id="agreement-until" v-model="agreementForm.effective_until" type="date" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 text-xs bg-white" />
            </div>

            <!-- Sleek Authorization Document Field with Info Badge -->
            <div class="md:col-span-2 space-y-2">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-700">Chứng từ ủy quyền / Hợp đồng phân phối</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200/90 px-2.5 py-0.5 text-[11px] font-extrabold text-slate-600">
                  <span class="material-symbols-outlined text-[14px] text-[#00b14f]">info</span>
                  <span>Tùy chọn (Phục vụ Demo)</span>
                </span>
              </div>
              <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <FileUpload mode="basic" accept=".pdf,image/png,image/jpeg" :maxFileSize="5242880" chooseLabel="Chọn tệp đính kèm" customUpload @select="agreementDocument = $event.files?.[0] || null" />
                <span v-if="agreementDocument" class="text-xs font-mono text-[#00b14f] font-bold">✓ {{ agreementDocument.name }}</span>
                <span v-else class="text-xs text-slate-400">Chấp nhận .pdf, .jpg, .png (Tối đa 5MB). Trong môi trường Demo có thể bỏ qua.</span>
              </div>
            </div>

            <div class="md:col-span-2 flex justify-end pt-2 border-t border-slate-100">
              <Button type="submit" label="Gửi đề nghị thỏa thuận" icon="pi pi-send" :loading="savingAgreement" class="min-h-11 px-6 font-extrabold text-xs" />
            </div>
          </form>
        </section>

        <!-- SECTION 4: Distribution Agreements History -->
        <section class="space-y-4" aria-labelledby="agreements-title">
          <div class="border-b border-slate-200/80 pb-3">
            <h2 id="agreements-title" class="text-xl font-black text-slate-900 flex items-center gap-2">
              <span class="material-symbols-outlined text-[#00b14f]">history_edu</span>
              <span>Lịch sử Thỏa thuận Phân phối</span>
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">Danh sách các thỏa thuận ủy quyền phát hành đã gửi và trạng thái phê duyệt từ Admin.</p>
          </div>

          <div v-if="!agreements.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-xs text-slate-400">
            Chưa có thỏa thuận phân phối nào được ghi nhận.
          </div>

          <div v-else class="grid gap-4 md:grid-cols-2">
            <article
              v-for="agreement in agreements"
              :key="agreement.id"
              class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                  <div class="flex items-center gap-2 text-sm font-extrabold text-slate-900">
                    <span>{{ agreement.publisher?.display_name || 'NXB' }}</span>
                    <span class="material-symbols-outlined text-base text-[#00b14f]">arrow_forward</span>
                    <span>{{ agreement.distributor?.display_name || 'Nhà phân phối' }}</span>
                  </div>
                  <p class="text-xs text-slate-500">Phạm vi: <strong class="text-slate-800">{{ agreement.scope?.coverage === 'books' ? 'Danh sách sách cụ thể' : 'Toàn bộ Catalog' }}</strong></p>
                </div>

                <span
                  class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider border"
                  :class="['approved', 'active'].includes(agreement.status)
                    ? 'bg-emerald-50 text-[#00b14f] border-emerald-200'
                    : 'bg-amber-50 text-amber-800 border-amber-200'"
                >
                  {{ agreement.status }}
                </span>
              </div>

              <div class="text-[11px] text-slate-400 flex flex-wrap gap-x-4 border-t border-slate-100 pt-2">
                <span v-if="agreement.effective_from">Hiệu lực từ: {{ agreement.effective_from }}</span>
                <span v-if="agreement.effective_until">Đến: {{ agreement.effective_until }}</span>
              </div>

              <p v-if="agreement.last_review_reason" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 border border-slate-100" role="status">
                <strong>Phản hồi Admin:</strong> {{ agreement.last_review_reason }}
              </p>
            </article>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>
