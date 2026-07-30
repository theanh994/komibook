<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const status = ref(null)
const reason = ref(null)
const termsAccepted = ref(false)
const hasBusinessDocument = ref(false)
const hasRepresentativeDocument = ref(false)
const businessDocument = ref(null)
const representativeDocument = ref(null)
const form = ref({
  shop_name: '', slug: '', description: '', legal_name: '', tax_code: '',
  business_model: 'bookstore',
  payout_bank_account: '', payout_bank_name: '', payout_bank_holder: '',
})
const businessModels = [
  { label: 'Nhà xuất bản bán trực tiếp', value: 'direct_publisher' },
  { label: 'Nhà sách / Hiệu sách', value: 'bookstore' },
  { label: 'Nhà phân phối', value: 'distributor' },
  { label: 'Mô hình kết hợp', value: 'mixed' },
]

const loadStatus = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/api/vendor-onboarding/status')
    const profile = response.data.data
    if (profile) {
      status.value = profile.onboarding_status
      reason.value = profile.last_review_reason
      termsAccepted.value = Boolean(profile.terms_accepted_at)
      hasBusinessDocument.value = Boolean(profile.has_business_registration_document)
      hasRepresentativeDocument.value = Boolean(profile.has_representative_identity_document)
      Object.keys(form.value).forEach((key) => { form.value[key] = profile[key] || '' })
    }
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: exception.response?.data?.message || 'Không thể tải hồ sơ nhà bán.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const selectBusiness = (event) => { businessDocument.value = event.files?.[0] || null }
const selectRepresentative = (event) => { representativeDocument.value = event.files?.[0] || null }

const submit = async () => {
  if (!termsAccepted.value) {
    toast.add({ severity: 'warn', summary: 'Thiếu xác nhận', detail: 'Vui lòng chấp nhận điều khoản nhà bán.', life: 3000 })
    return
  }
  if ((!businessDocument.value && !hasBusinessDocument.value) || (!representativeDocument.value && !hasRepresentativeDocument.value)) {
    toast.add({ severity: 'warn', summary: 'Thiếu tài liệu', detail: 'Vui lòng cung cấp đủ hồ sơ pháp lý và giấy tờ người đại diện.', life: 3000 })
    return
  }
  saving.value = true
  try {
    const payload = new FormData()
    Object.entries(form.value).forEach(([key, value]) => payload.append(key, value))
    payload.append('terms_accepted', '1')
    if (businessDocument.value) payload.append('business_registration_document', businessDocument.value)
    if (representativeDocument.value) payload.append('representative_identity_document', representativeDocument.value)
    const response = await apiClient.post('/api/vendor-onboarding/register', payload)
    status.value = response.data.data.onboarding_status
    toast.add({ severity: 'success', summary: 'Đã gửi', detail: 'Hồ sơ nhà bán đã được gửi để kiểm duyệt.', life: 3000 })
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: exception.response?.data?.message || 'Không thể gửi hồ sơ.', life: 3500 })
  } finally {
    saving.value = false
  }
}

onMounted(loadStatus)
</script>

<template>
  <main class="min-h-screen bg-background p-4 md:p-10">
    <section class="mx-auto max-w-4xl rounded-2xl border border-outline-variant/30 bg-surface-container-lowest p-6 shadow-sm md:p-8" aria-labelledby="vendor-register-title">
      <h1 id="vendor-register-title" class="text-3xl font-bold text-primary">Hồ sơ Nhà bán</h1>
      <p class="mt-2 text-sm text-on-surface-variant">Hồ sơ pháp lý và thanh toán được kiểm duyệt riêng cho từng Nhà bán.</p>
      <p v-if="loading" class="mt-8 text-on-surface-variant" role="status">Đang tải hồ sơ…</p>

      <div v-else-if="['submitted', 'resubmitted', 'under_review'].includes(status)" class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        Hồ sơ đang được kiểm duyệt. Bạn chưa thể vận hành gian hàng cho đến khi được phê duyệt.
      </div>
      <div v-else-if="['approved', 'suspended', 'revoked', 'rejected'].includes(status)" class="mt-8 rounded-xl border border-slate-200 p-6">
        <strong>Trạng thái: {{ status }}</strong><p v-if="reason" class="mt-2 text-sm">{{ reason }}</p>
      </div>

      <form v-else class="mt-8 space-y-6" @submit.prevent="submit">
        <div v-if="status === 'changes_requested'" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Cần bổ sung:</strong> {{ reason }}</div>
        <div class="rounded-xl bg-surface-container p-4" aria-label="Tiến độ hồ sơ"><p class="text-sm font-semibold text-primary">Bước 1/3 · Gian hàng và mô hình hoạt động</p><p class="mt-1 text-sm text-on-surface-variant">Sau khi hồ sơ Nhà bán được duyệt, bạn tiếp tục xác minh Nhà xuất bản và Nhà cung cấp cho sản phẩm.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
          <div><label for="vendor-shop-name" class="mb-2 block text-sm font-semibold">Tên gian hàng</label><InputText id="vendor-shop-name" v-model="form.shop_name" class="w-full" required /></div>
          <div><label for="vendor-slug" class="mb-2 block text-sm font-semibold">Đường dẫn gian hàng</label><InputText id="vendor-slug" v-model="form.slug" class="w-full" required /></div>
          <div class="md:col-span-2"><label for="vendor-business-model" class="mb-2 block text-sm font-semibold">Mô hình kinh doanh</label><Select id="vendor-business-model" v-model="form.business_model" :options="businessModels" optionLabel="label" optionValue="value" class="min-h-11 w-full" required /><p class="mt-2 text-sm text-on-surface-variant">Nhà sách bán nhiều nguồn phải bổ sung quan hệ với Nhà xuất bản và Nhà cung cấp trước khi xuất bản sản phẩm.</p></div>
          <div><label for="vendor-legal-name" class="mb-2 block text-sm font-semibold">Tên pháp lý</label><InputText id="vendor-legal-name" v-model="form.legal_name" class="w-full" required /></div>
          <div><label for="vendor-tax-code" class="mb-2 block text-sm font-semibold">Mã số thuế</label><InputText id="vendor-tax-code" v-model="form.tax_code" class="w-full" required /></div>
        </div>
        <div><label for="vendor-description" class="mb-2 block text-sm font-semibold">Mô tả gian hàng</label><Textarea id="vendor-description" v-model="form.description" rows="3" class="w-full" /></div>
        <p class="text-sm font-semibold text-on-surface">Tài liệu xác minh</p>
        <div class="grid gap-4 md:grid-cols-2">
          <FileUpload mode="basic" accept=".pdf,image/*" :maxFileSize="5242880" chooseLabel="Đăng ký kinh doanh" @select="selectBusiness" />
          <FileUpload mode="basic" accept=".pdf,image/*" :maxFileSize="5242880" chooseLabel="Giấy tờ người đại diện" @select="selectRepresentative" />
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <div><label for="vendor-bank-name" class="mb-2 block text-sm font-semibold">Ngân hàng</label><InputText id="vendor-bank-name" v-model="form.payout_bank_name" class="w-full" required /></div>
          <div><label for="vendor-bank-account" class="mb-2 block text-sm font-semibold">Số tài khoản</label><InputText id="vendor-bank-account" v-model="form.payout_bank_account" class="w-full" required /></div>
          <div><label for="vendor-bank-holder" class="mb-2 block text-sm font-semibold">Chủ tài khoản</label><InputText id="vendor-bank-holder" v-model="form.payout_bank_holder" class="w-full" required /></div>
        </div>
        <label class="flex min-h-11 items-center gap-3 text-sm text-on-surface-variant"><input v-model="termsAccepted" type="checkbox" class="h-5 w-5" /> Tôi xác nhận thông tin đúng và chấp nhận điều khoản Nhà bán.</label>
        <Button type="submit" label="Gửi hồ sơ kiểm duyệt" icon="pi pi-send" :loading="saving" class="min-h-11" />
      </form>
    </section>
  </main>
</template>
