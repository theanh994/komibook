<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const toast = useToast()
const book = ref(null)
const busy = ref(false)
const shares = ref([])
const scheduledFor = ref('')
const acceptedAuthors = computed(() => (book.value?.author_relations || []).filter((item) => item.status === 'accepted'))
const verifiedClaims = computed(() => (book.value?.copyright_claims || []).filter((item) => item.status === 'verified'))

const load = async () => {
  const response = await apiClient.get(`/api/vendor/books/${route.params.bookId}/publishing`)
  book.value = response.data.data
  const latest = [...(book.value.royalty_agreements || [])].sort((a, b) => b.version - a.version)[0]
  shares.value = latest?.shares || acceptedAuthors.value.map((item) => ({ author_id: item.author_id, share_percent: 0 }))
}

const acceptRoyalty = async () => {
  busy.value = true
  try {
    await apiClient.post(`/api/vendor/books/${route.params.bookId}/royalty-agreements`, { shares: shares.value })
    toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Thỏa thuận royalty mới đã được chấp nhận và khóa phiên bản.', life: 3000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Chưa thể lưu', detail: e.response?.data?.message || 'Tổng tỷ lệ phải bằng 100%.', life: 4000 })
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
  <main class="min-h-screen bg-slate-50 p-6 md:p-10">
    <section v-if="book" class="mx-auto max-w-4xl space-y-6">
      <header class="rounded-2xl bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Quy trình tự xuất bản</p>
        <h1 class="mt-2 text-3xl font-black text-slate-900">{{ book.title }}</h1>
        <p class="mt-2">Trạng thái: <strong>{{ book.publishing_status || 'draft (legacy)' }}</strong></p>
        <p v-if="book.publication_feedback" class="mt-3 rounded-lg bg-amber-50 p-3 text-amber-900">Phản hồi: {{ book.publication_feedback }}</p>
      </header>

      <section class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold">Điều kiện quyền</h2>
        <p class="mt-2 text-sm text-slate-600">Tác giả đã chấp nhận: {{ acceptedAuthors.length }} · Hồ sơ bản quyền verified: {{ verifiedClaims.length }}</p>
        <div class="mt-4 space-y-3">
          <div v-for="share in shares" :key="share.author_id" class="flex items-center gap-3">
            <span class="min-w-32">Author #{{ share.author_id }}</span>
            <InputNumber v-model="share.share_percent" suffix="%" :min="0" :max="100" />
          </div>
          <Button label="Chấp nhận phiên bản royalty" icon="pi pi-check" :loading="busy" :disabled="!shares.length" @click="acceptRoyalty" />
        </div>
      </section>

      <section class="rounded-2xl bg-white p-6 shadow-sm">
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
    </section>
  </main>
</template>
