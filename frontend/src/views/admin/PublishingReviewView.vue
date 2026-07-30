<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const books = ref([])
const reasons = ref({})
const busy = ref(null)
const loading = ref(true)
const error = ref('')

const load = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/admin/books', { params: { publishing_status: 'submitted_for_review', per_page: 50 } })
    const first = response.data.data || []
    const resubmitted = await apiClient.get('/api/admin/books', { params: { publishing_status: 'resubmitted', per_page: 50 } })
    books.value = [...first, ...(resubmitted.data.data || [])]
  } catch (e) {
    books.value = []
    error.value = e.response?.data?.message || 'Không thể tải hàng đợi xuất bản.'
  } finally {
    loading.value = false
  }
}

const decide = async (book, toStatus) => {
  busy.value = book.id
  try {
    await apiClient.patch(`/api/admin/books/${book.id}/publishing-transition`, {
      to_status: toStatus,
      reason: reasons.value[book.id] || null,
    })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: `Sách đã chuyển sang ${toStatus}.`, life: 2500 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: e.response?.data?.message || 'Vui lòng kiểm tra phản hồi.', life: 3500 })
  } finally { busy.value = null }
}

onMounted(load)
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-4 sm:p-6 md:p-8">
    <section class="mx-auto max-w-5xl rounded-2xl bg-white p-4 sm:p-6 shadow-sm">
      <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Kiểm duyệt xuất bản</h1>
      <p class="mt-2 text-sm text-slate-600">Chỉ duyệt workflow; trạng thái public không thể được chỉnh trực tiếp.</p>
      <div v-if="loading" role="status" aria-live="polite" class="mt-6 py-10 text-center text-slate-500">Đang tải hàng đợi xuất bản...</div>
      <div v-else-if="error" role="alert" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-5 text-center">
        <p class="font-bold text-rose-800">Không thể tải hàng đợi xuất bản</p>
        <p class="mt-2 text-sm text-rose-700">{{ error }}</p>
        <button type="button" class="mt-4 min-h-11 rounded-xl bg-rose-600 px-5 font-bold text-white" @click="load">Thử lại</button>
      </div>
      <div v-else class="mt-6 divide-y divide-slate-100">
        <article v-for="book in books" :key="book.id" class="grid gap-3 py-5 md:grid-cols-[1fr_2fr_auto] md:items-center">
          <div><strong>{{ book.title }}</strong><p class="text-xs text-slate-500">{{ book.publishing_status }}</p></div>
          <label :for="`publishing-feedback-${book.id}`" class="sr-only">Phản hồi kiểm duyệt cho {{ book.title }}</label>
          <InputText :id="`publishing-feedback-${book.id}`" v-model="reasons[book.id]" placeholder="Phản hồi bắt buộc khi yêu cầu sửa" />
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <Button type="button" label="Yêu cầu sửa" severity="warn" class="min-h-11" :loading="busy === book.id" @click="decide(book, 'changes_requested')" />
            <Button type="button" label="Duyệt" class="min-h-11" :loading="busy === book.id" @click="decide(book, 'approved')" />
          </div>
        </article>
        <p v-if="!books.length" class="py-8 text-center text-slate-500">Không có sách đang chờ duyệt.</p>
      </div>
    </section>
  </main>
</template>
