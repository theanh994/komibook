<script setup>
import { ref } from 'vue'

const period = ref('Tháng Này (T10 2023)')

// KPI Data (mock)
const kpiCards = [
  { label: 'Tổng Doanh Thu', value: '₫425,500,000', icon: 'account_balance_wallet', trend: '+12.5% so với tháng trước', trendColor: 'text-green-600', trendIcon: 'trending_up' },
  { label: 'Hoa Hồng Hệ Thống', value: '₫85,100,000', icon: 'percent', trend: '+8.2% so với tháng trước', trendColor: 'text-green-600', trendIcon: 'trending_up' },
  { label: 'Chờ Đối Soát', value: '₫124,000,000', icon: 'pending_actions', trend: '3 đối tác chưa thanh toán', trendColor: 'text-on-surface-variant', trendIcon: '' },
]

// Revenue distribution by publisher
const publishers = [
  { name: 'NXB Kim Đồng', percent: 45, color: 'bg-primary-container' },
  { name: 'NXB Trẻ', percent: 30, color: 'bg-primary-fixed-dim' },
  { name: 'Nhã Nam', percent: 15, color: 'bg-surface-tint' },
  { name: 'Khác', percent: 10, color: 'bg-surface-container-high' },
]

// Reconciliation table data
const reconciliations = ref([
  { period: 'Tháng 10/2023', totalRevenue: '₫425,500,000', commission: '₫85,100,000', netPayable: '₫340,400,000', status: 'pending' },
  { period: 'Tháng 09/2023', totalRevenue: '₫380,200,000', commission: '₫76,040,000', netPayable: '₫304,160,000', status: 'completed' },
  { period: 'Tháng 08/2023', totalRevenue: '₫410,000,000', commission: '₫82,000,000', netPayable: '₫328,000,000', status: 'completed' },
])

const getStatusStyle = (status) => {
  if (status === 'pending') return 'bg-yellow-100 text-yellow-800'
  return 'bg-green-100 text-green-800'
}
const getStatusLabel = (status) => status === 'pending' ? 'Chờ xử lý' : 'Đã thanh toán'
</script>

<template>
  <div class="px-lg md:px-xl pb-xxl max-w-container-max mx-auto w-full pt-6">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-end mb-xl animate-fade-in">
      <div>
        <h1 class="font-headline-lg text-headline-lg font-semibold text-primary">Báo cáo tài chính</h1>
        <p class="text-on-surface-variant font-body-md text-body-md mt-1">Tổng quan doanh thu và đối soát hàng tháng</p>
      </div>
      <div class="flex gap-md mt-md md:mt-0">
        <div class="relative bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2 flex items-center gap-2 shadow-sm cursor-pointer hover:border-primary transition-colors">
          <span class="material-symbols-outlined text-on-surface-variant text-[20px]">calendar_month</span>
          <span class="font-label-md text-label-md text-on-surface">{{ period }}</span>
          <span class="material-symbols-outlined text-on-surface-variant text-[20px]">arrow_drop_down</span>
        </div>
      </div>
    </header>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl animate-slide-up">
      <div
        v-for="(card, idx) in kpiCards" :key="idx"
        class="bg-surface-container-lowest p-lg rounded-xl shadow-card border border-outline-variant/20 flex flex-col justify-between h-32 relative overflow-hidden group hover:shadow-card-hover transition-all"
      >
        <div class="flex justify-between items-start z-10">
          <span class="font-label-md text-label-md text-on-surface-variant">{{ card.label }}</span>
          <span class="material-symbols-outlined text-primary bg-primary-container/30 p-1 rounded-md text-[20px]">{{ card.icon }}</span>
        </div>
        <div class="z-10">
          <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ card.value }}</span>
          <div class="flex items-center gap-1 mt-1 text-[12px] font-medium" :class="card.trendColor">
            <span v-if="card.trendIcon" class="material-symbols-outlined text-[14px]">{{ card.trendIcon }}</span>
            {{ card.trend }}
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg mb-xl animate-slide-up delay-100">
      <!-- Line Chart -->
      <div class="col-span-1 lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-card border border-outline-variant/20 p-lg">
        <h3 class="font-headline-md text-headline-md text-on-surface mb-6">Tăng Trưởng Doanh Thu</h3>
        <div class="h-64 w-full relative">
          <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-xs text-on-surface-variant pb-6">
            <span>500M</span><span>400M</span><span>300M</span><span>200M</span><span>100M</span><span>0</span>
          </div>
          <div class="ml-10 h-[calc(100%-1.5rem)] border-l border-b border-outline-variant relative">
            <div class="absolute w-full border-t border-outline-variant/30" style="top: 20%"></div>
            <div class="absolute w-full border-t border-outline-variant/30" style="top: 40%"></div>
            <div class="absolute w-full border-t border-outline-variant/30" style="top: 60%"></div>
            <div class="absolute w-full border-t border-outline-variant/30" style="top: 80%"></div>
            <svg class="absolute w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 100">
              <path d="M 0,80 L 16,70 L 33,75 L 50,40 L 66,50 L 83,20 L 100,10 L 100,100 L 0,100 Z" fill="url(#gradientFill)" opacity="0.2"/>
              <path d="M 0,80 L 16,70 L 33,75 L 50,40 L 66,50 L 83,20 L 100,10" fill="none" stroke="#1a3a5a" stroke-width="2"/>
              <defs>
                <linearGradient id="gradientFill" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#1a3a5a"/>
                  <stop offset="100%" stop-color="transparent"/>
                </linearGradient>
              </defs>
              <circle v-for="(pt, i) in [[0,80],[16,70],[33,75],[50,40],[66,50],[83,20],[100,10]]" :key="i" :cx="pt[0]" :cy="pt[1]" fill="#1a3a5a" r="1.5"/>
            </svg>
          </div>
          <div class="ml-10 mt-2 flex justify-between text-xs text-on-surface-variant">
            <span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>T8</span><span>T9</span><span>T10</span>
          </div>
        </div>
      </div>
      <!-- Pie Chart -->
      <div class="col-span-1 bg-surface-container-lowest rounded-xl shadow-card border border-outline-variant/20 p-lg flex flex-col">
        <h3 class="font-headline-md text-headline-md text-on-surface mb-6">Nguồn Thu Theo NXB</h3>
        <div class="flex-1 flex items-center justify-center relative">
          <div class="w-40 h-40 rounded-full border-8 border-surface-container-low relative" style="background: conic-gradient(#1a3a5a 0% 45%, #abc9f0 45% 75%, #436082 75% 90%, #e2e7ff 90% 100%);">
            <div class="absolute inset-0 m-auto w-24 h-24 bg-surface-container-lowest rounded-full flex items-center justify-center flex-col">
              <span class="text-xs text-on-surface-variant">Top NXB</span>
              <span class="font-bold text-primary">Kim Đồng</span>
            </div>
          </div>
        </div>
        <div class="mt-6 space-y-2">
          <div v-for="pub in publishers" :key="pub.name" class="flex justify-between items-center text-sm">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-sm" :class="pub.color"></div>
              <span>{{ pub.name }}</span>
            </div>
            <span class="font-medium">{{ pub.percent }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Reconciliation Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-card border border-outline-variant/20 overflow-hidden animate-slide-up delay-200">
      <div class="p-lg border-b border-outline-variant/30 flex justify-between items-center">
        <h3 class="font-headline-md text-headline-md text-on-surface">Chi Tiết Đối Soát</h3>
        <button class="text-primary hover:text-primary/80 font-label-md text-label-md flex items-center gap-1">
          Xem tất cả <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-label-md">
              <th class="py-3 px-lg font-medium">Kỳ Đối Soát</th>
              <th class="py-3 px-lg font-medium text-right">Tổng Doanh Thu</th>
              <th class="py-3 px-lg font-medium text-right">Hoa Hồng (20%)</th>
              <th class="py-3 px-lg font-medium text-right">Thực Nhận NXB</th>
              <th class="py-3 px-lg font-medium">Trạng Thái</th>
              <th class="py-3 px-lg font-medium text-center">Hành Động</th>
            </tr>
          </thead>
          <tbody class="text-body-md text-on-surface divide-y divide-outline-variant/20">
            <tr v-for="row in reconciliations" :key="row.period" class="hover:bg-surface-container-lowest transition-colors">
              <td class="py-4 px-lg font-medium">{{ row.period }}</td>
              <td class="py-4 px-lg text-right">{{ row.totalRevenue }}</td>
              <td class="py-4 px-lg text-right text-primary">{{ row.commission }}</td>
              <td class="py-4 px-lg text-right font-medium">{{ row.netPayable }}</td>
              <td class="py-4 px-lg">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusStyle(row.status)">
                  {{ getStatusLabel(row.status) }}
                </span>
              </td>
              <td class="py-4 px-lg text-center">
                <button class="text-primary hover:bg-primary-container/20 p-1.5 rounded-md transition-colors">
                  <span class="material-symbols-outlined text-[20px]">visibility</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shadow-card { box-shadow: 0px 12px 24px -4px rgba(26,58,90,0.04); }
.shadow-card-hover { box-shadow: 0px 16px 32px -4px rgba(26,58,90,0.08); }
.animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-slide-up { opacity: 0; transform: translateY(15px); animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
</style>
