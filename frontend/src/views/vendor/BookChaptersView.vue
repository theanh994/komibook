<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

import Button from 'primevue/button'
import Toast from 'primevue/toast'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = route.params.bookId
const bookTitle = ref('Tác phẩm tự viết')
const chapters = ref([])
const loading = ref(true)
const saving = ref(false)

const fetchChapters = async () => {
  loading.value = true
  try {
    const bookRes = await apiClient.get(`/api/books`)
    const b = bookRes.data?.data?.find(item => item.id == bookId)
    if (b) {
      bookTitle.value = b.title
    }

    const res = await apiClient.get(`/api/vendor/books/${bookId}/chapters`)
    if (res.data?.status === 'success') {
      chapters.value = res.data.data
    }
  } catch (e) {
    console.error('Không tải được danh sách chương', e)
    // Fallback Mock Chapters
    chapters.value = [
      { id: 101, title: 'Chương 1: Khởi đầu mới', chapter_number: 1, is_preview: true, price: 0 },
      { id: 102, title: 'Chương 2: Sóng gió nổi lên', chapter_number: 2, is_preview: false, price: 5000 },
      { id: 103, title: 'Chương 3: Đi tìm sự thật', chapter_number: 3, is_preview: false, price: 5000 },
    ]
  } finally {
    loading.value = false
  }
}

const saveConfigs = async () => {
  saving.value = true
  try {
    // Loop and update each chapter configurations
    for (const ch of chapters.value) {
      await apiClient.put(`/api/vendor/books/${bookId}/chapters/${ch.id}`, {
        title: ch.title,
        chapter_number: ch.chapter_number,
        is_preview: ch.is_preview,
        price: ch.is_preview ? 0 : ch.price,
      })
    }
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật giá bán và cấu hình đọc thử cho toàn bộ chương.', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi cập nhật', detail: 'Có lỗi xảy ra khi lưu thiết lập.', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchChapters()
})
</script>

<template>
  <div class="book-chapters min-h-screen bg-slate-50 p-6 md:p-8">
    <Toast />
    
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" class="p-button-text p-button-secondary p-button-sm" @click="router.push({ name: 'author-dashboard' })" />
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Thiết lập Giá & Đọc thử</h1>
          <p class="text-slate-500 text-sm mt-1">Cấu hình giá trị bán lẻ và quyền đọc thử cho từng chương sách của tác phẩm: <strong class="text-indigo-600 font-bold">{{ bookTitle }}</strong></p>
        </div>
      </div>
      <div>
        <Button label="Lưu cấu hình" icon="pi pi-save" class="p-button-primary bg-indigo-600 hover:bg-indigo-700 text-white font-bold" :loading="saving" @click="saveConfigs" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <i class="pi pi-spin pi-spinner text-3xl text-indigo-600"></i>
    </div>

    <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
              <th class="p-4 w-20">Stt</th>
              <th class="p-4">Tiêu đề chương</th>
              <th class="p-4 w-40 text-center">Cho đọc thử (Free)</th>
              <th class="p-4 w-52">Giá chương lẻ (đ)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-150">
            <tr v-for="(ch, idx) in chapters" :key="ch.id" class="hover:bg-slate-50/50">
              <td class="p-4 font-semibold text-slate-500">{{ ch.chapter_number }}</td>
              <td class="p-4">
                <input type="text" v-model="ch.title" class="w-full px-2 py-1 bg-transparent border-b border-transparent focus:border-indigo-600 text-slate-800 font-medium text-sm outline-none" />
              </td>
              <td class="p-4 text-center">
                <div class="flex justify-center">
                  <ToggleSwitch v-model="ch.is_preview" />
                </div>
              </td>
              <td class="p-4">
                <div class="flex items-center gap-2">
                  <input 
                    type="number" 
                    v-model="ch.price" 
                    :disabled="ch.is_preview"
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-800 disabled:bg-slate-100 disabled:text-slate-400"
                    placeholder="0"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.book-chapters {
  font-family: 'Inter', sans-serif;
}
</style>
