<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const managers = ref([])
const warehouses = ref([])
const form = ref({ email: '', warehouse_id: null, capabilities: ['view_inventory'] })
let loadController = null
const capabilityOptions = [
  { label: 'Xem tồn kho', value: 'view_inventory' },
  { label: 'Nhập kho', value: 'receive_stock' },
  { label: 'Xuất kho', value: 'dispatch_stock' },
  { label: 'Điều chuyển', value: 'transfer_stock' },
  { label: 'Kiểm kê', value: 'count_inventory' },
  { label: 'In chứng từ', value: 'print_documents' },
]
const capabilityLabels = Object.fromEntries(capabilityOptions.map(option => [option.value, option.label]))
const statusMeta = {
  invited: { label: 'Chờ phản hồi', icon: 'schedule', classes: 'bg-amber-100 text-amber-900' },
  active: { label: 'Đang hoạt động', icon: 'check_circle', classes: 'bg-emerald-100 text-emerald-800' },
  suspended: { label: 'Tạm dừng', icon: 'pause_circle', classes: 'bg-surface-container-high text-on-surface-variant' },
  revoked: { label: 'Đã thu hồi', icon: 'block', classes: 'bg-error-container text-on-error-container' },
  declined: { label: 'Đã từ chối', icon: 'cancel', classes: 'bg-error-container text-on-error-container' },
}
const summary = computed(() => ({
  total: managers.value.length,
  active: managers.value.filter(item => item.status === 'active').length,
  pending: managers.value.filter(item => item.status === 'invited').length,
}))
const managerStatus = status => statusMeta[status] || { label: status, icon: 'help', classes: 'bg-surface-container text-on-surface-variant' }
const formatDateTime = value => value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : 'Chưa có'

const load = async () => {
  loadController?.abort()
  const controller = new AbortController()
  loadController = controller
  loading.value = true
  error.value = ''
  try {
    const [managerResponse, warehouseResponse] = await Promise.all([
      apiClient.get('/api/vendor/warehouse-managers', { signal: controller.signal }),
      apiClient.get('/api/vendor/warehouses', { signal: controller.signal }),
    ])
    managers.value = managerResponse.data.data || []
    warehouses.value = warehouseResponse.data.warehouses || warehouseResponse.data.data || warehouseResponse.data || []
  } catch (exception) {
    if (exception.code === 'ERR_CANCELED') return
    error.value = exception.response?.data?.message || 'Không thể tải nhân sự kho. Bạn có thể chuyển trang hoặc thử lại.'
  } finally {
    if (!controller.signal.aborted) loading.value = false
  }
}

const invite = async () => {
  saving.value = true
  try {
    await apiClient.post('/api/vendor/warehouse-managers/invite', form.value)
    toast.add({ severity: 'success', summary: 'Đã gửi lời mời', detail: 'Nhân sự có thể chấp nhận hoặc từ chối ngay trong trang Thông báo.', life: 4000 })
    form.value = { email: '', warehouse_id: null, capabilities: ['view_inventory'] }
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể mời', detail: exception.response?.data?.message || 'Kiểm tra lại tài khoản và kho.', life: 4000 })
  } finally {
    saving.value = false
  }
}

const transition = async (assignment, toStatus) => {
  const reason = toStatus === 'active' ? null : window.prompt('Nhập lý do thay đổi phân công:')
  if (toStatus !== 'active' && !reason) return
  try {
    await apiClient.patch(`/api/vendor/warehouse-managers/${assignment.id}/transition`, { to_status: toStatus, reason })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Trạng thái phân công đã được thay đổi.', life: 3000 })
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: exception.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  }
}

const resendInvitation = async assignment => {
  try {
    const response = await apiClient.post(`/api/vendor/warehouse-managers/${assignment.id}/resend`)
    toast.add({ severity: 'success', summary: 'Đã gửi lại', detail: response.data.message, life: 3500 })
    await load()
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Không thể gửi lại', detail: exception.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  }
}

onMounted(load)
onBeforeUnmount(() => loadController?.abort())
</script>

<template>
  <section class="space-y-6" aria-labelledby="warehouse-manager-title">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div><p class="text-sm font-semibold uppercase tracking-wider text-primary">Vận hành kho</p><h1 id="warehouse-manager-title" class="mt-1 text-3xl font-bold text-on-surface">Nhân sự kho</h1><p class="mt-2 max-w-3xl text-on-surface-variant">Phân công đúng người, đúng kho và đúng quyền. Nhân sự chỉ thấy nghiệp vụ được cấp, không xem tài chính hay cấu hình gian hàng.</p></div>
      <div class="grid grid-cols-3 gap-3" aria-label="Tổng quan nhân sự kho">
        <div class="rounded-xl bg-surface-container-low px-4 py-3 text-center"><p class="text-2xl font-black">{{ summary.total }}</p><p class="text-xs text-on-surface-variant">Tổng số</p></div>
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-center"><p class="text-2xl font-black text-emerald-800">{{ summary.active }}</p><p class="text-xs text-emerald-800">Hoạt động</p></div>
        <div class="rounded-xl bg-amber-50 px-4 py-3 text-center"><p class="text-2xl font-black text-amber-900">{{ summary.pending }}</p><p class="text-xs text-amber-900">Chờ nhận</p></div>
      </div>
    </header>

    <form class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm" @submit.prevent="invite">
      <div class="mb-5"><h2 class="text-lg font-bold">Mời nhân sự mới</h2><p class="mt-1 text-sm text-on-surface-variant">Lời mời được gửi vào trang Thông báo của tài khoản đã đăng ký trên KomiBook.</p></div>
      <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1.4fr_auto] lg:items-start">
        <div><label for="manager-email" class="mb-2 block text-sm font-semibold">Email tài khoản</label><InputText id="manager-email" v-model="form.email" type="email" autocomplete="email" required class="min-h-11 w-full" /><p class="mt-2 text-xs leading-relaxed text-on-surface-variant">Chỉ tài khoản này mới có thể phản hồi.</p></div>
        <div><label for="manager-warehouse" class="mb-2 block text-sm font-semibold">Kho phân công</label><Select id="manager-warehouse" v-model="form.warehouse_id" :options="warehouses" optionLabel="name" optionValue="id" placeholder="Chọn kho" required class="min-h-11 w-full" /></div>
        <div><label for="manager-capabilities" class="mb-2 block text-sm font-semibold">Quyền vận hành</label><MultiSelect id="manager-capabilities" v-model="form.capabilities" :options="capabilityOptions" optionLabel="label" optionValue="value" placeholder="Chọn quyền" class="min-h-11 w-full" /><p class="mt-2 text-xs text-on-surface-variant">Có thể thay đổi hoặc thu hồi sau khi nhân sự chấp nhận.</p></div>
        <Button type="submit" label="Gửi lời mời" icon="pi pi-send" :loading="saving" class="min-h-11 lg:mt-7" />
      </div>
    </form>
    <section v-if="error" class="rounded-xl border border-error/30 bg-error-container p-5 text-on-error-container" role="alert">
      <p>{{ error }}</p><Button label="Thử lại" severity="secondary" outlined class="mt-3 min-h-11" @click="load" />
    </section>
    <div v-if="loading" class="h-52 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="!managers.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center"><h2 class="text-xl font-bold">Chưa có nhân sự kho</h2><p class="mt-2 text-on-surface-variant">Dùng biểu mẫu phía trên để tạo lời mời đầu tiên.</p></section>
    <section v-else class="grid gap-4 xl:grid-cols-2">
      <article v-for="assignment in managers" :key="assignment.id" class="flex flex-col rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex min-w-0 items-center gap-3"><div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 font-black text-primary">{{ assignment.user?.name?.charAt(0)?.toUpperCase() || '?' }}</div><div class="min-w-0"><h2 class="truncate text-lg font-bold text-on-surface">{{ assignment.user?.name }}</h2><p class="truncate text-sm text-on-surface-variant">{{ assignment.user?.email }}</p></div></div>
          <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-bold" :class="managerStatus(assignment.status).classes"><span class="material-symbols-outlined text-base" aria-hidden="true">{{ managerStatus(assignment.status).icon }}</span>{{ managerStatus(assignment.status).label }}</span>
        </div>
        <dl class="mt-5 grid gap-3 rounded-xl bg-surface-container-low p-4 sm:grid-cols-2">
          <div><dt class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">Kho phụ trách</dt><dd class="mt-1 font-bold text-on-surface">{{ assignment.warehouse?.name || 'Kho không còn tồn tại' }}</dd></div>
          <div><dt class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">Gửi lời mời</dt><dd class="mt-1 text-sm font-medium text-on-surface">{{ formatDateTime(assignment.invited_at) }}</dd></div>
        </dl>
        <div class="mt-4"><h3 class="text-sm font-bold text-on-surface">Quyền vận hành</h3><div class="mt-2 flex flex-wrap gap-2"><span v-for="capability in assignment.capabilities" :key="capability" class="inline-flex items-center gap-1 rounded-lg border border-outline-variant bg-surface-container-lowest px-2.5 py-1.5 text-xs font-semibold"><span class="material-symbols-outlined text-sm text-primary" aria-hidden="true">verified_user</span>{{ capabilityLabels[capability] || capability }}</span></div></div>
        <p v-if="assignment.last_reason" class="mt-4 rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant"><strong>Lý do gần nhất:</strong> {{ assignment.last_reason }}</p>
        <div class="mt-auto flex flex-wrap gap-2 pt-5">
          <Button v-if="assignment.status === 'invited'" label="Gửi lại lời mời" icon="pi pi-refresh" outlined class="min-h-11" @click="resendInvitation(assignment)" />
          <Button v-if="assignment.status === 'active'" label="Tạm dừng" severity="secondary" outlined class="min-h-11" @click="transition(assignment, 'suspended')" />
          <Button v-if="assignment.status === 'active'" label="Thu hồi" severity="danger" outlined class="min-h-11" @click="transition(assignment, 'revoked')" />
          <Button v-else-if="assignment.status === 'suspended'" label="Kích hoạt lại" class="min-h-11" @click="transition(assignment, 'active')" />
        </div>
      </article>
    </section>
  </section>
</template>
