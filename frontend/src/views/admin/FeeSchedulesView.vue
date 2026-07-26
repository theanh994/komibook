<script setup>
import { onMounted, reactive, ref } from 'vue'
import apiClient from '@/services/axios'

const effective = ref(null)
const history = ref([])
const preview = ref(null)
const form = reactive({ commission_rate: 10, service_fee_rate: 0, effective_at: '', reason: '', base_amount: 100000 })

async function load() {
  const response = await apiClient.get('/api/admin/fee-schedules')
  effective.value = response.data.data.effective
  history.value = response.data.data.history.data
}

async function calculatePreview() {
  const response = await apiClient.post('/api/admin/fee-schedules/preview', {
    base_amount: form.base_amount,
    commission_rate: form.commission_rate,
    service_fee_rate: form.service_fee_rate,
  })
  preview.value = response.data.data
}

async function save() {
  await apiClient.post('/api/admin/fee-schedules', {
    commission_rate: form.commission_rate,
    service_fee_rate: form.service_fee_rate,
    effective_at: new Date(form.effective_at).toISOString(),
    reason: form.reason,
    operation_key: `fee-ui:${Date.now()}`,
  })
  form.reason = ''
  await load()
}

onMounted(load)
</script>

<template>
  <section class="space-y-6">
    <div><h1 class="text-2xl font-bold text-slate-900">Commission &amp; phí dịch vụ</h1><p class="mt-1 text-sm text-slate-500">Mỗi thay đổi tạo một phiên bản mới; lịch sử cũ không bị sửa.</p></div>
    <div v-if="effective" class="rounded-2xl bg-white p-6 shadow-sm">
      <p class="text-sm text-slate-500">Đang có hiệu lực</p><p class="mt-2 text-xl font-bold">Commission {{ effective.commission_rate }}% · Phí dịch vụ {{ effective.service_fee_rate }}%</p><p class="mt-1 text-xs text-slate-500">Nguồn: {{ effective.source }}</p>
    </div>
    <form class="grid gap-4 rounded-2xl bg-white p-6 shadow-sm md:grid-cols-2" @submit.prevent="save">
      <label class="text-sm">Commission (%)<input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.01" required class="mt-1 w-full rounded-lg border p-3" /></label>
      <label class="text-sm">Phí dịch vụ (%)<input v-model.number="form.service_fee_rate" type="number" min="0" max="100" step="0.01" required class="mt-1 w-full rounded-lg border p-3" /></label>
      <label class="text-sm">Hiệu lực từ<input v-model="form.effective_at" type="datetime-local" required class="mt-1 w-full rounded-lg border p-3" /></label>
      <label class="text-sm">Lý do<input v-model="form.reason" required maxlength="2000" class="mt-1 w-full rounded-lg border p-3" /></label>
      <label class="text-sm">Giá trị thử (VND)<input v-model.number="form.base_amount" type="number" min="0" class="mt-1 w-full rounded-lg border p-3" /></label>
      <div class="flex items-end gap-3"><button type="button" class="rounded-lg bg-slate-100 px-5 py-3" @click="calculatePreview">Xem trước</button><button class="rounded-lg bg-indigo-600 px-5 py-3 text-white">Lưu phiên bản mới</button></div>
      <p v-if="preview" class="text-sm md:col-span-2">Preview: commission {{ preview.commission_amount.toLocaleString('vi-VN') }}đ · phí {{ preview.service_fee_amount.toLocaleString('vi-VN') }}đ · khách trả {{ preview.total_amount.toLocaleString('vi-VN') }}đ</p>
    </form>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Hiệu lực</th><th class="p-4">Commission</th><th class="p-4">Phí dịch vụ</th><th class="p-4">Lý do</th></tr></thead><tbody><tr v-for="item in history" :key="item.id" class="border-t"><td class="p-4">{{ new Date(item.effective_at).toLocaleString('vi-VN') }}</td><td class="p-4">{{ item.commission_rate }}%</td><td class="p-4">{{ item.service_fee_rate }}%</td><td class="p-4">{{ item.reason }}</td></tr></tbody></table></div>
  </section>
</template>
