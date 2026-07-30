<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Select from 'primevue/select'

const loading = ref(true)
const error = ref('')
const assignments = ref([])
const selectedId = ref(null)
const dashboard = ref(null)

const selectedAssignment = computed(() => assignments.value.find((item) => item.id === selectedId.value))

const loadAssignments = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/warehouse-manager/assignments')
    assignments.value = response.data.data || []
    selectedId.value = assignments.value[0]?.id || null
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải danh sách kho được giao.'
  } finally {
    loading.value = false
  }
}

const loadDashboard = async () => {
  if (!selectedId.value) {
    dashboard.value = null
    return
  }
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get(`/api/warehouse-manager/assignments/${selectedId.value}/dashboard`)
    dashboard.value = response.data.data
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải tổng quan kho.'
  } finally {
    loading.value = false
  }
}

watch(selectedId, loadDashboard)
onMounted(loadAssignments)
</script>

<template>
  <main id="main-content" class="space-y-6" tabindex="-1">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-primary">Kênh Quản lý kho</p>
        <h1 class="mt-1 text-3xl font-bold text-on-surface">Tổng quan kho được giao</h1>
        <p class="mt-2 max-w-2xl text-on-surface-variant">Theo dõi tồn kho và công việc trong đúng phạm vi Nhà bán đã phân công.</p>
      </div>
      <div v-if="assignments.length" class="w-full lg:w-80">
        <label for="warehouse-assignment" class="mb-2 block text-sm font-semibold text-on-surface">Kho đang thao tác</label>
        <Select
          id="warehouse-assignment"
          v-model="selectedId"
          :options="assignments"
          optionLabel="warehouse.name"
          optionValue="id"
          class="min-h-11 w-full"
        />
      </div>
    </header>

    <section v-if="loading" class="grid gap-4 sm:grid-cols-3" aria-label="Đang tải tổng quan">
      <div v-for="index in 3" :key="index" class="h-28 animate-pulse rounded-xl bg-surface-container"></div>
    </section>

    <section v-else-if="error" class="rounded-xl border border-error/30 bg-error-container p-6 text-on-error-container" role="alert">
      <p class="font-semibold">{{ error }}</p>
      <Button label="Thử lại" icon="pi pi-refresh" severity="danger" class="mt-4 min-h-11" @click="loadAssignments" />
    </section>

    <section v-else-if="!assignments.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center">
      <i class="pi pi-box text-4xl text-primary" aria-hidden="true"></i>
      <h2 class="mt-4 text-xl font-bold text-on-surface">Chưa có kho được giao</h2>
      <p class="mt-2 text-on-surface-variant">Nhà bán cần gửi lời mời và bạn phải chấp nhận trước khi bắt đầu.</p>
    </section>

    <template v-else-if="dashboard">
      <section class="grid gap-4 sm:grid-cols-3" aria-label="Chỉ số kho">
        <article class="rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-5">
          <p class="text-sm text-on-surface-variant">Mã sách trong kho</p>
          <p class="mt-2 text-3xl font-bold tabular-nums text-primary">{{ dashboard.metrics.sku_count }}</p>
        </article>
        <article class="rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-5">
          <p class="text-sm text-on-surface-variant">Tổng số lượng</p>
          <p class="mt-2 text-3xl font-bold tabular-nums text-primary">{{ dashboard.metrics.total_units }}</p>
        </article>
        <article class="rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-5">
          <p class="text-sm text-on-surface-variant">Sắp hết hàng</p>
          <p class="mt-2 text-3xl font-bold tabular-nums text-error">{{ dashboard.metrics.low_stock_count }}</p>
        </article>
      </section>

      <section class="rounded-xl border border-outline-variant/50 bg-surface-container-lowest p-5">
        <h2 class="text-xl font-bold text-on-surface">Phạm vi phân công</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
          <div><dt class="text-sm text-on-surface-variant">Nhà bán</dt><dd class="mt-1 font-semibold text-on-surface">{{ selectedAssignment?.vendor?.shop_name }}</dd></div>
          <div><dt class="text-sm text-on-surface-variant">Kho</dt><dd class="mt-1 font-semibold text-on-surface">{{ selectedAssignment?.warehouse?.name }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-sm text-on-surface-variant">Quyền được cấp</dt><dd class="mt-2 flex flex-wrap gap-2"><span v-for="capability in selectedAssignment?.capabilities" :key="capability" class="rounded-full bg-secondary-container px-3 py-1 text-sm text-on-secondary-container">{{ capability }}</span></dd></div>
        </dl>
      </section>
    </template>
  </main>
</template>
