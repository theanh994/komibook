<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

defineProps({ embedded: { type: Boolean, default: false } })

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const previewing = ref(false)
const history = ref([])
const effective = ref(null)
const previewRevenue = ref(0)
const preview = ref(null)
const form = ref({
  tax_year: new Date().getFullYear(),
  effective_at: new Date().toISOString().slice(0, 16),
  reason: '',
  brackets: [
    { up_to: null, rate_percent: 0 },
  ],
})

const load = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/admin/vendor-tax-schedules', { params: { tax_year: form.value.tax_year } })
    effective.value = response.data.data.effective
    history.value = response.data.data.history?.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể tải biểu thuế', detail: error.response?.data?.message || 'Vui lòng thử lại.', life: 4000 })
  } finally {
    loading.value = false
  }
}

const addBracket = () => {
  const last = form.value.brackets.at(-1)
  if (last) last.up_to = last.up_to || 100000000
  form.value.brackets.push({ up_to: null, rate_percent: last?.rate_percent || 0 })
}

const removeBracket = (index) => {
  if (form.value.brackets.length <= 1) return
  form.value.brackets.splice(index, 1)
  form.value.brackets.at(-1).up_to = null
}

const taxPayload = () => ({
  ...form.value,
  brackets: form.value.brackets.map((bracket, index) => ({
    up_to: index === form.value.brackets.length - 1 ? null : Number(bracket.up_to),
    rate_percent: Number(bracket.rate_percent),
  })),
})

const runPreview = async () => {
  previewing.value = true
  try {
    const response = await apiClient.post('/api/admin/vendor-tax-schedules/preview', {
      annual_revenue: Number(previewRevenue.value),
      brackets: taxPayload().brackets,
    })
    preview.value = response.data.data
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Biểu thuế chưa hợp lệ', detail: error.response?.data?.message || 'Kiểm tra lại giới hạn và thuế suất.', life: 4500 })
  } finally {
    previewing.value = false
  }
}

const save = async () => {
  saving.value = true
  try {
    await apiClient.post('/api/admin/vendor-tax-schedules', {
      ...taxPayload(),
      operation_key: `vendor-tax:${form.value.tax_year}:${Date.now()}`,
    })
    toast.add({ severity: 'success', summary: 'Đã lưu biểu thuế', detail: 'Biểu mới chỉ áp dụng từ thời điểm hiệu lực, không hồi tố.', life: 4500 })
    form.value.reason = ''
    await load()
  } catch (error) {
    const detail = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(' ')
      : error.response?.data?.message || 'Không thể lưu biểu thuế.'
    toast.add({ severity: 'error', summary: 'Không thể lưu', detail, life: 5000 })
  } finally {
    saving.value = false
  }
}

const money = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)

onMounted(load)
</script>

<template>
  <div class="space-y-lg">
    <section class="rounded-xl border border-amber-300 bg-amber-50 p-lg text-amber-950">
      <h2 class="text-lg font-bold">Thuế doanh thu Nhà bán</h2>
      <p class="mt-xs text-sm leading-6">Tính lũy tiến theo năm trên tổng tiền khách thanh toán trước commission. Khoản thuế được khấu trừ thật khỏi số dư có thể payout. Hệ thống không tự gán thuế suất pháp lý.</p>
    </section>

    <div class="grid grid-cols-1 gap-lg xl:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
      <section class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-lg shadow-soft">
        <div class="grid grid-cols-1 gap-md md:grid-cols-2">
          <label class="space-y-xs text-sm font-bold text-on-surface-variant">Năm tính thuế
            <input v-model.number="form.tax_year" type="number" min="2020" max="2200" class="min-h-11 w-full rounded-lg border border-outline-variant bg-surface px-md" @change="load" />
          </label>
          <label class="space-y-xs text-sm font-bold text-on-surface-variant">Hiệu lực từ
            <input v-model="form.effective_at" type="datetime-local" class="min-h-11 w-full rounded-lg border border-outline-variant bg-surface px-md" />
          </label>
        </div>

        <div class="mt-lg space-y-sm">
          <div class="grid grid-cols-[1fr_1fr_3rem] gap-sm text-xs font-bold uppercase text-on-surface-variant">
            <span>Doanh thu đến</span><span>Thuế suất (%)</span><span></span>
          </div>
          <div v-for="(bracket, index) in form.brackets" :key="index" class="grid grid-cols-[1fr_1fr_3rem] gap-sm">
            <input v-if="index < form.brackets.length - 1" v-model.number="bracket.up_to" type="number" min="1" class="min-h-11 rounded-lg border border-outline-variant bg-surface px-md" />
            <div v-else class="flex min-h-11 items-center rounded-lg bg-surface-container-high px-md text-sm">Phần còn lại</div>
            <input v-model.number="bracket.rate_percent" type="number" min="0" max="100" step="0.01" class="min-h-11 rounded-lg border border-outline-variant bg-surface px-md" />
            <button type="button" class="min-h-11 min-w-11 rounded-lg text-error hover:bg-error-container disabled:opacity-30" :disabled="form.brackets.length === 1" aria-label="Xóa bậc thuế" @click="removeBracket(index)">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </div>
          <button type="button" class="min-h-11 rounded-lg border border-primary px-md font-bold text-primary hover:bg-primary/5" @click="addBracket">+ Thêm bậc</button>
        </div>

        <label class="mt-lg block space-y-xs text-sm font-bold text-on-surface-variant">Lý do thay đổi
          <textarea v-model="form.reason" rows="3" class="w-full rounded-lg border border-outline-variant bg-surface p-md font-normal" placeholder="Bắt buộc ghi rõ căn cứ và phạm vi áp dụng"></textarea>
        </label>
        <button type="button" class="mt-lg min-h-11 rounded-lg bg-primary px-lg font-bold text-on-primary disabled:opacity-50" :disabled="saving || !form.reason.trim()" @click="save">{{ saving ? 'Đang lưu...' : 'Lưu biểu thuế mới' }}</button>
      </section>

      <aside class="space-y-lg">
        <section class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-lg shadow-soft">
          <h3 class="font-bold text-on-surface">Xem trước khấu trừ</h3>
          <label class="mt-md block space-y-xs text-sm text-on-surface-variant">Doanh thu lũy kế năm
            <input v-model.number="previewRevenue" type="number" min="0" class="min-h-11 w-full rounded-lg border border-outline-variant bg-surface px-md" />
          </label>
          <button type="button" class="mt-md min-h-11 w-full rounded-lg border border-primary font-bold text-primary" :disabled="previewing" @click="runPreview">Tính thử</button>
          <div v-if="preview" class="mt-md rounded-lg bg-primary/5 p-md">
            <p class="text-sm text-on-surface-variant">Thuế lũy kế dự kiến</p>
            <p class="mt-xs text-2xl font-black text-primary">{{ money(preview.tax_amount) }}</p>
          </div>
        </section>
        <section class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-lg shadow-soft">
          <h3 class="font-bold text-on-surface">Biểu đang hiệu lực</h3>
          <p v-if="loading" class="mt-md text-sm text-on-surface-variant">Đang tải...</p>
          <p v-else-if="!effective" class="mt-md text-sm leading-6 text-on-surface-variant">Chưa có biểu thuế cho năm này. Thuế khấu trừ bằng 0 cho tới khi có biểu hợp lệ.</p>
          <div v-else class="mt-md text-sm leading-6 text-on-surface-variant">
            <p><strong>{{ effective.brackets.length }}</strong> bậc · hiệu lực {{ new Date(effective.effective_at).toLocaleString('vi-VN') }}</p>
            <p class="mt-xs">{{ effective.reason }}</p>
          </div>
          <p class="mt-md text-xs text-on-surface-variant">Lịch sử: {{ history.length }} biểu trong trang hiện tại.</p>
        </section>
      </aside>
    </div>
  </div>
</template>
