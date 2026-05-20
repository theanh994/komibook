<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import apiClient from '@/services/axios'

const toast = useToast()

const config = ref({
  systemName: '',
  hotline: '',
  email: '',
  authorCommission: 0,
  serviceFee: 0,
  defaultBorrowDays: 14,
  maintenanceMode: false,
  maxUploadSize: 5,
})

const gateways = ref([
  { id: 'momo', name: 'Ví Momo', description: 'Thanh toán qua mã QR', icon: 'account_balance_wallet', color: '#A50064', enabled: true },
  { id: 'vnpay', name: 'VNPay', description: 'Cổng thanh toán nội địa', icon: 'payments', color: '#005BAA', enabled: true },
  { id: 'stripe', name: 'Credit Card (Stripe)', description: 'Thẻ quốc tế Visa/Master', icon: 'credit_card', color: '#73777e', enabled: false },
])

const loading = ref(true)
const saving = ref(false)

const fetchConfig = async () => {
  try {
    const res = await apiClient.get('/api/admin/config')
    const data = res.data.data
    config.value = {
      systemName: data.site_name,
      hotline: data.hotline || '',
      email: data.support_email,
      authorCommission: data.commission_rate,
      serviceFee: data.service_fee,
      defaultBorrowDays: data.default_borrow_days,
      maintenanceMode: data.maintenance_mode,
      maxUploadSize: data.max_upload_size,
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể tải cấu hình hệ thống.', life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleSave = async () => {
  saving.value = true
  try {
    const payload = {
      site_name: config.value.systemName,
      hotline: config.value.hotline,
      support_email: config.value.email,
      commission_rate: config.value.authorCommission,
      service_fee: config.value.serviceFee,
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
        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Quản lý thông tin chung, thanh toán và các tham số vận hành cốt lõi.</p>
      </div>
      <button
        @click="handleSave"
        :disabled="saving || loading"
        class="bg-primary text-on-primary font-label-md text-label-md px-lg py-sm rounded-lg hover:opacity-90 transition-opacity flex items-center gap-sm shadow-sm whitespace-nowrap disabled:opacity-50"
      >
        <span class="material-symbols-outlined text-[18px]" v-if="!saving">save</span>
        <span class="material-symbols-outlined text-[18px] animate-spin" v-else>progress_activity</span>
        {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
      </button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-3 gap-lg animate-pulse">
      <div class="lg:col-span-2 bg-surface-container-lowest h-96 rounded-xl shadow-soft"></div>
      <div class="bg-surface-container-lowest h-96 rounded-xl shadow-soft"></div>
    </div>

    <!-- Content Layout: Bento Grid Style -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-lg animate-slide-up">
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
            <div class="space-y-xs">
              <label class="font-label-md text-label-md text-on-surface-variant">Tỷ lệ chiết khấu tác giả</label>
              <div class="relative flex items-center">
                <input v-model.number="config.authorCommission" class="w-full bg-surface border border-outline-variant rounded-lg pl-md pr-xl py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-right" type="number"/>
                <span class="absolute right-md font-body-md text-on-surface-variant">%</span>
              </div>
              <p class="text-[12px] text-on-surface-variant mt-xs">Phần trăm doanh thu chia cho tác giả/NXB.</p>
            </div>
            <div class="space-y-xs">
              <label class="font-label-md text-label-md text-on-surface-variant">Phí dịch vụ cố định</label>
              <div class="relative flex items-center">
                <input v-model.number="config.serviceFee" class="w-full bg-surface border border-outline-variant rounded-lg pl-md pr-xl py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-right" type="text"/>
                <span class="absolute right-md font-body-md text-on-surface-variant">VNĐ</span>
              </div>
              <p class="text-[12px] text-on-surface-variant mt-xs">Phí áp dụng cho mỗi giao dịch thành công.</p>
            </div>
            <div class="space-y-xs md:col-span-2">
              <label class="font-label-md text-label-md text-on-surface-variant">Thời gian gia hạn mượn sách mặc định</label>
              <div class="relative flex items-center">
                <input v-model.number="config.defaultBorrowDays" class="w-full bg-surface border border-outline-variant rounded-lg pl-md pr-xl py-sm font-body-md text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="number"/>
                <span class="absolute right-md font-body-md text-on-surface-variant">Ngày</span>
              </div>
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
                <div class="w-12 h-12 rounded-lg flex items-center justify-center" :style="{ backgroundColor: gw.color + '15', color: gw.color }">
                  <span class="material-symbols-outlined text-[28px]">{{ gw.icon }}</span>
                </div>
                <div>
                  <p class="font-label-md text-label-md text-on-background">{{ gw.name }}</p>
                  <p class="text-[13px] text-on-surface-variant">{{ gw.description }}</p>
                </div>
              </div>
              <!-- Toggle Switch -->
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="gw.enabled" class="sr-only peer"/>
                <div class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
              </label>
            </div>
          </div>
          <div class="p-md bg-surface-container-low border-t border-outline-variant/10">
            <button class="w-full text-primary font-label-md text-label-md flex items-center justify-center gap-xs hover:underline">
              <span class="material-symbols-outlined text-[18px]">add</span>
              Thêm cổng thanh toán mới
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-soft { box-shadow: 0px 2px 12px 0px rgba(26,58,90,0.04); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>
