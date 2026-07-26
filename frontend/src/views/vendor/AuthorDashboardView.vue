<script setup>
import { onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'

const loading = ref(true)
const error = ref(null)
const profile = ref({ pen_name: '', onboarding_status: '', total_books: 0 })

const fetchStats = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await apiClient.get('/api/author/dashboard-stats')
    profile.value = response.data.data
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Không thể tải hồ sơ tác giả.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchStats)
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-6 md:p-10">
    <section class="mx-auto max-w-5xl space-y-6">
      <header>
        <p class="text-sm font-semibold text-emerald-700">Kênh tác giả đã xác minh</p>
        <h1 class="mt-1 text-3xl font-black text-slate-900">{{ profile.pen_name || 'Bảng điều khiển tác giả' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Quyền tác giả được quản lý độc lập với quyền vận hành gian hàng.</p>
      </header>

      <ProgressBar v-if="loading" mode="indeterminate" style="height: 6px" />

      <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-800">
        <p>{{ error }}</p>
        <Button label="Thử lại" icon="pi pi-refresh" class="mt-4 p-button-sm" @click="fetchStats" />
      </div>

      <template v-else>
        <div class="grid gap-4 md:grid-cols-2">
          <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Trạng thái hồ sơ</p>
            <p class="mt-2 text-xl font-black text-emerald-700">Đã phê duyệt</p>
          </article>
          <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tác phẩm đã liên kết</p>
            <p class="mt-2 text-3xl font-black text-slate-900">{{ profile.total_books || 0 }}</p>
          </article>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-6 text-indigo-950">
          <h2 class="font-bold">Quyền tác giả và xuất bản</h2>
          <p class="mt-2 text-sm leading-6">Hồ sơ bản quyền, quan hệ đồng tác giả, ủy quyền và thỏa thuận royalty nay được xác nhận tách biệt với quyền vận hành Vendor.</p>
          <Button label="Xem thỏa thuận royalty" icon="pi pi-percentage" class="mt-4" @click="$router.push('/author/royalty-agreements')" />
        </div>
      </template>
    </section>
  </main>
</template>
