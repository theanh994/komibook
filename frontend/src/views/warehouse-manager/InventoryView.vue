<script setup>
import { onMounted, ref, watch } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import Select from 'primevue/select'

const assignments = ref([])
const selectedId = ref(null)
const stocks = ref([])
const loading = ref(true)
const error = ref('')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const list = await apiClient.get('/api/warehouse-manager/assignments')
    assignments.value = list.data.data || []
    selectedId.value ||= assignments.value[0]?.id || null
    if (selectedId.value) {
      const detail = await apiClient.get(`/api/warehouse-manager/assignments/${selectedId.value}/dashboard`)
      stocks.value = detail.data.data.assignment.warehouse.stocks || []
    } else {
      stocks.value = []
    }
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải dữ liệu tồn kho.'
  } finally {
    loading.value = false
  }
}

watch(selectedId, load)
onMounted(load)
</script>

<template>
  <main id="main-content" class="space-y-6" tabindex="-1">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div><p class="text-sm font-semibold uppercase tracking-wider text-primary">Vận hành kho</p><h1 class="mt-1 text-3xl font-bold text-on-surface">Tồn kho</h1></div>
      <Select v-if="assignments.length" v-model="selectedId" :options="assignments" optionLabel="warehouse.name" optionValue="id" aria-label="Chọn kho" class="min-h-11 w-full lg:w-80" />
    </header>
    <div v-if="loading" class="h-56 animate-pulse rounded-xl bg-surface-container" aria-label="Đang tải tồn kho"></div>
    <section v-else-if="error" class="rounded-xl bg-error-container p-6 text-on-error-container" role="alert"><p>{{ error }}</p><Button label="Thử lại" class="mt-4 min-h-11" @click="load" /></section>
    <section v-else-if="!stocks.length" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-8 text-center"><h2 class="text-xl font-bold">Kho chưa có sản phẩm</h2><p class="mt-2 text-on-surface-variant">Phiếu nhập kho được duyệt sẽ tạo số lượng tồn tại đây.</p></section>
    <section v-else class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
      <div class="hidden grid-cols-[minmax(240px,1fr)_140px] gap-4 border-b border-outline-variant bg-surface-container px-5 py-3 text-sm font-semibold md:grid"><span>Sách</span><span>Số lượng</span></div>
      <article v-for="stock in stocks" :key="stock.id" class="grid gap-3 border-b border-outline-variant/50 p-5 last:border-0 md:grid-cols-[minmax(240px,1fr)_140px] md:items-center">
        <div><span class="text-xs text-on-surface-variant md:hidden">Sách</span><p class="font-semibold text-on-surface">{{ stock.book?.title }}</p></div>
        <div><span class="text-xs text-on-surface-variant md:hidden">Số lượng</span><p class="font-semibold tabular-nums">{{ stock.quantity }}</p></div>
      </article>
    </section>
  </main>
</template>
