<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const toast = useToast()
const saving = ref(false)
const evidence = ref(null)
const claim = ref(null)
const form = ref({ registration_type: 'original_work', registration_number: '', rights_scope: 'digital,distribute', territory_scope: 'VN', valid_from: '', valid_until: '' })
const selectEvidence = (event) => { evidence.value = event.files?.[0] || null }

const createAndSubmit = async () => {
  saving.value = true
  try {
    const payload = new FormData()
    payload.append('registration_type', form.value.registration_type)
    if (form.value.registration_number) payload.append('registration_number', form.value.registration_number)
    form.value.rights_scope.split(',').map((item) => item.trim()).filter(Boolean).forEach((item) => payload.append('rights_scope[]', item))
    form.value.territory_scope.split(',').map((item) => item.trim()).filter(Boolean).forEach((item) => payload.append('territory_scope[]', item))
    if (form.value.valid_from) payload.append('valid_from', form.value.valid_from)
    if (form.value.valid_until) payload.append('valid_until', form.value.valid_until)
    payload.append('evidence_document', evidence.value)
    const created = await apiClient.post(`/api/author/books/${route.params.bookId}/copyright`, payload)
    claim.value = created.data.data
    const submitted = await apiClient.post(`/api/author/copyright/${claim.value.id}/submit`)
    claim.value = submitted.data.data
    toast.add({ severity: 'success', summary: 'Đã gửi', detail: 'Hồ sơ bản quyền đã được gửi kiểm duyệt.', life: 3000 })
  } catch (exception) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: exception.response?.data?.message || 'Không thể gửi hồ sơ.', life: 3500 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-6 md:p-10"><section class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
    <h1 class="text-3xl font-black text-slate-900">Hồ sơ bản quyền</h1><p class="mt-2 text-sm text-slate-600">Chỉ quan hệ tác giả hoặc ủy quyền đã chấp nhận mới có thể gửi hồ sơ.</p>
    <div v-if="claim" class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">Trạng thái: <strong>{{ claim.status }}</strong></div>
    <form v-else class="mt-8 space-y-4" @submit.prevent="createAndSubmit">
      <InputText v-model="form.registration_number" placeholder="Số đăng ký (nếu có)" class="w-full" />
      <InputText v-model="form.rights_scope" placeholder="Quyền, cách nhau bằng dấu phẩy" class="w-full" />
      <InputText v-model="form.territory_scope" placeholder="Lãnh thổ, ví dụ VN" class="w-full" />
      <div class="grid gap-4 md:grid-cols-2"><InputText v-model="form.valid_from" type="date" /><InputText v-model="form.valid_until" type="date" /></div>
      <FileUpload mode="basic" accept=".pdf,image/*" :maxFileSize="10485760" chooseLabel="Chọn bằng chứng riêng tư" @select="selectEvidence" />
      <Button type="submit" label="Tạo và gửi kiểm duyệt" icon="pi pi-send" :loading="saving" :disabled="!evidence" />
    </form>
  </section></main>
</template>
