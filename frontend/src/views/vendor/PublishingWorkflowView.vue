<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const toast = useToast()
const book = ref(null)
const busy = ref(false)
const loading = ref(true)
const error = ref('')
const scheduledFor = ref('')
const relationships = ref([])
const partyForm = ref({
  publisher_relationship_id: null,
  supplier_relationship_id: null,
  responsible_relationship_id: null,
})
const usesCommercialParties = computed(() => (book.value?.active_commercial_parties || []).length > 0)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const [response, organizationResponse] = await Promise.all([
      apiClient.get(`/api/vendor/books/${route.params.bookId}/publishing`),
      apiClient.get('/api/vendor/organizations'),
    ])
    book.value = response.data.data
    relationships.value = (organizationResponse.data.data.relationships || []).filter((item) => item.status === 'verified')
    const current = Object.fromEntries((book.value.active_commercial_parties || []).map((item) => [item.role, item.vendor_organization_relationship_id]))
    partyForm.value = {
      publisher_relationship_id: current.publisher || null,
      supplier_relationship_id: current.supplier || null,
      responsible_relationship_id: current.responsible_organization || null,
    }
  } catch (requestError) {
    book.value = null
    error.value = requestError.response?.data?.message || 'Không thể tải quy trình xuất bản.'
  } finally {
    loading.value = false
  }
}

const saveCommercialParties = async () => {
  busy.value = true
  try {
    await apiClient.put(`/api/vendor/books/${route.params.bookId}/commercial-parties`, partyForm.value)
    toast.add({ severity: 'success', summary: 'Đã lưu chuỗi cung ứng', detail: 'Thông tin Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm đã được khóa phiên bản.', life: 4000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Chưa thể lưu', detail: e.response?.data?.message || 'Chỉ dùng quan hệ đã được xác minh và còn hiệu lực.', life: 4500 })
  } finally { busy.value = false }
}

const transition = async (action) => {
  busy.value = true
  try {
    if (action === 'submit') await apiClient.post(`/api/vendor/books/${route.params.bookId}/submit`)
    if (action === 'draft') await apiClient.patch(`/api/vendor/books/${route.params.bookId}/return-to-draft`)
    if (action === 'publish') await apiClient.post(`/api/vendor/books/${route.params.bookId}/publish`, scheduledFor.value ? { scheduled_for: scheduledFor.value } : {})
    await load()
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Trạng thái xuất bản đã được cập nhật.', life: 2500 })
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.add({ severity: 'error', summary: 'Chưa đủ điều kiện', detail: errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'Không thể chuyển trạng thái.'), life: 5000 })
  } finally { busy.value = false }
}

onMounted(load)
</script>

<template>
  <section class="min-w-0 bg-slate-50 p-4 md:p-10" aria-labelledby="publishing-title">
    <div v-if="loading" role="status" aria-live="polite" class="py-12 text-center text-slate-500">Đang tải quy trình xuất bản…</div>
    <div v-else-if="error" role="alert" class="mx-auto max-w-3xl rounded-xl border border-rose-200 bg-rose-50 p-5 text-rose-800">{{ error }} <Button label="Thử lại" icon="pi pi-refresh" class="ml-3 min-h-11" @click="load" /></div>
    <div v-else-if="book" class="mx-auto max-w-4xl space-y-6">
      <header class="rounded-2xl bg-surface-container-lowest p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-primary">Quy trình đăng bán sách</p>
        <h1 id="publishing-title" class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">{{ book.title }}</h1>
        <p class="mt-2">Trạng thái: <strong>{{ book.publishing_status || 'draft (legacy)' }}</strong></p>
        <p v-if="book.publication_feedback" class="mt-3 rounded-lg bg-amber-50 p-3 text-amber-900">Phản hồi: {{ book.publication_feedback }}</p>
      </header>

      <section class="rounded-2xl bg-surface-container-lowest p-6 shadow-sm">
        <h2 class="text-xl font-bold">Thông tin xuất bản và cung ứng</h2>
        <p class="mt-2 text-sm text-on-surface-variant">Chỉ quan hệ đã được Admin xác minh mới có thể gắn vào sản phẩm.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
          <div><label for="party-publisher" class="mb-2 block text-sm font-semibold">Nhà xuất bản</label><Select id="party-publisher" v-model="partyForm.publisher_relationship_id" :options="relationships" optionValue="id" class="min-h-11 w-full"><template #option="{ option }">{{ option.organization?.display_name }} · {{ option.role }}</template><template #value="{ value }">{{ relationships.find(item => item.id === value)?.organization?.display_name || 'Chọn Nhà xuất bản' }}</template></Select></div>
          <div><label for="party-supplier" class="mb-2 block text-sm font-semibold">Nhà cung cấp</label><Select id="party-supplier" v-model="partyForm.supplier_relationship_id" :options="relationships" optionValue="id" class="min-h-11 w-full"><template #option="{ option }">{{ option.organization?.display_name }} · {{ option.role }}</template><template #value="{ value }">{{ relationships.find(item => item.id === value)?.organization?.display_name || 'Chọn Nhà cung cấp' }}</template></Select></div>
          <div><label for="party-responsible" class="mb-2 block text-sm font-semibold">Đơn vị chịu trách nhiệm</label><Select id="party-responsible" v-model="partyForm.responsible_relationship_id" :options="relationships" optionValue="id" class="min-h-11 w-full"><template #option="{ option }">{{ option.organization?.display_name }} · {{ option.role }}</template><template #value="{ value }">{{ relationships.find(item => item.id === value)?.organization?.display_name || 'Chọn đơn vị' }}</template></Select></div>
        </div>
        <Button label="Lưu chuỗi cung ứng" icon="pi pi-verified" :loading="busy" class="mt-5 min-h-11" @click="saveCommercialParties" />
        <router-link v-if="!relationships.length" to="/vendor/organizations" class="mt-4 block text-sm font-semibold text-primary">Đăng ký Nhà xuất bản hoặc Nhà cung cấp trước →</router-link>
      </section>

      <section v-if="!usesCommercialParties" class="rounded-2xl border border-warning/30 bg-warning/5 p-6">
        <h2 class="text-xl font-bold text-on-surface">Chưa đủ chuỗi cung ứng</h2>
        <p class="mt-2 text-sm leading-6 text-on-surface-variant">Hãy gắn Nhà xuất bản, Nhà cung cấp và đơn vị chịu trách nhiệm đã được xác minh trước khi gửi kiểm duyệt.</p>
      </section>

      <section class="rounded-2xl bg-surface-container-lowest p-6 shadow-sm">
        <h2 class="text-xl font-bold">Chuyển trạng thái</h2>
        <div class="mt-4 flex flex-wrap items-center gap-3">
          <Button v-if="['draft', 'changes_requested'].includes(book.publishing_status || 'draft')" label="Gửi kiểm duyệt" icon="pi pi-send" :loading="busy" @click="transition(book.publishing_status === 'changes_requested' ? 'draft' : 'submit')" />
          <Button v-if="book.publishing_status === 'draft' && book.publication_version > 1" label="Nộp lại" icon="pi pi-refresh" :loading="busy" @click="transition('submit')" />
          <template v-if="book.publishing_status === 'approved'">
            <InputText v-model="scheduledFor" type="datetime-local" />
            <Button :label="scheduledFor ? 'Lên lịch' : 'Xuất bản ngay'" icon="pi pi-calendar" :loading="busy" @click="transition('publish')" />
          </template>
        </div>
      </section>
    </div>
  </section>
</template>
