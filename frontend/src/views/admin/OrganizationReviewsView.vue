<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const error = ref('')
const activeSection = ref('organizations')
const items = ref([])
const summary = ref({ organizations: 0, relationships: 0, agreements: 0, unlinked_books: 0 })
const pagination = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 })
const filters = ref({ search: '', status: '', data_mode: 'all', per_page: 10 })
const actionDialogVisible = ref(false)
const actionBusy = ref(false)
const actionError = ref('')
const pendingAction = ref(null)
const actionReason = ref('')

const sectionOptions = [
  {
    value: 'organizations',
    label: 'Tổ chức',
    shortLabel: 'Tổ chức',
    icon: 'pi pi-building',
    countKey: 'organizations',
  },
  {
    value: 'relationships',
    label: 'Quan hệ gian hàng',
    shortLabel: 'Quan hệ',
    icon: 'pi pi-share-alt',
    countKey: 'relationships',
  },
  {
    value: 'agreements',
    label: 'Thỏa thuận phân phối',
    shortLabel: 'Thỏa thuận',
    icon: 'pi pi-file-check',
    countKey: 'agreements',
  },
  {
    value: 'books',
    label: 'Sách thiếu liên kết',
    shortLabel: 'Sách chưa gắn',
    icon: 'pi pi-exclamation-circle',
    countKey: 'unlinked_books',
  },
]

const statusLabels = {
  pending_review: 'Chờ duyệt',
  submitted: 'Đã gửi duyệt',
  changes_requested: 'Cần bổ sung',
  verified: 'Đã xác minh',
  demo_accepted: 'Chấp nhận mô phỏng',
  suspended: 'Tạm dừng',
  rejected: 'Từ chối',
  revoked: 'Thu hồi',
  active: 'Đang hoạt động',
  inactive: 'Chưa hoạt động',
  approved: 'Đã duyệt',
  draft: 'Bản nháp',
}

const roleLabels = {
  self_legal_entity: 'Pháp nhân của gian hàng',
  publisher_partner: 'Đối tác xuất bản',
  supplier_partner: 'Đối tác cung ứng',
  authorized_distributor: 'Nhà phân phối được ủy quyền',
}

const organizationTypeLabels = {
  publisher: 'Nhà xuất bản',
  supplier: 'Nhà cung cấp',
  distributor: 'Nhà phân phối',
  bookstore: 'Nhà sách',
}

const statusOptionsBySection = {
  organizations: ['pending_review', 'demo_accepted', 'verified', 'suspended'],
  relationships: ['submitted', 'changes_requested', 'demo_accepted', 'verified', 'suspended'],
  agreements: ['submitted', 'changes_requested', 'demo_accepted', 'verified', 'suspended'],
  books: [],
}

const statusOptions = computed(() => [
  { label: 'Tất cả trạng thái', value: '' },
  ...statusOptionsBySection[activeSection.value].map((value) => ({
    label: statusLabels[value],
    value,
  })),
])

const dataModeOptions = computed(() => {
  const options = [
    { label: 'Tất cả chế độ dữ liệu', value: 'all' },
    { label: 'Dữ liệu mô phỏng', value: 'demo' },
    { label: 'Dữ liệu thật', value: 'real' },
  ]
  if (activeSection.value === 'organizations') {
    options.push({ label: 'Tham chiếu công khai', value: 'public_reference' })
  }
  return options
})

const currentSection = computed(() =>
  sectionOptions.find((section) => section.value === activeSection.value),
)
const needsReason = computed(() =>
  ['changes_requested', 'rejected', 'suspended', 'revoked'].includes(pendingAction.value?.status),
)

const load = async (page = 1) => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/organization-reviews', {
      params: {
        section: activeSection.value,
        search: filters.value.search || undefined,
        status: filters.value.status || undefined,
        data_mode: filters.value.data_mode,
        per_page: filters.value.per_page,
        page,
      },
    })
    const data = response.data.data
    summary.value = data.summary
    items.value = data.items?.data || []
    pagination.value = {
      current_page: data.items?.current_page || 1,
      last_page: data.items?.last_page || 1,
      total: data.items?.total || 0,
      from: data.items?.from || 0,
      to: data.items?.to || 0,
    }
  } catch (requestError) {
    items.value = []
    error.value =
      requestError.response?.data?.message || 'Không thể tải dữ liệu tổ chức và cung ứng.'
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.value = { search: '', status: '', data_mode: 'all', per_page: 10 }
  load(1)
}

const statusClass = (status) => {
  if (status === 'demo_accepted') return 'border-amber-300 bg-amber-50 text-amber-900'
  if (status === 'verified' || status === 'active' || status === 'approved')
    return 'border-primary/30 bg-primary-container text-on-primary-container'
  if (['suspended', 'rejected', 'revoked'].includes(status))
    return 'border-error/30 bg-error-container text-on-error-container'
  return 'border-outline-variant bg-surface-container text-on-surface-variant'
}

const dataModeLabel = (item) =>
  item.data_mode === 'demo' || item.is_demo
    ? 'Mô phỏng'
    : item.data_mode === 'public_reference'
      ? 'Tham chiếu công khai'
      : 'Dữ liệu thật'
const organizationTypes = (item) =>
  (item.organization_types || []).map((type) => organizationTypeLabels[type] || type).join(', ')
const agreementCoverage = (item) =>
  item.scope?.coverage === 'books' ? 'Sách cụ thể' : 'Toàn bộ danh mục'

const actionsFor = (item) => {
  if (activeSection.value === 'books') return []
  const actions = []
  const isDemo = item.data_mode === 'demo' || item.is_demo
  if (activeSection.value === 'organizations') {
    if (isDemo && item.status !== 'demo_accepted')
      actions.push({ status: 'demo_accepted', label: 'Chấp nhận mô phỏng', icon: 'pi pi-check' })
    if (!isDemo && item.status !== 'verified')
      actions.push({ status: 'verified', label: 'Xác minh', icon: 'pi pi-check' })
    if (item.status !== 'suspended')
      actions.push({
        status: 'suspended',
        label: 'Tạm dừng',
        icon: 'pi pi-pause',
        severity: 'danger',
      })
    return actions
  }
  if (isDemo && ['submitted', 'suspended'].includes(item.status))
    actions.push({ status: 'demo_accepted', label: 'Chấp nhận mô phỏng', icon: 'pi pi-check' })
  if (!isDemo && ['submitted', 'suspended'].includes(item.status))
    actions.push({ status: 'verified', label: 'Xác minh', icon: 'pi pi-check' })
  if (item.status === 'submitted') {
    actions.push({
      status: 'changes_requested',
      label: 'Yêu cầu bổ sung',
      icon: 'pi pi-pencil',
      severity: 'secondary',
    })
    actions.push({ status: 'rejected', label: 'Từ chối', icon: 'pi pi-times', severity: 'danger' })
  }
  if (['verified', 'demo_accepted', 'suspended'].includes(item.status))
    actions.push({ status: 'revoked', label: 'Thu hồi', icon: 'pi pi-ban', severity: 'danger' })
  return actions
}

const itemName = (item) => {
  if (activeSection.value === 'organizations') return item.display_name
  if (activeSection.value === 'relationships')
    return `${item.vendor?.shop_name || 'Gian hàng thiếu dữ liệu'} → ${item.organization?.display_name || 'Tổ chức thiếu dữ liệu'}`
  if (activeSection.value === 'agreements')
    return `${item.publisher?.display_name || 'NXB thiếu dữ liệu'} → ${item.distributor?.display_name || 'Nhà phân phối thiếu dữ liệu'}`
  return item.title
}

const openAction = (item, action) => {
  pendingAction.value = { item, ...action }
  actionReason.value = ''
  actionError.value = ''
  actionDialogVisible.value = true
}

const submitAction = async () => {
  if (needsReason.value && !actionReason.value.trim()) {
    actionError.value = 'Vui lòng nhập lý do để Admin và đối tác có thể đối chiếu quyết định.'
    return
  }
  actionBusy.value = true
  actionError.value = ''
  try {
    const item = pendingAction.value.item
    const endpoint =
      activeSection.value === 'organizations'
        ? `/api/admin/organizations/${item.id}/transition`
        : activeSection.value === 'relationships'
          ? `/api/admin/organization-relationships/${item.id}/transition`
          : `/api/admin/distribution-agreements/${item.id}/transition`
    await apiClient.patch(endpoint, {
      to_status: pendingAction.value.status,
      reason: actionReason.value.trim() || null,
    })
    actionDialogVisible.value = false
    toast.add({
      severity: 'success',
      summary: 'Đã cập nhật trạng thái',
      detail: itemName(item),
      life: 3000,
    })
    await load(pagination.value.current_page)
  } catch (requestError) {
    actionError.value =
      requestError.response?.data?.message ||
      'Không thể cập nhật trạng thái. Vui lòng kiểm tra lại dữ liệu.'
  } finally {
    actionBusy.value = false
  }
}

watch(activeSection, () => {
  filters.value.search = ''
  filters.value.status = ''
  filters.value.data_mode = 'all'
  load(1)
})

onMounted(() => load())
</script>

<template>
  <main id="main-content" class="space-y-6" tabindex="-1">
    <header>
      <p class="text-sm font-semibold uppercase tracking-wider text-primary">Kiểm duyệt đối tác</p>
      <h1 class="mt-1 text-3xl font-bold text-on-surface">Tổ chức & quan hệ cung ứng</h1>
      <p class="mt-2 max-w-3xl leading-6 text-on-surface-variant">
        Theo dõi riêng hồ sơ tổ chức, quan hệ gian hàng, thỏa thuận phân phối và những sách chưa
        được gắn chuỗi cung ứng.
      </p>
    </header>

    <section
      aria-label="Tổng quan tổ chức và cung ứng"
      class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
    >
      <button
        v-for="section in sectionOptions"
        :key="section.value"
        type="button"
        :class="[
          'min-h-24 rounded-xl border p-4 text-left transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
          activeSection === section.value
            ? 'border-primary bg-primary-container'
            : 'border-outline-variant bg-surface-container-lowest hover:bg-surface-container-low',
        ]"
        @click="activeSection = section.value"
      >
        <span class="flex items-center justify-between gap-3">
          <span class="text-sm font-semibold text-on-surface-variant">{{
            section.shortLabel
          }}</span>
          <i :class="section.icon" aria-hidden="true"></i>
        </span>
        <strong class="mt-2 block text-2xl text-on-surface">{{ summary[section.countKey] }}</strong>
      </button>
    </section>

    <section class="rounded-2xl border border-outline-variant bg-surface-container-lowest">
      <div class="border-b border-outline-variant p-3">
        <div
          class="flex gap-2 overflow-x-auto"
          role="tablist"
          aria-label="Loại dữ liệu cần quản lý"
        >
          <button
            v-for="section in sectionOptions"
            :id="`tab-${section.value}`"
            :key="section.value"
            type="button"
            role="tab"
            :aria-selected="activeSection === section.value"
            :aria-controls="`panel-${section.value}`"
            :class="[
              'min-h-11 shrink-0 rounded-lg px-4 text-sm font-semibold transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
              activeSection === section.value
                ? 'bg-primary text-on-primary'
                : 'text-on-surface-variant hover:bg-surface-container',
            ]"
            @click="activeSection = section.value"
          >
            {{ section.label }}
          </button>
        </div>
      </div>

      <form
        class="grid gap-3 border-b border-outline-variant p-4 md:grid-cols-[minmax(220px,1fr)_220px_220px_auto]"
        aria-label="Bộ lọc dữ liệu"
        @submit.prevent="load(1)"
      >
        <div>
          <label for="organization-search" class="mb-1 block text-sm font-semibold text-on-surface"
            >Tìm kiếm</label
          >
          <InputText
            id="organization-search"
            v-model="filters.search"
            class="min-h-11 w-full"
            :placeholder="
              activeSection === 'books'
                ? 'Tên sách hoặc gian hàng'
                : 'Tên đơn vị, gian hàng hoặc mã demo'
            "
          />
        </div>
        <div v-if="activeSection !== 'books'">
          <label for="organization-status" class="mb-1 block text-sm font-semibold text-on-surface"
            >Trạng thái</label
          >
          <Select
            id="organization-status"
            v-model="filters.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            class="min-h-11 w-full"
          />
        </div>
        <div>
          <label
            for="organization-data-mode"
            class="mb-1 block text-sm font-semibold text-on-surface"
            >Chế độ dữ liệu</label
          >
          <Select
            id="organization-data-mode"
            v-model="filters.data_mode"
            :options="dataModeOptions"
            optionLabel="label"
            optionValue="value"
            class="min-h-11 w-full"
          />
        </div>
        <div class="flex items-end gap-2">
          <Button
            type="submit"
            label="Lọc"
            icon="pi pi-filter"
            :loading="loading"
            class="min-h-11"
          />
          <Button
            type="button"
            label="Đặt lại"
            severity="secondary"
            outlined
            class="min-h-11"
            @click="resetFilters"
          />
        </div>
      </form>

      <div
        :id="`panel-${activeSection}`"
        role="tabpanel"
        :aria-labelledby="`tab-${activeSection}`"
        class="p-4"
      >
        <div
          v-if="error"
          class="rounded-xl bg-error-container p-4 text-on-error-container"
          role="alert"
        >
          <p>{{ error }}</p>
          <Button
            label="Thử lại"
            severity="danger"
            outlined
            class="mt-3 min-h-11"
            @click="load(pagination.current_page)"
          />
        </div>
        <div v-else-if="loading" class="space-y-3" aria-label="Đang tải dữ liệu">
          <div
            v-for="index in 4"
            :key="index"
            class="h-20 animate-pulse rounded-xl bg-surface-container"
          ></div>
        </div>
        <div
          v-else-if="!items.length"
          class="rounded-xl border border-dashed border-outline-variant p-8 text-center"
        >
          <i class="pi pi-inbox text-3xl text-on-surface-variant" aria-hidden="true"></i>
          <h2 class="mt-3 text-lg font-bold text-on-surface">Không có dữ liệu phù hợp</h2>
          <p class="mt-1 text-sm text-on-surface-variant">
            Hãy thay đổi bộ lọc hoặc chọn khu vực quản lý khác.
          </p>
        </div>
        <template v-else>
          <div
            class="hidden overflow-x-auto lg:block"
            :aria-label="`Bảng ${currentSection.label.toLowerCase()}`"
          >
            <table class="w-full min-w-[880px] border-collapse text-left text-sm">
              <thead class="bg-surface-container-low text-on-surface-variant">
                <tr v-if="activeSection === 'organizations'">
                  <th class="p-3 font-semibold">Tổ chức</th>
                  <th class="p-3 font-semibold">Loại hình</th>
                  <th class="p-3 font-semibold">Dữ liệu</th>
                  <th class="p-3 font-semibold">Trạng thái</th>
                  <th class="p-3 font-semibold">Thao tác</th>
                </tr>
                <tr v-else-if="activeSection === 'relationships'">
                  <th class="p-3 font-semibold">Gian hàng</th>
                  <th class="p-3 font-semibold">Quan hệ</th>
                  <th class="p-3 font-semibold">Tổ chức</th>
                  <th class="p-3 font-semibold">Trạng thái</th>
                  <th class="p-3 font-semibold">Thao tác</th>
                </tr>
                <tr v-else-if="activeSection === 'agreements'">
                  <th class="p-3 font-semibold">Nhà xuất bản</th>
                  <th class="p-3 font-semibold">Phân phối tới</th>
                  <th class="p-3 font-semibold">Phạm vi</th>
                  <th class="p-3 font-semibold">Trạng thái</th>
                  <th class="p-3 font-semibold">Thao tác</th>
                </tr>
                <tr v-else>
                  <th class="p-3 font-semibold">Sách</th>
                  <th class="p-3 font-semibold">Gian hàng</th>
                  <th class="p-3 font-semibold">Vendor</th>
                  <th class="p-3 font-semibold">Thiếu liên kết</th>
                  <th class="p-3 font-semibold">Xem</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant">
                <tr
                  v-for="item in items"
                  :key="item.id"
                  class="align-top hover:bg-surface-container-low/60"
                >
                  <template v-if="activeSection === 'organizations'">
                    <td class="p-3">
                      <strong class="block text-on-surface">{{ item.display_name }}</strong
                      ><span class="mt-1 block text-xs text-on-surface-variant">{{
                        item.legal_name
                      }}</span>
                    </td>
                    <td class="p-3 text-on-surface-variant">{{ organizationTypes(item) }}</td>
                    <td class="p-3">
                      <span
                        class="font-semibold"
                        :class="
                          item.data_mode === 'demo' ? 'text-amber-800' : 'text-on-surface-variant'
                        "
                        >{{ dataModeLabel(item) }}</span
                      >
                    </td>
                    <td class="p-3">
                      <span
                        :class="[
                          'inline-flex rounded-full border px-3 py-1 font-semibold',
                          statusClass(item.status),
                        ]"
                        >{{ statusLabels[item.status] || item.status }}</span
                      >
                    </td>
                  </template>
                  <template v-else-if="activeSection === 'relationships'">
                    <td class="p-3">
                      <strong class="block text-on-surface">{{
                        item.vendor?.shop_name || 'Gian hàng thiếu dữ liệu'
                      }}</strong
                      ><span class="mt-1 block text-xs text-on-surface-variant">{{
                        statusLabels[item.vendor?.status] || item.vendor?.status
                      }}</span>
                    </td>
                    <td class="p-3">
                      <span class="inline-flex items-center gap-2 font-semibold text-primary"
                        ><i class="pi pi-arrow-right" aria-hidden="true"></i
                        >{{ roleLabels[item.role] || item.role }}</span
                      ><span v-if="item.demo_reference" class="mt-1 block text-xs text-amber-800">{{
                        item.demo_reference
                      }}</span>
                    </td>
                    <td class="p-3 font-semibold text-on-surface">
                      {{ item.organization?.display_name || 'Tổ chức thiếu dữ liệu' }}
                    </td>
                    <td class="p-3">
                      <span
                        :class="[
                          'inline-flex rounded-full border px-3 py-1 font-semibold',
                          statusClass(item.status),
                        ]"
                        >{{ statusLabels[item.status] || item.status }}</span
                      >
                    </td>
                  </template>
                  <template v-else-if="activeSection === 'agreements'">
                    <td class="p-3 font-semibold text-on-surface">
                      {{ item.publisher?.display_name || 'NXB thiếu dữ liệu' }}
                    </td>
                    <td class="p-3">
                      <span class="inline-flex items-center gap-2 font-semibold text-primary"
                        ><i class="pi pi-arrow-right" aria-hidden="true"></i
                        >{{ item.distributor?.display_name || 'Nhà phân phối thiếu dữ liệu' }}</span
                      ><span v-if="item.demo_reference" class="mt-1 block text-xs text-amber-800">{{
                        item.demo_reference
                      }}</span>
                    </td>
                    <td class="p-3 text-on-surface-variant">{{ agreementCoverage(item) }}</td>
                    <td class="p-3">
                      <span
                        :class="[
                          'inline-flex rounded-full border px-3 py-1 font-semibold',
                          statusClass(item.status),
                        ]"
                        >{{ statusLabels[item.status] || item.status }}</span
                      >
                    </td>
                  </template>
                  <template v-else>
                    <td class="p-3">
                      <strong class="block text-on-surface">{{ item.title }}</strong
                      ><span class="mt-1 block text-xs text-on-surface-variant"
                        >#{{ item.id }}</span
                      >
                    </td>
                    <td class="p-3 font-semibold text-on-surface">
                      {{ item.vendor?.shop_name || 'Gian hàng thiếu dữ liệu' }}
                    </td>
                    <td class="p-3">
                      <span
                        :class="[
                          'inline-flex rounded-full border px-3 py-1 font-semibold',
                          statusClass(item.vendor?.status),
                        ]"
                        >{{ statusLabels[item.vendor?.status] || item.vendor?.status }}</span
                      ><span
                        v-if="item.vendor?.is_demo"
                        class="mt-1 block text-xs font-semibold text-amber-800"
                        >Vendor mô phỏng</span
                      >
                    </td>
                    <td class="p-3 text-on-surface-variant">
                      NXB · Nhà cung cấp · Đơn vị chịu trách nhiệm
                    </td>
                  </template>
                  <td class="p-3">
                    <div v-if="activeSection !== 'books'" class="flex flex-wrap gap-2">
                      <Button
                        v-for="action in actionsFor(item)"
                        :key="action.status"
                        :label="action.label"
                        :icon="action.icon"
                        :severity="action.severity"
                        size="small"
                        outlined
                        class="min-h-11"
                        @click="openAction(item, action)"
                      />
                      <span v-if="!actionsFor(item).length" class="text-sm text-on-surface-variant"
                        >Không có thao tác</span
                      >
                    </div>
                    <router-link
                      v-else
                      :to="`/book/${item.slug}`"
                      class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-primary px-3 font-semibold text-primary no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                      ><i class="pi pi-external-link" aria-hidden="true"></i>Xem sách</router-link
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="space-y-3 lg:hidden">
            <article
              v-for="item in items"
              :key="item.id"
              class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h2 class="font-bold text-on-surface">{{ itemName(item) }}</h2>
                  <p
                    v-if="activeSection === 'organizations'"
                    class="mt-1 text-sm text-on-surface-variant"
                  >
                    {{ organizationTypes(item) }}
                  </p>
                  <p
                    v-else-if="activeSection === 'relationships'"
                    class="mt-1 text-sm text-on-surface-variant"
                  >
                    {{ roleLabels[item.role] || item.role }}
                  </p>
                  <p
                    v-else-if="activeSection === 'agreements'"
                    class="mt-1 text-sm text-on-surface-variant"
                  >
                    {{ agreementCoverage(item) }}
                  </p>
                  <p v-else class="mt-1 text-sm text-on-surface-variant">
                    {{ item.vendor?.shop_name || 'Gian hàng thiếu dữ liệu' }}
                  </p>
                </div>
                <span
                  v-if="activeSection !== 'books'"
                  :class="[
                    'shrink-0 rounded-full border px-3 py-1 text-xs font-semibold',
                    statusClass(item.status),
                  ]"
                  >{{ statusLabels[item.status] || item.status }}</span
                >
                <span
                  v-else
                  class="shrink-0 rounded-full border border-outline-variant bg-surface-container px-3 py-1 text-xs font-semibold"
                  >Chưa gắn</span
                >
              </div>
              <p
                v-if="item.data_mode === 'demo' || item.is_demo || item.vendor?.is_demo"
                class="mt-3 text-sm font-semibold text-amber-800"
              >
                Dữ liệu mô phỏng
              </p>
              <div v-if="activeSection !== 'books'" class="mt-4 flex flex-wrap gap-2">
                <Button
                  v-for="action in actionsFor(item)"
                  :key="action.status"
                  :label="action.label"
                  :icon="action.icon"
                  :severity="action.severity"
                  size="small"
                  outlined
                  class="min-h-11"
                  @click="openAction(item, action)"
                />
                <span v-if="!actionsFor(item).length" class="text-sm text-on-surface-variant"
                  >Không có thao tác khả dụng.</span
                >
              </div>
              <router-link
                v-else
                :to="`/book/${item.slug}`"
                class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg border border-primary px-3 font-semibold text-primary no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                ><i class="pi pi-external-link" aria-hidden="true"></i>Xem sách</router-link
              >
            </article>
          </div>

          <div
            class="mt-4 flex flex-col gap-3 border-t border-outline-variant pt-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <p class="text-sm text-on-surface-variant">
              Hiển thị {{ pagination.from || 0 }}–{{ pagination.to || 0 }} trong
              {{ pagination.total }} mục
            </p>
            <div class="flex items-center gap-2">
              <Button
                label="Trang trước"
                icon="pi pi-chevron-left"
                severity="secondary"
                outlined
                class="min-h-11"
                :disabled="pagination.current_page <= 1"
                @click="load(pagination.current_page - 1)"
              />
              <span class="min-w-20 text-center text-sm font-semibold"
                >{{ pagination.current_page }}/{{ pagination.last_page }}</span
              >
              <Button
                label="Trang sau"
                icon="pi pi-chevron-right"
                iconPos="right"
                severity="secondary"
                outlined
                class="min-h-11"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="load(pagination.current_page + 1)"
              />
            </div>
          </div>
        </template>
      </div>
    </section>

    <Dialog
      v-model:visible="actionDialogVisible"
      modal
      :header="pendingAction?.label || 'Cập nhật trạng thái'"
      class="w-[min(92vw,36rem)]"
    >
      <div v-if="pendingAction" class="space-y-4">
        <div class="rounded-xl bg-surface-container p-4">
          <p class="text-sm text-on-surface-variant">Đối tượng</p>
          <p class="mt-1 font-bold text-on-surface">{{ itemName(pendingAction.item) }}</p>
          <p class="mt-2 text-sm text-on-surface-variant">
            Trạng thái mới:
            <strong>{{ statusLabels[pendingAction.status] || pendingAction.status }}</strong>
          </p>
        </div>
        <div v-if="needsReason">
          <label
            for="organization-action-reason"
            class="mb-2 block text-sm font-semibold text-on-surface"
            >Lý do quyết định *</label
          >
          <Textarea
            id="organization-action-reason"
            v-model="actionReason"
            rows="4"
            class="w-full"
            aria-describedby="organization-action-help"
          />
          <p id="organization-action-help" class="mt-1 text-sm text-on-surface-variant">
            Lý do sẽ được lưu để đối chiếu lịch sử kiểm duyệt.
          </p>
        </div>
        <p
          v-if="actionError"
          class="rounded-lg bg-error-container p-3 text-sm text-on-error-container"
          role="alert"
        >
          {{ actionError }}
        </p>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button
            label="Hủy"
            severity="secondary"
            outlined
            class="min-h-11"
            :disabled="actionBusy"
            @click="actionDialogVisible = false"
          />
          <Button
            :label="pendingAction.label"
            :icon="pendingAction.icon"
            :severity="pendingAction.severity"
            class="min-h-11"
            :loading="actionBusy"
            @click="submitAction"
          />
        </div>
      </div>
    </Dialog>
  </main>
</template>
