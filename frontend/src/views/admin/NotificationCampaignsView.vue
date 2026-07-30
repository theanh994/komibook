<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()
const campaigns = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const activeFilter = ref('all')
const sendingCampaignId = ref(null)
const stats = ref({ total: 0, sent: 0 })

const filterOptions = [
  { value: 'all', label: 'Tất cả', icon: 'list' },
  { value: 'sent', label: 'Đã gửi', icon: 'check_circle' },
  { value: 'scheduled', label: 'Lên lịch', icon: 'schedule' },
  { value: 'draft', label: 'Bản thảo', icon: 'edit_note' },
]
const statusConfig = {
  draft: { label: 'Bản thảo', classes: 'bg-surface-container text-on-surface-variant', icon: 'edit_note' },
  scheduled: { label: 'Lên lịch', classes: 'bg-primary-fixed text-on-primary-fixed', icon: 'schedule' },
  sent: { label: 'Đã gửi', classes: 'bg-green-100 text-green-800', icon: 'check_circle' },
}
const audienceLabels = {
  all: 'Tất cả độc giả',
  active_readers: 'Độc giả tích cực',
  fiction_enthusiasts: 'Độc giả thích viễn tưởng',
  lapsed_users: 'Người dùng cũ (30 ngày)',
}
const statusOf = (status) => statusConfig[status] || { label: status || 'Không rõ', classes: 'bg-surface-container text-on-surface-variant', icon: 'help' }

const fetchCampaigns = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = {
      ...(search.value.trim() && { search: search.value.trim() }),
      ...(activeFilter.value !== 'all' && { status: activeFilter.value }),
    }
    const [listResponse, allResponse, sentResponse] = await Promise.all([
      apiClient.get('/api/admin/notifications/campaigns', { params }),
      apiClient.get('/api/admin/notifications/campaigns', { params: { per_page: 1 } }),
      apiClient.get('/api/admin/notifications/campaigns', { params: { status: 'sent', per_page: 1 } }),
    ])
    campaigns.value = listResponse.data?.data || []
    stats.value = {
      total: Number(allResponse.data?.total || 0),
      sent: Number(sentResponse.data?.total || 0),
    }
  } catch (err) {
    campaigns.value = []
    error.value = err.response?.data?.message || 'Không thể tải danh sách chiến dịch.'
    toast.add({ severity: 'error', summary: 'Lỗi', detail: error.value, life: 3000 })
  } finally {
    loading.value = false
  }
}

const selectFilter = (value) => {
  activeFilter.value = value
  fetchCampaigns()
}

const handleSendImmediately = async (id) => {
  if (!confirm('Gửi chiến dịch này ngay bây giờ? Thông báo sẽ được đưa vào hàng đợi phát ngay.')) return
  sendingCampaignId.value = id
  try {
    await apiClient.post(`/api/admin/notifications/campaigns/${id}/send`)
    toast.add({ severity: 'success', summary: 'Đã xếp hàng', detail: 'Chiến dịch đang được hệ thống xử lý.', life: 3000 })
    await fetchCampaigns()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể gửi', detail: err.response?.data?.message || 'Vui lòng thử lại.', life: 3000 })
  } finally {
    sendingCampaignId.value = null
  }
}

const handleDeleteCampaign = async (id) => {
  if (!confirm('Xóa chiến dịch bản thảo này?')) return
  try {
    await apiClient.delete(`/api/admin/notifications/campaigns/${id}`)
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: 'Chiến dịch bản thảo đã được xóa.', life: 3000 })
    await fetchCampaigns()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể xóa', detail: err.response?.data?.message || 'Vui lòng thử lại.', life: 3000 })
  }
}

const retryCampaign = async (id) => {
  sendingCampaignId.value = id
  try {
    await apiClient.post(`/api/admin/notifications/campaigns/${id}/retry`)
    toast.add({ severity: 'success', summary: 'Đã xếp hàng lại', detail: 'Các batch thất bại sẽ được thử lại.', life: 3000 })
    await fetchCampaigns()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Không thể thử lại', detail: err.response?.data?.message || 'Vui lòng thử lại.', life: 3000 })
  } finally {
    sendingCampaignId.value = null
  }
}

const formatDate = (value) => value ? new Date(value).toLocaleString('vi-VN', {
  year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
}) : '—'

onMounted(fetchCampaigns)
</script>

<template>
  <main class="space-y-6" aria-labelledby="campaign-title">
    <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
      <div>
        <p class="text-sm font-bold uppercase tracking-[0.16em] text-secondary">Truyền thông</p>
        <h1 id="campaign-title" class="mt-1 text-2xl font-extrabold text-on-surface sm:text-3xl">Chiến dịch thông báo</h1>
        <p class="mt-1 max-w-3xl text-sm text-on-surface-variant">Tạo, lên lịch và theo dõi thông báo đến độc giả KomiBook.</p>
      </div>
      <router-link to="/admin/notifications/create" class="ui-btn ui-btn-primary self-start">
        <span class="material-symbols-outlined text-xl" aria-hidden="true">add</span>
        Tạo chiến dịch
      </router-link>
    </header>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2" aria-label="Tổng quan chiến dịch">
      <article class="ui-panel flex items-center gap-4">
        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-fixed text-on-primary-fixed"><span class="material-symbols-outlined" aria-hidden="true">campaign</span></span>
        <div><p class="text-sm text-on-surface-variant">Tổng chiến dịch</p><p class="text-2xl font-extrabold text-primary">{{ stats.total }}</p></div>
      </article>
      <article class="ui-panel flex items-center gap-4">
        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-100 text-green-800"><span class="material-symbols-outlined" aria-hidden="true">send</span></span>
        <div><p class="text-sm text-on-surface-variant">Đã phát đi</p><p class="text-2xl font-extrabold text-primary">{{ stats.sent }}</p></div>
      </article>
    </section>

    <section class="ui-panel flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between" aria-label="Bộ lọc chiến dịch">
      <div class="flex flex-wrap gap-2" role="group" aria-label="Lọc theo trạng thái">
        <button v-for="option in filterOptions" :key="option.value" type="button" class="ui-btn px-3 text-sm" :class="activeFilter === option.value ? 'ui-btn-primary' : 'ui-btn-secondary'" :aria-pressed="activeFilter === option.value" @click="selectFilter(option.value)">
          <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ option.icon }}</span>{{ option.label }}
        </button>
      </div>
      <form class="flex w-full gap-2 lg:max-w-md" role="search" @submit.prevent="fetchCampaigns">
        <label class="sr-only" for="campaign-search">Tìm kiếm chiến dịch</label>
        <input id="campaign-search" v-model="search" class="ui-field" type="search" placeholder="Tìm tiêu đề hoặc nội dung…" />
        <button type="submit" class="ui-btn ui-btn-secondary" aria-label="Tìm kiếm"><span class="material-symbols-outlined" aria-hidden="true">search</span></button>
      </form>
    </section>

    <section aria-labelledby="campaign-list-title">
      <h2 id="campaign-list-title" class="sr-only">Danh sách chiến dịch</h2>
      <div v-if="loading" class="ui-panel flex min-h-48 items-center justify-center gap-3" role="status" aria-live="polite">
        <span class="material-symbols-outlined animate-spin text-3xl text-primary" aria-hidden="true">progress_activity</span><span>Đang tải chiến dịch…</span>
      </div>
      <div v-else-if="error" class="ui-alert ui-alert-error text-center" role="alert">
        <p class="font-bold">Không thể tải danh sách chiến dịch</p><p class="mt-1 text-sm">{{ error }}</p>
        <button type="button" class="ui-btn ui-btn-secondary mt-4" @click="fetchCampaigns">Thử lại</button>
      </div>
      <div v-else-if="campaigns.length === 0" class="ui-empty-state bg-surface-container-lowest">
        <span class="material-symbols-outlined text-5xl text-outline" aria-hidden="true">campaign</span>
        <div><h3 class="font-extrabold">Chưa có chiến dịch phù hợp</h3><p class="mt-1 text-sm text-on-surface-variant">Thử thay đổi bộ lọc hoặc tạo chiến dịch mới.</p></div>
      </div>
      <div v-else class="grid gap-4">
        <article v-for="campaign in campaigns" :key="campaign.id" class="ui-panel grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_auto] lg:items-center">
          <div class="min-w-0">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold" :class="statusOf(campaign.status).classes"><span class="material-symbols-outlined text-sm" aria-hidden="true">{{ statusOf(campaign.status).icon }}</span>{{ statusOf(campaign.status).label }}</span>
              <span v-if="['partial_failed', 'failed'].includes(campaign.dispatch_status)" class="rounded-full bg-error-container px-2.5 py-1 text-xs font-bold text-on-error-container">Có batch lỗi</span>
            </div>
            <h3 class="truncate font-extrabold text-on-surface">{{ campaign.title }}</h3>
            <p class="mt-1 line-clamp-2 text-sm text-on-surface-variant">{{ campaign.message }}</p>
          </div>
          <div><p class="text-xs font-bold uppercase tracking-wide text-outline">Đối tượng</p><p class="mt-1 text-sm font-semibold">{{ audienceLabels[campaign.target_audience] || campaign.target_audience }}</p></div>
          <div><p class="text-xs font-bold uppercase tracking-wide text-outline">Thời gian</p><p class="mt-1 text-sm font-semibold">{{ formatDate(campaign.status === 'scheduled' ? campaign.scheduled_at : campaign.created_at) }}</p></div>
          <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <button v-if="campaign.status !== 'sent'" type="button" class="ui-btn ui-btn-secondary" :disabled="sendingCampaignId === campaign.id" :aria-label="`Gửi ngay ${campaign.title}`" @click="handleSendImmediately(campaign.id)"><span class="material-symbols-outlined" aria-hidden="true">send</span></button>
            <router-link v-if="campaign.status === 'sent'" :to="`/admin/notifications/${campaign.id}/analytics`" class="ui-btn ui-btn-secondary" :aria-label="`Xem báo cáo ${campaign.title}`"><span class="material-symbols-outlined" aria-hidden="true">bar_chart</span></router-link>
            <button v-if="['partial_failed', 'failed'].includes(campaign.dispatch_status)" type="button" class="ui-btn ui-btn-secondary text-warning" :aria-label="`Thử lại ${campaign.title}`" @click="retryCampaign(campaign.id)"><span class="material-symbols-outlined" aria-hidden="true">refresh</span></button>
            <button v-if="campaign.status !== 'sent' && campaign.dispatch_status === 'idle'" type="button" class="ui-btn ui-btn-secondary text-error" :aria-label="`Xóa ${campaign.title}`" @click="handleDeleteCampaign(campaign.id)"><span class="material-symbols-outlined" aria-hidden="true">delete</span></button>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>
