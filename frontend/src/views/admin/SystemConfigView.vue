<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'
import FeeSchedulesView from '@/views/admin/FeeSchedulesView.vue'
import vnpayLogo from '@/assets/logo-vnpay.png'
import adminLogo from '@/assets/logo-komi.png'

const toast = useToast()
const route = useRoute()
const router = useRouter()
const activeSection = computed(() => route.query.section === 'fees' ? 'fees' : 'general')

const setSection = (section) => {
  const query = section === 'fees' ? { section: 'fees' } : {}
  router.replace({ name: 'admin-system-config', query })
}

const config = ref({
  systemName: '',
  hotline: '',
  email: '',
  defaultBorrowDays: 14,
  maintenanceMode: false,
  maxUploadSize: 5,
})

const gateways = ref([])
const gatewayMeta = {
  demo_wallet: { description: 'Ví nội bộ cho thanh toán, hoàn tiền, doanh thu và yêu cầu rút. Không hỗ trợ nạp tiền ngoài', icon: 'wallet', color: '#1A3A5A' },
  vnpay: { description: 'Cổng VNPAY Sandbox, không phát sinh tiền thật', logo: vnpayLogo, color: '#005BAA' },
}

const loading = ref(true)
const saving = ref(false)

const fetchConfig = async () => {
  try {
    const [res, providersRes] = await Promise.all([
      apiClient.get('/api/admin/config'),
      apiClient.get('/api/admin/payment-providers'),
    ])
    const data = res.data.data
    config.value = {
      systemName: data.site_name,
      hotline: data.hotline || '',
      email: data.support_email,
      defaultBorrowDays: data.default_borrow_days,
      maintenanceMode: data.maintenance_mode,
      maxUploadSize: data.max_upload_size,
    }
    gateways.value = (providersRes.data.data || []).map((provider) => {
      const meta = gatewayMeta[provider.id] || { description: 'Phương thức thanh toán', icon: 'payments', color: '#1A3A5A' }
      return {
        ...provider,
        ...meta,
        icon: meta.logo ? undefined : meta.icon,
        enabled: provider.available,
        canConfigureDemo: provider.id === 'demo_wallet',
      }
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải cấu hình hệ thống.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const updateGateway = async (gateway) => {
  if (!gateway.canConfigureDemo) return
  const nextEnabled = !gateway.available
  gateway.updating = true
  try {
    const response = await apiClient.put(`/api/admin/payment-providers/${gateway.id}`, {
      enabled: nextEnabled,
      mode: nextEnabled ? 'demo' : 'disabled',
      reason: nextEnabled ? 'Bật mô phỏng không phát sinh phí từ cấu hình hệ thống' : 'Tắt mô phỏng từ cấu hình hệ thống',
    })
    Object.assign(gateway, response.data.data, { enabled: response.data.data.available })
    toast.add({ severity: 'success', summary: 'Đã cập nhật', detail: response.data.data.notice, life: 3500 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể cập nhật', detail: error.response?.data?.message || 'Cấu hình thanh toán bị từ chối.', life: 4000 })
  } finally {
    gateway.updating = false
  }
}

const handleSave = async () => {
  saving.value = true
  try {
    const payload = {
      site_name: config.value.systemName,
      hotline: config.value.hotline,
      support_email: config.value.email,
      default_borrow_days: config.value.defaultBorrowDays,
      maintenance_mode: config.value.maintenanceMode,
      max_upload_size: config.value.maxUploadSize,
    }
    await apiClient.put('/api/admin/config', payload)
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Cấu hình hệ thống đã được lưu.', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Đã xảy ra lỗi khi lưu cấu hình.', life: 3000 })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchConfig()
})
</script>

<template>
  <div class="pb-xxl w-full pt-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-md mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg text-primary font-bold">Cấu hình hệ thống</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">
          {{ activeSection === 'fees'
            ? 'Quản lý lịch Commission, phí dịch vụ và cách phân bổ dòng tiền.'
            : 'Quản lý thông tin chung, thanh toán và các tham số vận hành cốt lõi.' }}
        </p>
      </div>
      <button
        v-if="activeSection === 'general'"
        type="button"
        @click="handleSave"
        :disabled="saving || loading"
        class="min-h-11 bg-primary text-on-primary font-label-md text-label-md px-lg py-sm rounded-lg hover:opacity-90 transition-opacity flex items-center gap-sm shadow-sm whitespace-nowrap disabled:opacity-50"
      >
        <span class="material-symbols-outlined text-[18px]" v-if="!saving">save</span>
        <span class="material-symbols-outlined text-[18px] animate-spin" v-else>progress_activity</span>
        {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
      </button>
    </div>

    <nav class="mb-xl flex w-full gap-xs overflow-x-auto border-b border-outline-variant/30" aria-label="Nhóm cấu hình hệ thống">
      <button
        type="button"
        class="min-h-11 shrink-0 border-b-2 px-md py-sm font-label-md transition-colors"
        :class="activeSection === 'general' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-primary'"
        :aria-current="activeSection === 'general' ? 'page' : undefined"
        @click="setSection('general')"
      >
        Thông tin và vận hành
      </button>
      <button
        type="button"
        class="min-h-11 shrink-0 border-b-2 px-md py-sm font-label-md transition-colors"
        :class="activeSection === 'fees' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-primary'"
        :aria-current="activeSection === 'fees' ? 'page' : undefined"
        @click="setSection('fees')"
      >
        Commission và phí
      </button>
    </nav>

    <!-- Loading Skeleton -->
    <div v-if="activeSection === 'general' && loading" class="grid grid-cols-1 lg:grid-cols-3 gap-lg animate-pulse">
      <div class="lg:col-span-2 bg-surface-container-lowest h-96 rounded-xl shadow-soft"></div>
      <div class="bg-surface-container-lowest h-96 rounded-xl shadow-soft"></div>
    </div>

    <!-- Content Layout: Bento Grid Style -->
    <div v-else-if="activeSection === 'general'" class="grid grid-cols-1 lg:grid-cols-3 gap-lg animate-slide-up">
      <!-- Left Column -->
      <div class="lg:col-span-2 space-y-lg">
        <!-- Section: General Info -->
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/20 overflow-hidden">
          <div class="border-b border-outline-variant/20 px-lg py-md bg-surface">
            <h2 class="font-headline-md text-headline-md text-primary flex items-center gap-sm">
              <span class="material-symbols-outlined">info</span>
              Thông tin chung
            </h2>
          </div>
          <div class="p-lg space-y-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
              <div class="space-y-xs">
                <label class="font-label-md text-label-md text-on-surface-variant">Tên hệ thống</label>
                <input v-model="config.systemName" class="w-full bg-surface border border-outline-variant rounded-lg px-md py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="text"/>
              </div>
              <div class="space-y-xs">
                <label class="font-label-md text-label-md text-on-surface-variant">Hotline hỗ trợ</label>
                <input v-model="config.hotline" class="w-full bg-surface border border-outline-variant rounded-lg px-md py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="text"/>
              </div>
              <div class="space-y-xs md:col-span-2">
                <label class="font-label-md text-label-md text-on-surface-variant">Email liên hệ</label>
                <input v-model="config.email" class="w-full bg-surface border border-outline-variant rounded-lg px-md py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="email"/>
              </div>
            </div>
            <div class="space-y-xs pt-md border-t border-outline-variant/20">
              <label class="font-label-md text-label-md text-on-surface-variant">Logo hệ thống</label>
              <div class="border-2 border-dashed border-outline-variant rounded-xl p-lg flex flex-col items-center justify-center bg-surface hover:bg-surface-container-low transition-colors cursor-pointer group">
                <div class="h-16 w-16 rounded-lg bg-surface-container-highest flex items-center justify-center mb-md group-hover:scale-105 transition-transform">
                  <span class="material-symbols-outlined text-[32px] text-primary">cloud_upload</span>
                </div>
                <p class="font-label-md text-label-md text-primary">Nhấn để tải lên hoặc kéo thả file</p>
                <p class="font-body-md text-[13px] text-on-surface-variant mt-xs">SVG, PNG, JPG (Tối đa 2MB)</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Section: Operational Parameters -->
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/20 overflow-hidden">
          <div class="border-b border-outline-variant/20 px-lg py-md bg-surface">
            <h2 class="font-headline-md text-headline-md text-primary flex items-center gap-sm">
              <span class="material-symbols-outlined">tune</span>
              Tham số vận hành
            </h2>
          </div>
          <div class="p-lg grid grid-cols-1 md:grid-cols-2 gap-lg">
            <div class="space-y-xs md:col-span-2 rounded-lg border border-primary/20 bg-primary/5 p-md">
              <p class="font-label-md text-label-md text-on-surface">Commission và phí dịch vụ được quản lý theo lịch sử hiệu lực.</p>
              <RouterLink to="/admin/fee-schedules" class="mt-sm inline-flex min-h-11 items-center font-label-md text-primary hover:underline">Mở cài đặt cấu hình phí</RouterLink>
            </div>
          </div>
        </section>
      </div>

      <!-- Right Column: Payment Gateways -->
      <div class="space-y-lg">
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/20 overflow-hidden sticky top-24">
          <div class="border-b border-outline-variant/20 px-lg py-md bg-surface">
            <h2 class="font-headline-md text-headline-md text-primary flex items-center gap-sm">
              <span class="material-symbols-outlined">credit_card</span>
              Cổng thanh toán
            </h2>
          </div>
          <div class="p-0">
            <div
              v-for="(gw, idx) in gateways"
              :key="gw.id"
              class="p-lg flex items-center justify-between hover:bg-surface-container-low/50 transition-colors"
              :class="{ 'border-b border-outline-variant/10': idx < gateways.length - 1 }"
            >
              <div class="flex items-center gap-md">
                <div 
                  class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" 
                  :style="{ backgroundColor: gw.logo ? '#F8FAFC' : gw.color + '15', color: gw.color }"
                >
                  <img 
                    v-if="gw.logo" 
                    :src="gw.logo" 
                    :alt="gw.name" 
                    class="w-full h-full object-contain scale-[1.65]" 
                  />
                  <span v-else class="material-symbols-outlined text-[28px]">{{ gw.icon }}</span>
                </div>
                <div>
                  <p class="font-label-md text-label-md text-on-background">{{ gw.name }}</p>
                  <p class="text-[13px] text-on-surface-variant">{{ gw.description }}</p>
                </div>
              </div>
              <button
                type="button"
                class="min-h-11 min-w-11 rounded-full px-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-fixed-dim disabled:cursor-not-allowed disabled:opacity-50"
                :class="gw.available ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'"
                :disabled="!gw.canConfigureDemo || gw.updating"
                :aria-label="`${gw.available ? 'Tắt' : 'Bật'} ${gw.name}`"
                :title="gw.canConfigureDemo ? gw.notice : 'Không thay đổi trong phạm vi tích hợp không phát sinh phí'"
                @click="updateGateway(gw)"
              >
                <span class="material-symbols-outlined text-[20px]">{{ gw.updating ? 'progress_activity' : (gw.available ? 'toggle_on' : 'toggle_off') }}</span>
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
    <FeeSchedulesView v-else-if="activeSection === 'fees'" embedded class="animate-slide-up" />
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0px 2px 12px 0px rgba(26,58,90,0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>
