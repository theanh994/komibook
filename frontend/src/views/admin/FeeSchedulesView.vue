<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

defineProps({
  embedded: { type: Boolean, default: false },
})

const toast = useToast()
const effective = ref(null)
const history = ref([])
const preview = ref(null)
const loading = ref(true)
const previewing = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const toLocalInput = (date = new Date(Date.now() + 60 * 60 * 1000)) => {
  const offset = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

const form = reactive({
  commission_rate: 10,
  service_fee_rate: 0,
  effective_at: toLocalInput(),
  reason: '',
  base_amount: 100000,
})

const money = (value) => `${Number(value || 0).toLocaleString('vi-VN')} đ`
const effectiveLabel = computed(() => {
  if (!effective.value?.effective_at) return 'Mặc định tương thích của hệ thống'
  return new Date(effective.value.effective_at).toLocaleString('vi-VN')
})

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/admin/fee-schedules')
    effective.value = response.data.data.effective
    history.value = response.data.data.history.data
    form.commission_rate = effective.value?.commission_rate ?? 10
    form.service_fee_rate = effective.value?.service_fee_rate ?? 0
    await calculatePreview(false)
  } catch {
    errorMessage.value = 'Không thể tải lịch phí. Hãy kiểm tra kết nối và thử lại.'
  } finally {
    loading.value = false
  }
}

async function calculatePreview(showError = true) {
  previewing.value = true
  if (showError) errorMessage.value = ''
  try {
    const response = await apiClient.post('/api/admin/fee-schedules/preview', {
      base_amount: form.base_amount,
      commission_rate: form.commission_rate,
      service_fee_rate: form.service_fee_rate,
    })
    const result = response.data.data
    const sellerGross = Number(result.seller_gross ?? result.base_amount ?? 0)
    const commissionAmount = Number(result.commission_amount ?? 0)
    const serviceFeeAmount = Number(result.service_fee_amount ?? 0)

    preview.value = {
      ...result,
      seller_gross: result.seller_gross ?? sellerGross,
      customer_pays: result.customer_pays ?? result.total_amount ?? (sellerGross + serviceFeeAmount),
      seller_net: result.seller_net ?? (sellerGross - commissionAmount),
      platform_net_before_tax: result.platform_net_before_tax ?? (commissionAmount + serviceFeeAmount),
      tax_rate: result.tax_rate ?? 0,
      tax_amount: result.tax_amount ?? 0,
      tax_configured: result.tax_configured ?? false,
    }
  } catch {
    if (showError) errorMessage.value = 'Không thể tính thử dòng tiền. Vui lòng kiểm tra các tỷ lệ và giá trị VND.'
  } finally {
    previewing.value = false
  }
}

async function save() {
  saving.value = true
  errorMessage.value = ''
  try {
    await apiClient.post('/api/admin/fee-schedules', {
      commission_rate: form.commission_rate,
      service_fee_rate: form.service_fee_rate,
      effective_at: new Date(form.effective_at).toISOString(),
      reason: form.reason.trim(),
      operation_key: `fee-ui:${crypto.randomUUID?.() || Date.now()}`,
    })
    form.reason = ''
    toast.add({ severity: 'success', summary: 'Đã tạo phiên bản phí', detail: 'Lịch cũ được giữ nguyên để đối soát.', life: 3500 })
    await load()
  } catch (error) {
    const validation = error?.response?.data?.errors
    errorMessage.value = validation
      ? Object.values(validation).flat().join(' ')
      : 'Không thể lưu phiên bản phí. Hãy kiểm tra thời điểm hiệu lực và thử lại.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="fee-page space-y-lg" aria-labelledby="fee-heading">
    <header v-if="!embedded" class="space-y-xs">
      <h1 id="fee-heading" class="font-headline-lg text-headline-lg font-bold text-primary">Commission và phí dịch vụ</h1>
      <p class="font-body-md text-on-surface-variant">Quản lý theo phiên bản có thời điểm hiệu lực; lịch sử cũ không bị sửa.</p>
    </header>
    <h2 v-else id="fee-heading" class="sr-only">Commission và phí dịch vụ</h2>

    <div v-if="errorMessage" role="alert" class="flex items-start justify-between gap-md rounded-xl border border-error/30 bg-error/5 p-md text-sm text-error">
      <span>{{ errorMessage }}</span>
      <button type="button" class="min-h-11 shrink-0 rounded-lg px-md font-label-md hover:bg-error/10" @click="load">Thử lại</button>
    </div>

    <div v-if="loading" class="grid animate-pulse gap-lg lg:grid-cols-3" aria-label="Đang tải cấu hình phí">
      <div class="h-40 rounded-xl bg-surface-container-high lg:col-span-2"></div>
      <div class="h-40 rounded-xl bg-surface-container-high"></div>
    </div>

    <template v-else>
      <div class="grid gap-lg lg:grid-cols-3">
        <section class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-lg shadow-soft lg:col-span-2">
          <div class="flex flex-col justify-between gap-md sm:flex-row sm:items-start">
            <div>
              <p class="font-label-md text-primary">Đang có hiệu lực</p>
              <p class="mt-xs text-xl font-bold text-on-background">
                Commission {{ effective?.commission_rate ?? 10 }}% · Phí dịch vụ {{ effective?.service_fee_rate ?? 0 }}%
              </p>
              <p class="mt-xs text-sm text-on-surface-variant">Từ {{ effectiveLabel }}</p>
            </div>
            <span class="w-fit rounded-full bg-primary/10 px-md py-xs text-xs font-bold text-primary">
              {{ effective?.source === 'database' ? 'Lịch đã cấu hình' : 'Giá trị mặc định' }}
            </span>
          </div>
        </section>

        <aside class="rounded-xl border border-warning/30 bg-warning/5 p-lg">
          <div class="flex gap-sm">
            <span class="material-symbols-outlined text-warning" aria-hidden="true">gavel</span>
            <div>
              <h3 class="font-label-md font-bold text-on-background">Thuế chưa được cấu hình</h3>
              <p class="mt-xs text-sm leading-6 text-on-surface-variant">7C.3 giữ thuế suất và tiền thuế bằng 0. Chính sách thuế cần ADR pháp lý riêng.</p>
            </div>
          </div>
        </aside>
      </div>

      <div class="grid gap-lg xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
        <form class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-lg shadow-soft" @submit.prevent="save">
          <div class="mb-lg">
            <h3 class="font-headline-md text-primary">Tạo phiên bản phí mới</h3>
            <p class="mt-xs text-sm text-on-surface-variant">Phiên bản chỉ áp dụng từ thời điểm đã chọn và không thay đổi đơn hàng cũ.</p>
          </div>
          <div class="grid gap-md md:grid-cols-2">
            <label class="space-y-xs font-label-md text-on-surface">
              <span>Commission người bán chịu (%)</span>
              <input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.01" required class="fee-input" />
              <small class="block font-normal text-on-surface-variant">Khấu trừ từ doanh thu gộp của người bán.</small>
            </label>
            <label class="space-y-xs font-label-md text-on-surface">
              <span>Phí dịch vụ khách hàng chịu (%)</span>
              <input v-model.number="form.service_fee_rate" type="number" min="0" max="100" step="0.01" required class="fee-input" />
              <small class="block font-normal text-on-surface-variant">Cộng riêng vào tổng tiền khách thanh toán.</small>
            </label>
            <label class="space-y-xs font-label-md text-on-surface">
              <span>Hiệu lực từ</span>
              <input v-model="form.effective_at" type="datetime-local" required class="fee-input" />
            </label>
            <label class="space-y-xs font-label-md text-on-surface">
              <span>Giá trị tính thử (VND)</span>
              <input v-model.number="form.base_amount" type="number" min="0" step="1" required class="fee-input" @change="calculatePreview" />
            </label>
            <label class="space-y-xs font-label-md text-on-surface md:col-span-2">
              <span>Lý do thay đổi</span>
              <textarea v-model="form.reason" required maxlength="2000" rows="3" class="fee-input resize-y" placeholder="Ví dụ: Điều chỉnh chính sách phí quý III"></textarea>
              <small class="block text-right font-normal text-on-surface-variant">{{ form.reason.length }}/2000</small>
            </label>
          </div>
          <div class="mt-lg flex flex-col-reverse gap-sm sm:flex-row sm:justify-end">
            <button type="button" :disabled="previewing" class="fee-button bg-surface-container-high text-primary" @click="calculatePreview">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">calculate</span>
              {{ previewing ? 'Đang tính...' : 'Tính thử dòng tiền' }}
            </button>
            <button type="submit" :disabled="saving" class="fee-button bg-primary text-on-primary">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>
              {{ saving ? 'Đang lưu...' : 'Tạo phiên bản mới' }}
            </button>
          </div>
        </form>

        <section class="rounded-xl border border-primary/20 bg-primary/5 p-lg" aria-live="polite">
          <div class="mb-md flex items-center justify-between gap-sm">
            <div>
              <h3 class="font-headline-md text-primary">Dòng tiền tính thử</h3>
              <p class="mt-xs text-sm text-on-surface-variant">Mỗi khoản được làm tròn half-up tới VND nguyên.</p>
            </div>
            <span v-if="previewing" class="material-symbols-outlined animate-spin text-primary" aria-label="Đang tính">progress_activity</span>
          </div>
          <dl v-if="preview" class="space-y-sm text-sm">
            <div class="fee-row"><dt>Doanh thu gộp người bán</dt><dd>{{ money(preview.seller_gross) }}</dd></div>
            <div class="fee-row"><dt>Phí dịch vụ khách chịu</dt><dd>+ {{ money(preview.service_fee_amount) }}</dd></div>
            <div class="fee-row border-t border-primary/20 pt-sm font-bold text-primary"><dt>Khách hàng thanh toán</dt><dd>{{ money(preview.customer_pays) }}</dd></div>
            <div class="fee-row mt-md"><dt>Commission người bán chịu</dt><dd>− {{ money(preview.commission_amount) }}</dd></div>
            <div class="fee-row font-bold"><dt>Người bán nhận ròng</dt><dd>{{ money(preview.seller_net) }}</dd></div>
            <div class="fee-row"><dt>Nền tảng nhận trước thuế</dt><dd>{{ money(preview.platform_net_before_tax) }}</dd></div>
            <div class="fee-row text-on-surface-variant"><dt>Thuế</dt><dd>0 đ · Chưa cấu hình</dd></div>
          </dl>
        </section>
      </div>

      <section class="overflow-hidden rounded-xl border border-outline-variant/20 bg-surface-container-lowest shadow-soft">
        <div class="border-b border-outline-variant/20 px-lg py-md">
          <h3 class="font-headline-md text-primary">Lịch sử hiệu lực</h3>
          <p class="mt-xs text-sm text-on-surface-variant">Các phiên bản đã tạo chỉ đọc và được giữ để kiểm toán, đối soát.</p>
        </div>
        <div v-if="history.length" class="overflow-x-auto">
          <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="bg-surface-container-low text-on-surface-variant">
              <tr><th class="p-md">Hiệu lực</th><th class="p-md">Commission</th><th class="p-md">Phí dịch vụ</th><th class="p-md">Lý do</th></tr>
            </thead>
            <tbody>
              <tr v-for="item in history" :key="item.id" class="border-t border-outline-variant/15">
                <td class="p-md whitespace-nowrap">{{ new Date(item.effective_at).toLocaleString('vi-VN') }}</td>
                <td class="p-md font-bold">{{ item.commission_rate }}%</td>
                <td class="p-md font-bold">{{ item.service_fee_rate }}%</td>
                <td class="p-md">{{ item.reason }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="p-lg text-sm text-on-surface-variant">Chưa có phiên bản phí nào. Hệ thống đang dùng giá trị mặc định tương thích.</p>
      </section>
    </template>
  </section>
</template>

<style scoped>
.shadow-soft { box-shadow: 0 2px 12px rgba(26, 58, 90, 0.04); }
.fee-input { min-height: 44px; width: 100%; border-radius: 0.5rem; border: 1px solid var(--color-outline-variant); background: var(--color-surface); padding: 0.75rem 1rem; color: var(--color-on-background); }
.fee-input:focus { border-color: var(--color-primary); outline: 3px solid color-mix(in srgb, var(--color-primary) 18%, transparent); }
.fee-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 0.5rem; padding: 0.65rem 1rem; font-weight: 700; transition: opacity 180ms ease, box-shadow 180ms ease; }
.fee-button:hover:not(:disabled) { box-shadow: 0 4px 12px rgba(26, 58, 90, 0.12); }
.fee-button:disabled { cursor: not-allowed; opacity: 0.5; }
.fee-row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
.fee-row dd { white-space: nowrap; font-weight: 700; }
@media (prefers-reduced-motion: reduce) { .fee-button { transition: none; } }
</style>
