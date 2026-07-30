<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'

const toast = useToast()
const vendors = ref([])
const loading = ref(true)
const error = ref('')
const selectedVendor = ref(null)
const feedbackAction = ref('rejected')
const feedbackReason = ref('')
const dialogVisible = ref(false)

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/approvals/vendors')
    vendors.value = response.data?.data?.vendors || []
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Không thể tải danh sách hồ sơ Nhà bán.'
  } finally {
    loading.value = false
  }
}

const transition = async (vendor, toStatus, reason = null) => {
  try {
    await apiClient.patch(`/api/admin/approvals/vendors/${vendor.id}/transition`, {
      to_status: toStatus,
      reason,
    })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: 'Trạng thái hồ sơ Nhà bán đã được cập nhật.', life: 3000 })
    dialogVisible.value = false
    await load()
  } catch (requestError) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: requestError.response?.data?.message || 'Vui lòng thử lại.', life: 3500 })
  }
}

const openFeedback = (vendor, action) => {
  selectedVendor.value = vendor
  feedbackAction.value = action
  feedbackReason.value = ''
  dialogVisible.value = true
}

const submitFeedback = () => {
  if (!feedbackReason.value.trim()) return
  transition(selectedVendor.value, feedbackAction.value, feedbackReason.value.trim())
}

onMounted(load)
</script>

<template>
  <main class="min-h-screen bg-surface-container-low p-4 sm:p-6 lg:p-8" aria-labelledby="vendor-approval-heading">
    <Toast />
    <header class="mx-auto mb-6 max-w-6xl">
      <p class="font-label-md font-bold text-secondary">Đối tác thương mại</p>
      <h1 id="vendor-approval-heading" class="mt-1 text-3xl font-bold text-primary">Kiểm duyệt Nhà bán</h1>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-on-surface-variant">Xác minh pháp lý, đơn vị chịu trách nhiệm và thông tin vận hành trước khi kích hoạt gian hàng.</p>
    </header>

    <section class="mx-auto max-w-6xl space-y-4">
      <div class="flex min-h-20 items-center justify-between rounded-xl border border-outline-variant/30 bg-surface p-5 shadow-sm">
        <div>
          <p class="text-sm text-on-surface-variant">Hồ sơ cần xử lý</p>
          <p class="text-3xl font-bold text-on-surface">{{ vendors.length }}</p>
        </div>
        <span class="material-symbols-outlined text-4xl text-primary" aria-hidden="true">storefront</span>
      </div>

      <div v-if="error" role="alert" class="rounded-xl border border-error/30 bg-error/5 p-4 text-error">
        <p>{{ error }}</p>
        <Button label="Thử lại" class="mt-3 min-h-11" @click="load" />
      </div>
      <div v-else-if="loading" role="status" aria-live="polite" class="rounded-xl bg-surface p-8 text-center text-on-surface-variant">Đang tải hồ sơ Nhà bán…</div>
      <div v-else-if="!vendors.length" class="rounded-xl border border-dashed border-outline-variant p-10 text-center text-on-surface-variant">Không có hồ sơ Nhà bán nào đang chờ duyệt.</div>

      <article v-for="vendor in vendors" v-else :key="vendor.id" class="rounded-xl border border-outline-variant/30 bg-surface p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-bold text-on-surface">{{ vendor.shop_name }}</h2>
              <span class="rounded-full bg-primary-container px-3 py-1 text-xs font-bold text-on-primary-container">{{ vendor.onboarding_status }}</span>
            </div>
            <p class="mt-2 text-sm leading-6 text-on-surface-variant">{{ vendor.description || 'Chưa cung cấp mô tả.' }}</p>
            <p class="mt-2 text-xs text-on-surface-variant">Đại diện: {{ vendor.user?.name || 'Chưa cập nhật' }} · {{ vendor.user?.email || 'Chưa cập nhật email' }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button v-if="['submitted', 'resubmitted'].includes(vendor.onboarding_status)" label="Bắt đầu duyệt" icon="pi pi-search" class="min-h-11" @click="transition(vendor, 'under_review')" />
            <template v-if="vendor.onboarding_status === 'under_review'">
              <Button label="Phê duyệt" icon="pi pi-check" severity="success" class="min-h-11" @click="transition(vendor, 'approved')" />
              <Button label="Yêu cầu bổ sung" icon="pi pi-pencil" severity="warn" outlined class="min-h-11" @click="openFeedback(vendor, 'changes_requested')" />
              <Button label="Từ chối" icon="pi pi-times" severity="danger" outlined class="min-h-11" @click="openFeedback(vendor, 'rejected')" />
            </template>
          </div>
        </div>
      </article>
    </section>

    <Dialog v-model:visible="dialogVisible" modal :header="feedbackAction === 'changes_requested' ? 'Yêu cầu bổ sung hồ sơ' : 'Từ chối hồ sơ'" :style="{ width: 'min(92vw, 500px)' }">
      <label class="block space-y-2 text-sm font-bold text-on-surface">
        <span>Lý do phản hồi</span>
        <InputText v-model="feedbackReason" class="min-h-11 w-full" />
      </label>
      <template #footer>
        <Button label="Hủy" text class="min-h-11" @click="dialogVisible = false" />
        <Button label="Xác nhận" severity="danger" class="min-h-11" :disabled="!feedbackReason.trim()" @click="submitFeedback" />
      </template>
    </Dialog>
  </main>
</template>
