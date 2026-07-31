<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
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
    toast.add({ severity: 'success', summary: 'Đã tạo lời mời', detail: 'Người được mời cần tự chấp nhận trước khi có quyền.', life: 3500 })
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
  await apiClient.patch(`/api/vendor/warehouse-managers/${assignment.id}/transition`, { to_status: toStatus, reason })
  await load()
}

onMounted(load)
onBeforeUnmount(() => loadController?.abort())
</script>

<template>
  <section class="space-y-6" aria-labelledby="warehouse-manager-title">
    <header><p class="text-sm font-semibold uppercase tracking-wider text-primary">Nhà bán</p><h1 id="warehouse-manager-title" class="mt-1 text-3xl font-bold text-on-surface">Nhân sự kho</h1><p class="mt-2 text-on-surface-variant">Cấp quyền riêng cho từng kho; nhân sự kho không thể xem tài chính, giá hay cấu hình gian hàng.</p></header>
    <form class="grid gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 lg:grid-cols-[1fr_1fr_1.4fr_auto] lg:items-end" @submit.prevent="invite">
      <div><label for="manager-email" class="mb-2 block text-sm font-semibold">Email tài khoản</label><InputText id="manager-email" v-model="form.email" type="email" autocomplete="email" required class="min-h-11 w-full" /></div>
      <div><label for="manager-warehouse" class="mb-2 block text-sm font-semibold">Kho phân công</label><Select id="manager-warehouse" v-model="form.warehouse_id" :options="warehouses" optionLabel="name" optionValue="id" required class="min-h-11 w-full" /></div>
      <div><label for="manager-capabilities" class="mb-2 block text-sm font-semibold">Quyền vận hành</label><MultiSelect id="manager-capabilities" v-model="form.capabilities" :options="capabilityOptions" optionLabel="label" optionValue="value" class="min-h-11 w-full" /></div>
      <Button type="submit" label="Gửi lời mời" icon="pi pi-send" :loading="saving" class="min-h-11" />
    </form>
    <section v-if="error" class="rounded-xl border border-error/30 bg-error-container p-5 text-on-error-container" role="alert">
      <p>{{ error }}</p><Button label="Thử lại" severity="secondary" outlined class="mt-3 min-h-11" @click="load" />
    </section>
    <div v-if="loading" class="h-52 animate-pulse rounded-xl bg-surface-container"></div>
    <section v-else-if="!managers.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center"><h2 class="text-xl font-bold">Chưa có nhân sự kho</h2><p class="mt-2 text-on-surface-variant">Dùng biểu mẫu phía trên để tạo lời mời đầu tiên.</p></section>
    <section v-else class="grid gap-4 lg:grid-cols-2">
      <article v-for="assignment in managers" :key="assignment.id" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex items-start justify-between gap-3"><div><h2 class="font-bold text-on-surface">{{ assignment.user?.name }}</h2><p class="text-sm text-on-surface-variant">{{ assignment.user?.email }}</p></div><span class="rounded-full bg-secondary-container px-3 py-1 text-sm font-semibold text-on-secondary-container">{{ assignment.status }}</span></div>
        <p class="mt-4"><span class="text-sm text-on-surface-variant">Kho:</span> <strong>{{ assignment.warehouse?.name }}</strong></p>
        <div class="mt-3 flex flex-wrap gap-2"><span v-for="capability in assignment.capabilities" :key="capability" class="rounded-full bg-surface-container px-2 py-1 text-xs">{{ capability }}</span></div>
        <div v-if="assignment.status === 'active'" class="mt-5 flex flex-wrap gap-2"><Button label="Tạm dừng" severity="secondary" outlined class="min-h-11" @click="transition(assignment, 'suspended')" /><Button label="Thu hồi" severity="danger" outlined class="min-h-11" @click="transition(assignment, 'revoked')" /></div>
        <Button v-else-if="assignment.status === 'suspended'" label="Kích hoạt lại" class="mt-5 min-h-11" @click="transition(assignment, 'active')" />
      </article>
    </section>
  </section>
</template>
