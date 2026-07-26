<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
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
  payout_bank_account: '', payout_bank_name: '', payout_bank_holder: '',
})

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
  <main class="min-h-screen bg-slate-50 p-6 md:p-10">
    <section class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
      <h1 class="text-3xl font-black text-slate-900">Hồ sơ nhà bán</h1>
      <p class="mt-2 text-sm text-slate-600">Hồ sơ pháp lý và thanh toán độc lập với hồ sơ tác giả.</p>
      <p v-if="loading" class="mt-8 text-slate-500">Đang tải...</p>

      <div v-else-if="['submitted', 'resubmitted', 'under_review'].includes(status)" class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        Hồ sơ đang được kiểm duyệt. Bạn chưa thể vận hành gian hàng cho đến khi được phê duyệt.
      </div>
      <div v-else-if="['approved', 'suspended', 'revoked', 'rejected'].includes(status)" class="mt-8 rounded-xl border border-slate-200 p-6">
        <strong>Trạng thái: {{ status }}</strong><p v-if="reason" class="mt-2 text-sm">{{ reason }}</p>
      </div>

      <form v-else class="mt-8 space-y-6" @submit.prevent="submit">
        <div v-if="status === 'changes_requested'" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Cần bổ sung:</strong> {{ reason }}</div>
        <div class="grid gap-4 md:grid-cols-2">
          <InputText v-model="form.shop_name" placeholder="Tên gian hàng" required />
          <InputText v-model="form.slug" placeholder="Đường dẫn gian hàng" required />
          <InputText v-model="form.legal_name" placeholder="Tên pháp lý" required />
          <InputText v-model="form.tax_code" placeholder="Mã số thuế" required />
        </div>
        <Textarea v-model="form.description" rows="3" placeholder="Mô tả gian hàng" class="w-full" />
        <div class="grid gap-4 md:grid-cols-2">
          <FileUpload mode="basic" accept=".pdf,image/*" :maxFileSize="5242880" chooseLabel="Đăng ký kinh doanh" @select="selectBusiness" />
          <FileUpload mode="basic" accept=".pdf,image/*" :maxFileSize="5242880" chooseLabel="Giấy tờ người đại diện" @select="selectRepresentative" />
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <InputText v-model="form.payout_bank_name" placeholder="Ngân hàng" required />
          <InputText v-model="form.payout_bank_account" placeholder="Số tài khoản" required />
          <InputText v-model="form.payout_bank_holder" placeholder="Chủ tài khoản" required />
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-600"><input v-model="termsAccepted" type="checkbox" class="mt-1" /> Tôi xác nhận thông tin đúng và chấp nhận điều khoản nhà bán.</label>
        <Button type="submit" label="Gửi hồ sơ kiểm duyệt" icon="pi pi-send" :loading="saving" />
      </form>
    </section>
  </main>
</template>
