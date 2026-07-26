<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

const campaigns = ref([])
const loading = ref(true)
const search = ref('')
const activeFilter = ref('all')
const sendingCampaignId = ref(null)

const statusConfig = {
  draft: { label: 'Bản thảo', bg: 'bg-zinc-100 dark:bg-zinc-800', text: 'text-zinc-600 dark:text-zinc-400', icon: 'edit_note' },
  scheduled: { label: 'Lên lịch', bg: 'bg-blue-50 dark:bg-blue-950/30', text: 'text-blue-600 dark:text-blue-400', icon: 'schedule' },
  sent: { label: 'Đã gửi', bg: 'bg-emerald-50 dark:bg-emerald-950/30', text: 'text-emerald-600 dark:text-emerald-400', icon: 'check_circle' },
}

const audienceLabels = {
  all: 'Tất cả độc giả',
  active_readers: 'Độc giả tích cực',
  fiction_enthusiasts: 'Độc giả thích viễn tưởng',
  lapsed_users: 'Người dùng cũ (30 ngày)',
}

const stats = ref({
  total: 0,
  sent: 0,
  avg_open: 42.5,
  avg_click: 11.8
})

const fetchCampaigns = async () => {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.search = search.value
    if (activeFilter.value !== 'all') params.status = activeFilter.value

    const res = await apiClient.get('/api/admin/notifications/campaigns', { params })
    campaigns.value = res.data.data

    // Compute basic page statistics
    const allRes = await apiClient.get('/api/admin/notifications/campaigns')
    const list = allRes.data.data || []
    stats.value.total = list.length
    stats.value.sent = list.filter(c => c.status === 'sent').length
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải danh sách chiến dịch.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleSendImmediately = async (id) => {
  if (!confirm('Bạn có chắc chắn muốn gửi chiến dịch này ngay bây giờ không? Email và thông báo đẩy sẽ lập tức được chuyển đi.')) {
    return
  }

  sendingCampaignId.value = id
  try {
    await apiClient.post(`/api/admin/notifications/campaigns/${id}/send`)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Chiến dịch đã được gửi đi thành công!', life: 3000 })
    fetchCampaigns()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Gửi chiến dịch thất bại. Vui lòng thử lại.', life: 3000 })
  } finally {
    sendingCampaignId.value = null
  }
}

const handleDeleteCampaign = async (id) => {
  if (!confirm('Bạn có chắc chắn muốn xóa chiến dịch này không?')) {
    return
  }

  try {
    await apiClient.delete(`/api/admin/notifications/campaigns/${id}`)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Chiến dịch đã được xóa thành công.', life: 3000 })
    fetchCampaigns()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Xóa chiến dịch thất bại.', life: 3000 })
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  fetchCampaigns()
})
</script>

<template>
  <div class="pb-12 w-full pt-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 animate-fade-in">
      <div>
        <h1 class="text-3xl font-bold text-slate-800 dark:text-zinc-100 flex items-center gap-2">
          <span class="material-symbols-outlined text-[32px] text-indigo-600">campaign</span>
          Chiến dịch thông báo & Marketing
        </h1>
        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">
          Thiết kế chiến dịch tiếp cận, tạo thông báo đẩy và email khuyến mãi hàng loạt đến độc giả.
        </p>
      </div>
      <div>
        <router-link
          to="/admin/notifications/create"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-md transition-all duration-200 font-medium transform hover:-translate-y-0.5"
        >
          <span class="material-symbols-outlined text-[20px]">add</span>
          Tạo chiến dịch mới
        </router-link>
      </div>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <!-- Card 1 -->
      <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 flex items-center justify-center">
          <span class="material-symbols-outlined text-[24px]">campaign</span>
        </div>
        <div>
          <div class="text-xs text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold">Tổng chiến dịch</div>
          <div class="text-2xl font-bold text-slate-800 dark:text-zinc-100 mt-0.5">{{ stats.total }}</div>
        </div>
      </div>
      <!-- Card 2 -->
      <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 flex items-center justify-center">
          <span class="material-symbols-outlined text-[24px]">send</span>
        </div>
        <div>
          <div class="text-xs text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold">Đã phát đi</div>
          <div class="text-2xl font-bold text-slate-800 dark:text-zinc-100 mt-0.5">{{ stats.sent }}</div>
        </div>
      </div>
      <!-- Card 3 -->
      <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 flex items-center justify-center">
          <span class="material-symbols-outlined text-[24px]">mail</span>
        </div>
        <div>
          <div class="text-xs text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold">Tỷ lệ Mở Thư</div>
          <div class="text-2xl font-bold text-slate-800 dark:text-zinc-100 mt-0.5">{{ stats.avg_open }}%</div>
        </div>
      </div>
      <!-- Card 4 -->
      <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-5 shadow-sm flex items-center gap-4 hover:shadow-md transition-all duration-200">
        <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 flex items-center justify-center">
          <span class="material-symbols-outlined text-[24px]">touch_app</span>
        </div>
        <div>
          <div class="text-xs text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold">Tỷ lệ Click Link</div>
          <div class="text-2xl font-bold text-slate-800 dark:text-zinc-100 mt-0.5">{{ stats.avg_click }}%</div>
        </div>
      </div>
    </div>

    <!-- Filter & search actions -->
    <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl p-4 shadow-sm mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="f in ['all', 'sent', 'scheduled', 'draft']"
          :key="f"
          @click="() => { activeFilter = f; fetchCampaigns() }"
          class="px-4 py-2 text-xs font-semibold rounded-xl transition-all duration-200 flex items-center gap-1.5"
          :class="activeFilter === f
            ? 'bg-indigo-600 text-white shadow-sm'
            : 'bg-slate-50 dark:bg-zinc-800 hover:bg-slate-100 text-slate-600 dark:text-zinc-400'"
        >
          <span class="material-symbols-outlined text-[16px]">
            {{ f === 'all' ? 'list' : f === 'sent' ? 'check_circle' : f === 'scheduled' ? 'schedule' : 'edit_note' }}
          </span>
          {{ f === 'all' ? 'Tất cả' : f === 'sent' ? 'Đã gửi' : f === 'scheduled' ? 'Lên lịch' : 'Bản thảo' }}
        </button>
      </div>

      <div class="relative w-full md:w-80">
        <span class="absolute left-3 top-2.5 text-slate-400 dark:text-zinc-500 material-symbols-outlined text-[18px]">search</span>
        <input
          v-model="search"
          type="text"
          placeholder="Tìm tên chiến dịch, nội dung..."
          @keyup.enter="fetchCampaigns"
          class="pl-9 pr-4 py-2 text-sm w-full bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-slate-800 dark:text-zinc-100"
        />
      </div>
    </div>

    <!-- Table List -->
    <div class="bg-white dark:bg-zinc-900 border border-slate-100 dark:border-zinc-800 rounded-2xl shadow-sm overflow-hidden animate-slide-up">
      <div v-if="loading" class="p-12 flex justify-center items-center flex-col gap-3">
        <i class="pi pi-spin pi-spinner text-4xl text-indigo-600"></i>
        <span class="text-sm text-slate-500">Đang tải danh sách chiến dịch...</span>
      </div>

      <div v-else-if="campaigns.length === 0" class="p-16 text-center">
        <span class="material-symbols-outlined text-[64px] text-slate-300 dark:text-zinc-700">campaign</span>
        <h3 class="text-lg font-bold text-slate-800 dark:text-zinc-200 mt-4">Chưa có chiến dịch nào</h3>
        <p class="text-slate-500 dark:text-zinc-400 text-sm mt-1 max-w-sm mx-auto">
          Không tìm thấy chiến dịch nào tương ứng với bộ lọc hiện tại. Hãy bắt đầu bằng cách tạo mới.
        </p>
        <router-link
          to="/admin/notifications/create"
          class="inline-flex items-center gap-2 mt-6 px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold shadow-md hover:bg-indigo-700 transition-all"
        >
          <span class="material-symbols-outlined text-[16px]">add</span> Tạo chiến dịch đầu tiên
        </router-link>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-zinc-800/50 border-b border-slate-100 dark:border-zinc-800/80">
              <th class="py-4 px-6 text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Chiến dịch</th>
              <th class="py-4 px-6 text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Đối tượng nhận</th>
              <th class="py-4 px-6 text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Thời gian</th>
              <th class="py-4 px-6 text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Trạng thái</th>
              <th class="py-4 px-6 text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 dark:divide-zinc-800/80">
            <tr
              v-for="campaign in campaigns"
              :key="campaign.id"
              class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/20 transition-colors"
            >
              <!-- Info block -->
              <td class="py-4 px-6">
                <div class="flex items-start gap-3">
                  <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">
                      {{ campaign.image_url ? 'image' : 'campaign' }}
                    </span>
                  </div>
                  <div class="min-w-0">
                    <h4 class="font-bold text-slate-800 dark:text-zinc-200 text-sm truncate max-w-xs md:max-w-md">{{ campaign.title }}</h4>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 line-clamp-1 mt-0.5">{{ campaign.message }}</p>
                  </div>
                </div>
              </td>
              <!-- Target block -->
              <td class="py-4 px-6 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50/70 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 rounded-full text-xs font-medium">
                  <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                  {{ audienceLabels[campaign.target_audience] }}
                </span>
              </td>
              <!-- Time block -->
              <td class="py-4 px-6 whitespace-nowrap text-xs text-slate-500 dark:text-zinc-400">
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-slate-700 dark:text-zinc-300">
                    {{ campaign.status === 'scheduled' ? 'Lên lịch lúc' : 'Gửi lúc' }}
                  </span>
                  <span>
                    {{ formatDate(campaign.status === 'scheduled' ? campaign.scheduled_at : campaign.created_at) }}
                  </span>
                </div>
              </td>
              <!-- Status block -->
              <td class="py-4 px-6 whitespace-nowrap">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-full text-xs font-semibold"
                  :class="[statusConfig[campaign.status].bg, statusConfig[campaign.status].text]"
                >
                  <span class="material-symbols-outlined text-[14px]">
                    {{ statusConfig[campaign.status].icon }}
                  </span>
                  {{ statusConfig[campaign.status].label }}
                </span>
              </td>
              <!-- Action block -->
              <td class="py-4 px-6 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <!-- Send immediately -->
                  <button
                    v-if="campaign.status !== 'sent'"
                    @click="handleSendImmediately(campaign.id)"
                    class="p-2 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 rounded-lg transition-colors flex items-center justify-center"
                    v-tooltip.top="'Gửi ngay bây giờ'"
                    :disabled="sendingCampaignId === campaign.id"
                  >
                    <i v-if="sendingCampaignId === campaign.id" class="pi pi-spin pi-spinner text-sm"></i>
                    <span v-else class="material-symbols-outlined text-[18px]">send</span>
                  </button>

                  <!-- View Analytics -->
                  <router-link
                    v-if="campaign.status === 'sent'"
                    :to="`/admin/notifications/${campaign.id}/analytics`"
                    class="p-2 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 rounded-lg transition-colors flex items-center justify-center"
                    v-tooltip.top="'Xem hiệu quả'"
                  >
                    <span class="material-symbols-outlined text-[18px]">bar_chart</span>
                  </router-link>

                  <!-- Delete -->
                  <button
                    @click="handleDeleteCampaign(campaign.id)"
                    class="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors flex items-center justify-center"
                    v-tooltip.top="'Xóa chiến dịch'"
                  >
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
