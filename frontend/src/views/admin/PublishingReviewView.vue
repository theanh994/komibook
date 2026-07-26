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

const load = async () => {
  const response = await apiClient.get('/api/admin/books', { params: { publishing_status: 'submitted_for_review', per_page: 50 } })
  const first = response.data.data || []
  const resubmitted = await apiClient.get('/api/admin/books', { params: { publishing_status: 'resubmitted', per_page: 50 } })
  books.value = [...first, ...(resubmitted.data.data || [])]
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
  <main class="min-h-screen bg-slate-50 p-6 md:p-8">
    <section class="mx-auto max-w-5xl rounded-2xl bg-white p-6 shadow-sm">
      <h1 class="text-3xl font-black text-slate-900">Kiểm duyệt xuất bản</h1>
      <p class="mt-2 text-sm text-slate-600">Chỉ duyệt workflow; trạng thái public không thể được chỉnh trực tiếp.</p>
      <div class="mt-6 divide-y divide-slate-100">
        <article v-for="book in books" :key="book.id" class="grid gap-3 py-5 md:grid-cols-[1fr_2fr_auto] md:items-center">
          <div><strong>{{ book.title }}</strong><p class="text-xs text-slate-500">{{ book.publishing_status }}</p></div>
          <InputText v-model="reasons[book.id]" placeholder="Phản hồi bắt buộc khi yêu cầu sửa" />
          <div class="flex gap-2"><Button label="Yêu cầu sửa" severity="warn" :loading="busy === book.id" @click="decide(book, 'changes_requested')" /><Button label="Duyệt" :loading="busy === book.id" @click="decide(book, 'approved')" /></div>
        </article>
        <p v-if="!books.length" class="py-8 text-center text-slate-500">Không có sách đang chờ duyệt.</p>
      </div>
    </section>
  </main>
</template>
