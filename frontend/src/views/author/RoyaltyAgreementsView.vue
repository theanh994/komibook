<script setup>
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/services/axios'
import Button from 'primevue/button'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'

const auth = useAuthStore()
const toast = useToast()
const agreements = ref([])
const busy = ref(null)
const authorId = computed(() => auth.user?.author_profile?.id)
const accepted = (agreement) => agreement.acceptances?.some((item) => item.author_id === authorId.value)
const load = async () => { agreements.value = (await apiClient.get('/api/author/royalty-agreements')).data.data || [] }
const accept = async (agreement) => {
  busy.value = agreement.id
  try {
    await apiClient.post(`/api/author/royalty-agreements/${agreement.id}/accept`)
    toast.add({ severity: 'success', summary: 'Đã chấp nhận', detail: 'Xác nhận royalty đã được ghi append-only.', life: 3000 })
    await load()
  } finally { busy.value = null }
}
onMounted(load)
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-6 md:p-10"><section class="mx-auto max-w-4xl rounded-2xl bg-white p-6 shadow-sm">
    <h1 class="text-3xl font-black">Thỏa thuận royalty</h1>
    <p class="mt-2 text-sm text-slate-600">Mỗi phiên bản và mỗi xác nhận được lưu bất biến; thay đổi tỷ lệ phải tạo phiên bản mới.</p>
    <article v-for="agreement in agreements" :key="agreement.id" class="mt-5 rounded-xl border border-slate-200 p-5">
      <div class="flex flex-wrap items-center justify-between gap-3"><div><strong>{{ agreement.book?.title }}</strong><p class="text-sm text-slate-500">Phiên bản {{ agreement.version }}</p></div><Button :label="accepted(agreement) ? 'Đã chấp nhận' : 'Chấp nhận'" :disabled="accepted(agreement)" :loading="busy === agreement.id" @click="accept(agreement)" /></div>
      <ul class="mt-3 text-sm"><li v-for="share in agreement.shares" :key="share.author_id">Author #{{ share.author_id }}: {{ share.share_percent }}%</li></ul>
    </article>
    <p v-if="!agreements.length" class="py-8 text-center text-slate-500">Chưa có thỏa thuận cần xử lý.</p>
  </section></main>
</template>
